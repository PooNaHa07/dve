<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['mentor', 'teacher', 'staff', 'admin']);

$u = current_user();
$redirect_error = null;
$redirect_success = false;



/* =============================== 
   ประมวลผลการลบ หรือ แก้ไขไฟล์ใบนิเทศก์
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    $teacher_id = (int)$u['id'];

    if ($action === 'delete') {
        $file_id = (int)$_POST['file_id'];
        $success_del = false;
        
        $stmt_find = $conn->prepare("SELECT file_path, status FROM supervision_files WHERE id = ? AND teacher_id = ?");
        if ($stmt_find) {
            $stmt_find->bind_param("ii", $file_id, $teacher_id);
            $stmt_find->execute();
            $res_find = $stmt_find->get_result();
            if ($res_find && $row_find = $res_find->fetch_assoc()) {
                $filepath = __DIR__ . '/../uploads/supervision_docs/original/' . $row_find['file_path'];
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
                
                $stmt_del = $conn->prepare("DELETE FROM supervision_files WHERE id = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("i", $file_id);
                    if ($stmt_del->execute()) {
                        $success_del = true;
                    } else {
                        $redirect_error = 'ลบข้อมูลจากระบบฐานข้อมูลล้มเหลว';
                    }
                    $stmt_del->close();
                }
            } else {
                $redirect_error = 'ไม่พบเอกสารนี้ หรือคุณไม่มีสิทธิ์ในการลบ';
            }
            $stmt_find->close();
        }
        
        if ($success_del) { header('Location: upload_supervision.php?deleted=1'); exit; }
        if ($redirect_error) { header('Location: upload_supervision.php?error=' . urlencode($redirect_error)); exit; }
    }

    if ($action === 'edit') {
        header('Content-Type: application/json; charset=utf-8');
        $file_id = (int)$_POST['file_id'];
        $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;

        if ($company_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'กรุณาเลือกสถานประกอบการ']);
            exit;
        }

        // ค้นหาไฟล์เดิมในฐานข้อมูล
        $stmt_find = $conn->prepare("SELECT file_path, status, company_id FROM supervision_files WHERE id = ? AND teacher_id = ?");
        if (!$stmt_find) { echo json_encode(['success' => false, 'error' => 'ระบบขัดข้อง']); exit; }
        $stmt_find->bind_param("ii", $file_id, $teacher_id);
        $stmt_find->execute();
        $res_find = $stmt_find->get_result();
        if (!($row_find = $res_find->fetch_assoc())) {
            echo json_encode(['success' => false, 'error' => 'ไม่พบเอกสาร หรือคุณไม่มีสิทธิ์แก้ไข']); exit;
        }
        $stmt_find->close();

        // 1. ตรวจสอบว่ามีการส่งไฟล์มาใหม่หรือไม่
        $has_new_files = isset($_FILES['physical_file']) && !empty($_FILES['physical_file']['name'][0]) && $_FILES['physical_file']['error'][0] !== UPLOAD_ERR_NO_FILE;

        if (!$has_new_files) {
            // กรณีเปลี่ยนแค่สถานประกอบการอย่างเดียว
            $stmt_upd = $conn->prepare("UPDATE supervision_files SET company_id = ? WHERE id = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("ii", $company_id, $file_id);
                if ($stmt_upd->execute()) {
                    echo json_encode(['success' => true, 'only_company' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'อัปเดตสถานประกอบการล้มเหลว']);
                }
                $stmt_upd->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ระบบฐานข้อมูลขัดข้อง']);
            }
            exit;
        }

        // กรณีมีการส่งไฟล์ใหม่มาด้วย -> อัปเดตไฟล์ + อัปเดตสถานประกอบการ
        // รวบรวมไฟล์ที่ถูกต้อง
        $upload_dir = __DIR__ . '/../uploads/supervision_docs/original/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $allowed     = ['pdf', 'jpg', 'jpeg', 'png'];
        $total_files = count($_FILES['physical_file']['name']);
        $valid_files = [];

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['physical_file']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['physical_file']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'error' => 'ไม่รองรับไฟล์ประเภทนี้ (รองรับ PDF, JPG, PNG)']); exit;
            }
            $valid_files[] = ['tmp_name' => $_FILES['physical_file']['tmp_name'][$i], 'name' => $_FILES['physical_file']['name'][$i], 'ext' => $ext];
        }

        if (empty($valid_files)) {
            echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์ที่ถูกต้อง']); exit;
        }

        // รวมทุกไฟล์เป็น PDF เดียว
        require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
        require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';

        $outFilename = 'SUP_' . $teacher_id . '_' . time() . '_' . uniqid() . '_edit.pdf';
        $outPath     = $upload_dir . $outFilename;
        $merge_error = null;

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->SetCreator('DVE System');
            $pdf->SetAutoPageBreak(false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($valid_files as $vf) {
                if ($vf['ext'] === 'pdf') {
                    try {
                        $pc = $pdf->setSourceFile($vf['tmp_name']);
                        for ($p = 1; $p <= $pc; $p++) {
                            $tplId = $pdf->importPage($p);
                            $size  = $pdf->getTemplateSize($tplId);
                            $orient = ($size['width'] > $size['height']) ? 'L' : 'P';
                            $pdf->AddPage($orient, [$size['width'], $size['height']]);
                            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);
                        }
                    } catch (Exception $e) { /* ข้ามหน้าที่อ่านไม่ได้ */ }
                } else {
                    $pdf->AddPage('P', [595.28, 841.89]);
                    try {
                        $pdf->Image($vf['tmp_name'], 0, 0, 595.28, 841.89, '', '', '', true, 300);
                    } catch (Exception $e) { /* ข้ามรูปที่แปลงไม่ได้ */ }
                }
            }
            $pdf->Output($outPath, 'F');
        } catch (Exception $e) {
            $merge_error = 'สร้างไฟล์ PDF ล้มเหลว: ' . $e->getMessage();
        }

        if ($merge_error) { echo json_encode(['success' => false, 'error' => $merge_error]); exit; }
        if (!file_exists($outPath)) { echo json_encode(['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์รวมได้']); exit; }

        // ลบไฟล์เดิม + อัปเดต DB
        $old = $upload_dir . $row_find['file_path'];
        if (file_exists($old)) @unlink($old);

        $stmt_upd = $conn->prepare("UPDATE supervision_files SET file_path = ?, company_id = ?, uploaded_at = NOW() WHERE id = ?");
        if ($stmt_upd) {
            $stmt_upd->bind_param("sii", $outFilename, $company_id, $file_id);
            if ($stmt_upd->execute()) {
                echo json_encode(['success' => true, 'pages' => count($valid_files)]);
            } else {
                @unlink($outPath);
                echo json_encode(['success' => false, 'error' => 'อัปเดตฐานข้อมูลล้มเหลว']);
            }
            $stmt_upd->close();
        } else {
            @unlink($outPath);
            echo json_encode(['success' => false, 'error' => 'ระบบฐานข้อมูลขัดข้อง']);
        }
        exit;
    }
}

/* =============================== 
   ประมวลผลการอัปโหลดไฟล์ใบนิเทศก์
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['upload_physical_file'])) {
    $teacher_id = (int)$u['id'];
    
    // ดึงสถานประกอบการที่เลือก (รองรับเลือกหลายแห่ง)
    $company_ids = [];
    if (!empty($_POST['company_ids']) && is_array($_POST['company_ids'])) {
        foreach ($_POST['company_ids'] as $cid) {
            $val = (int)$cid;
            if ($val > 0) $company_ids[] = $val;
        }
    } elseif (!empty($_POST['company_id']) && (int)$_POST['company_id'] > 0) {
        $company_ids[] = (int)$_POST['company_id'];
    }
    $company_ids = array_values(array_unique($company_ids));

    if (empty($company_ids)) {
        $redirect_error = 'กรุณาเลือกสถานประกอบการอย่างน้อย 1 แห่ง';
    } else if (!isset($_FILES['physical_file']) || empty($_FILES['physical_file']['name'][0])) {
        $redirect_error = 'กรุณาเลือกไฟล์ที่ต้องการอัปโหลด';
    } else {
        $upload_dir = __DIR__ . '/../uploads/supervision_docs/original/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $total_files = count($_FILES['physical_file']['name']);
        $valid_files = [];

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['physical_file']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['physical_file']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $redirect_error = 'ไม่รองรับไฟล์ประเภทนี้ (รองรับ PDF, JPG, PNG เท่านั้น)';
                    break;
                }
                $valid_files[] = [
                    'tmp_name' => $_FILES['physical_file']['tmp_name'][$i],
                    'name' => $_FILES['physical_file']['name'][$i],
                    'ext' => $ext
                ];
            }
        }

        if ($redirect_error === null && count($valid_files) > 0) {
            require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
            require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';

            /* ===================================================
               รวมทุกไฟล์ที่อัปโหลดพร้อมกันเป็น PDF ต้นฉบับ
               =================================================== */
            $masterFilename = 'SUP_' . $teacher_id . '_' . time() . '_' . uniqid() . '_master.pdf';
            $masterPath     = $upload_dir . $masterFilename;
            $failed_files   = [];

            try {
                $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
                $pdf->SetCreator('DVE System');
                $pdf->SetAuthor('Teacher');
                $pdf->SetAutoPageBreak(false);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);

                foreach ($valid_files as $vf) {
                    if ($vf['ext'] === 'pdf') {
                        try {
                            $pageCount = $pdf->setSourceFile($vf['tmp_name']);
                            for ($p = 1; $p <= $pageCount; $p++) {
                                $tplId = $pdf->importPage($p);
                                $size  = $pdf->getTemplateSize($tplId);
                                $orient = ($size['width'] > $size['height']) ? 'L' : 'P';
                                $pdf->AddPage($orient, [$size['width'], $size['height']]);
                                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);
                            }
                        } catch (Exception $e) {
                            $failed_files[] = $vf['name'] . ' (อ่าน PDF ล้มเหลว: ' . $e->getMessage() . ')';
                        }
                    } else {
                        $pdf->AddPage('P', [595.28, 841.89]);
                        try {
                            $pdf->Image($vf['tmp_name'], 0, 0, 595.28, 841.89, '', '', '', true, 300);
                        } catch (Exception $e) {
                            $failed_files[] = $vf['name'] . ' (แปลงรูปภาพล้มเหลว: ' . $e->getMessage() . ')';
                        }
                    }
                }

                $pdf->Output($masterPath, 'F');

            } catch (Exception $e) {
                $redirect_error = 'สร้างไฟล์ PDF ล้มเหลว: ' . $e->getMessage();
            }

            if ($redirect_error === null) {
                if (!file_exists($masterPath)) {
                    $redirect_error = 'ไม่สามารถบันทึกไฟล์รวมได้';
                } else {
                    $stmt = $conn->prepare("INSERT INTO supervision_files (teacher_id, student_id, company_id, file_path, status, uploaded_at) VALUES (?, 0, ?, ?, 1, NOW())");
                    if ($stmt) {
                        $inserted_count = 0;
                        foreach ($company_ids as $c_idx => $c_id) {
                            // สร้างไฟล์แยกแยกต่างหากสำหรับแต่ละสถานประกอบการ
                            $targetFilename = 'SUP_' . $teacher_id . '_C' . $c_id . '_' . time() . '_' . uniqid() . '.pdf';
                            $targetPath     = $upload_dir . $targetFilename;

                            if (@copy($masterPath, $targetPath)) {
                                $stmt->bind_param("iis", $teacher_id, $c_id, $targetFilename);
                                if ($stmt->execute()) {
                                    $inserted_count++;
                                } else {
                                    @unlink($targetPath);
                                }
                            }
                        }
                        $stmt->close();
                        @unlink($masterPath); // ลบ master PDF ชั่วคราว

                        if ($inserted_count > 0) {
                            $redirect_success = true;
                            header('Location: upload_supervision.php?success=1&count=' . $inserted_count);
                            exit;
                        } else {
                            $redirect_error = 'บันทึกข้อมูลลงฐานข้อมูลล้มเหลว';
                        }
                    } else {
                        @unlink($masterPath);
                        $redirect_error = 'ระบบฐานข้อมูลขัดข้อง';
                    }
                }
            }
        } elseif ($redirect_error === null) {
            $redirect_error = 'ไม่พบไฟล์ที่อัปโหลด';
        }
    }

    if ($redirect_success) { header('Location: upload_supervision.php?success=1'); exit; }
    if ($redirect_error)   { header('Location: upload_supervision.php?error=' . urlencode($redirect_error)); exit; }
}

// โหลดรายการสถานประกอบการสำหรับปุ่มตัวเลือก (กรองตามห้องที่ครูดูแล)
$companies = [];
$teacher_id_filter = (int)$u['id'];

// ดึงรายการห้องเรียนที่ครูดูแลจากตาราง teacher_assignments
$my_rooms = [];
$stmt_rooms = $conn->prepare("SELECT classroom_id FROM teacher_assignments WHERE teacher_id = ?");
if ($stmt_rooms) {
    $stmt_rooms->bind_param("i", $teacher_id_filter);
    $stmt_rooms->execute();
    $res_rooms = $stmt_rooms->get_result();
    while ($row_room = $res_rooms->fetch_assoc()) {
        $my_rooms[] = (int)$row_room['classroom_id'];
    }
    $stmt_rooms->close();
}

if (!empty($my_rooms)) {
    // กรองสถานประกอบการตามห้องเรียนที่ดูแล (ที่มีนักเรียนฝึกงานอยู่)
    $placeholders = implode(',', array_fill(0, count($my_rooms), '?'));
    $sql_c = "SELECT DISTINCT c.id, c.name 
              FROM companies c 
              JOIN users u ON u.company_id = c.id 
              WHERE u.role = 'student' AND u.classroom_id IN ($placeholders) 
              ORDER BY c.name ASC";
              
    $stmt_c = $conn->prepare($sql_c);
    if ($stmt_c) {
        $types = str_repeat('i', count($my_rooms));
        $stmt_c->bind_param($types, ...$my_rooms);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($res_c) {
            while ($row = $res_c->fetch_assoc()) {
                $companies[] = $row;
            }
        }
        $stmt_c->close();
    }
}

// ถ้าเป็นบทบาทอื่นหรือครูที่ยังไม่ได้จัดสรรห้องเรียน หรือห้องเรียนที่ดูแลไม่มีนักเรียนในสถานประกอบการใดเลย
// ให้ fallback แสดงสถานประกอบการทั้งหมด เพื่อไม่ให้หน้าเว็บว่างหรือทำงานไม่ได้
if (empty($companies)) {
    $res_c = $conn->query("SELECT id, name FROM companies ORDER BY name ASC");
    if ($res_c) {
        while ($row = $res_c->fetch_assoc()) {
            $companies[] = $row;
        }
    }
}

// โหลดประวัติการส่งของครูคนนี้
$teacher_id_me = (int)$u['id'];
$my_files = [];
$stmt_h = $conn->prepare("SELECT sf.id, sf.file_path, sf.status, sf.uploaded_at, sf.company_id,
        COALESCE(u.fullname, '—') as student_name,
        COALESCE(c.name, '—') as company_name
    FROM supervision_files sf
    LEFT JOIN users u ON u.id = sf.student_id
    LEFT JOIN companies c ON c.id = sf.company_id
    WHERE sf.teacher_id = ?
    ORDER BY sf.uploaded_at DESC LIMIT 20");
if ($stmt_h) {
    $stmt_h->bind_param("i", $teacher_id_me);
    $stmt_h->execute();
    $res_h = $stmt_h->get_result();
    while ($row = $res_h->fetch_assoc()) $my_files[] = $row;
    $stmt_h->close();
}

include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { background: #f0f2f8; }
.page-hero {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff !important;
    border-radius: 1.5rem;
    padding: 2.5rem 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 12px 32px rgba(79,70,229,.25);
}
.page-hero, .page-hero h1, .page-hero h2, .page-hero h3, .page-hero h4, .page-hero h5, .page-hero h6,
.page-hero p, .page-hero span, .page-hero small, .page-hero a, .page-hero i, .page-hero div {
    color: #fff !important;
}
.section-card {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 2rem;
}
.section-card .card-header-custom {
    padding: 1.1rem 1.5rem;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    border-bottom: 1px solid #f1f5f9;
}
.doc-btn {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.25rem;
    border-radius: 1rem;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    text-decoration: none;
    color: #1e293b;
    font-weight: 600;
    font-size: .9rem;
    transition: all .2s;
    cursor: pointer;
}
.doc-btn:hover {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(99,102,241,.15);
}
.doc-btn .icon-wrap {
    width: 42px; height: 42px;
    border-radius: .75rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.badge-type {
    font-size: .65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 50rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.dropzone-wrap {
    border: 2px dashed #c7d2fe;
    border-radius: 1.25rem;
    background: #f8faff;
    text-align: center;
    padding: 2.5rem 1.5rem;
    cursor: pointer;
    transition: .25s;
}
.dropzone-wrap:hover, .dropzone-wrap.drag-over {
    border-color: #6366f1;
    background: #eef2ff;
}
.file-list-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #f1f5f9;
    border-radius: .6rem;
    padding: .45rem .75rem;
    font-size: .82rem;
    font-weight: 600;
    color: #334155;
}
.status-badge {
    font-size: .72rem;
    padding: 4px 10px;
    border-radius: 50rem;
    font-weight: 700;
}

/* ===== Custom Company Picker ===== */
.cpicker-wrap { position: relative; }
.cpicker-trigger {
    display: flex; align-items: center; gap: .75rem;
    padding: .85rem 1.1rem;
    border: 2px solid #e2e8f0;
    border-radius: 1rem;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s, background .2s;
    user-select: none;
    min-height: 54px;
}
.cpicker-trigger:hover { border-color: #818cf8; background: #f8faff; }
.cpicker-trigger.open { border-color: #6366f1; box-shadow: 0 0 0 3.5px rgba(99,102,241,.18); }
.cpicker-trigger.has-value { border-color: #6366f1; background: linear-gradient(135deg,#f5f3ff,#eef2ff); }
.cpicker-icon {
    width: 38px; height: 38px; border-radius: .75rem;
    background: linear-gradient(135deg, #6366f1, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0; color: #fff;
    box-shadow: 0 3px 10px rgba(99,102,241,.35);
}
.cpicker-trigger.has-value .cpicker-icon { background: linear-gradient(135deg,#4f46e5,#6d28d9); }
.cpicker-body { flex: 1; min-width: 0; }
.cpicker-placeholder { color: #94a3b8; font-size: .88rem; }
.cpicker-selected { color: #1e293b; font-weight: 700; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpicker-badge { font-size: .65rem; font-weight: 700; background: linear-gradient(135deg,#6366f1,#7c3aed); color: #fff; padding: 2px 8px; border-radius: 50rem; margin-top: 2px; display: inline-block; }
.cpicker-arrow { color: #94a3b8; transition: transform .22s; font-size: 1rem; flex-shrink: 0; }
.cpicker-trigger.open .cpicker-arrow { transform: rotate(180deg); color: #6366f1; }
.cpicker-dropdown {
    position: absolute; top: calc(100% + .5rem); left: 0; right: 0;
    background: #fff;
    border: 1.5px solid #e0e7ff;
    border-radius: 1.1rem;
    box-shadow: 0 16px 48px rgba(79,70,229,.18);
    z-index: 1050;
    overflow: hidden;
    opacity: 0; transform: translateY(-8px) scale(.98);
    transition: opacity .18s, transform .18s;
    pointer-events: none;
}
.cpicker-dropdown.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
.cpicker-search-wrap { padding: .7rem .75rem; border-bottom: 1px solid #f1f5f9; }
.cpicker-search {
    width: 100%; padding: .55rem .85rem;
    border: 1.5px solid #e2e8f0; border-radius: .75rem;
    font-size: .875rem; outline: none; transition: border-color .2s, box-shadow .2s;
    background: #f8fafc;
}
.cpicker-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); background: #fff; }
.cpicker-list { max-height: 210px; overflow-y: auto; padding: .35rem; }
.cpicker-list::-webkit-scrollbar { width: 5px; }
.cpicker-list::-webkit-scrollbar-track { background: transparent; }
.cpicker-list::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 50rem; }
.cpicker-item {
    display: flex; align-items: center; gap: .6rem;
    padding: .65rem .85rem; border-radius: .75rem;
    cursor: pointer; transition: background .15s, color .15s;
    font-size: .875rem; color: #334155;
}
.cpicker-item:hover { background: #eef2ff; color: #4338ca; }
.cpicker-item.selected { background: linear-gradient(135deg,#eef2ff,#f5f3ff); color: #4f46e5; font-weight: 700; }
.cpicker-item .ci-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; transition: background .15s; }
.cpicker-item.selected .ci-dot, .cpicker-item:hover .ci-dot { background: #6366f1; }
.cpicker-item .ci-check { margin-left: auto; color: #6366f1; opacity: 0; font-size: .85rem; }
.cpicker-item.selected .ci-check { opacity: 1; }
.cpicker-empty { text-align: center; color: #94a3b8; font-size: .85rem; padding: 1.5rem .5rem; }
</style>

<?php
if (isset($_GET['success']) && (int)$_GET['success'] === 1) {
    $count_info = isset($_GET['count']) && (int)$_GET['count'] > 1 ? " รวมทั้งสิ้น " . (int)$_GET['count'] . " สถานประกอบการ (แยกส่งเอกสารเรียบร้อย)" : "";
    $success_txt = "ระบบรับไฟล์ใบนิเทศก์เรียบร้อยแล้ว" . $count_info;
    echo '<script>document.addEventListener("DOMContentLoaded",function(){Swal.fire({icon:"success",title:"อัปโหลดสำเร็จ!",text:' . json_encode($success_txt) . ',confirmButtonColor:"#4f46e5",confirmButtonText:"ตกลง"}).then(function(){var u=new URL(window.location.href);u.searchParams.delete("success");u.searchParams.delete("count");if(window.history.replaceState)window.history.replaceState({},"",u.pathname+(u.search||""));});});' . "</script>";
}
if (isset($_GET['deleted']) && (int)$_GET['deleted'] === 1) {
    echo '<script>document.addEventListener("DOMContentLoaded",function(){Swal.fire({icon:"success",title:"ลบไฟล์สำเร็จ!",text:"ระบบทำการลบไฟล์ใบนิเทศก์เรียบร้อยแล้ว",confirmButtonColor:"#4f46e5",confirmButtonText:"ตกลง"}).then(function(){var u=new URL(window.location.href);u.searchParams.delete("deleted");if(window.history.replaceState)window.history.replaceState({},"",u.pathname+(u.search||""));});});' . "</script>";
}
if (isset($_GET['error'])) {
    $em = htmlspecialchars($_GET['error']);
    echo '<script>document.addEventListener("DOMContentLoaded",function(){Swal.fire({icon:"error",title:"เกิดข้อผิดพลาด",text:' . json_encode($em) . ',confirmButtonColor:"#dc3545",confirmButtonText:"ตกลง"}).then(function(){var u=new URL(window.location.href);u.searchParams.delete("error");if(window.history.replaceState)window.history.replaceState({},"",u.pathname+(u.search||""));});});' . "</script>";
}
?>

<div class="container py-4" style="max-width:860px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb bg-white px-3 py-2 rounded-3 shadow-sm small mb-0">
            <li class="breadcrumb-item"><a href="../roles/teacher.php" class="text-decoration-none text-muted"><i class="bi bi-house-door"></i> หน้าหลัก</a></li>
            <li class="breadcrumb-item active text-primary fw-semibold">ใบนิเทศก์</li>
        </ol>
    </nav>

    <!-- Hero -->
    <div class="page-hero">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size:2.2rem; line-height:1;">📋</div>
            <div>
                <h1 class="fw-bold mb-1 fs-4">ระบบจัดการใบนิเทศก์</h1>
                <p class="mb-0 opacity-80 small">ดาวน์โหลดแบบฟอร์ม → กรอกและเซ็นชื่อจริง → อัปโหลดไฟล์กลับเข้าระบบ</p>
            </div>
        </div>
    </div>

    <!-- ดาวน์โหลดเอกสาร -->
    <div class="section-card">
        <div class="card-header-custom">
            <span style="color:#16a34a; font-size:1.2rem;">⬇️</span>
            <span>ดาวน์โหลดแบบฟอร์ม</span>
        </div>
        <div class="p-4">
            <p class="text-muted small mb-3">เลือกแบบฟอร์มที่ต้องการ แล้วพิมพ์ออกมากรอกข้อมูลและลงลายมือชื่อด้วยตัวเอง</p>
            <div class="row g-3">
                <!-- ปกติ -->
                <div class="col-12">
                    <p class="text-uppercase fw-bold text-muted small mb-2" style="letter-spacing:.08em;">📁 แบบปกติ</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="../template/pgga.pdf" target="_blank" class="doc-btn">
                            <div class="icon-wrap" style="background:#dcfce7; color:#16a34a;">📄</div>
                            <div>
                                <div>ใบปะหน้า <span class="badge-type bg-success text-white">ปกติ</span></div>
                                <div class="text-muted fw-normal" style="font-size:.78rem;">pgga.pdf</div>
                            </div>
                        </a>
                        <a href="../template/pgfa.pdf" target="_blank" class="doc-btn">
                            <div class="icon-wrap" style="background:#dcfce7; color:#16a34a;">📝</div>
                            <div>
                                <div>ใบนิเทศ <span class="badge-type bg-success text-white">ปกติ</span></div>
                                <div class="text-muted fw-normal" style="font-size:.78rem;">pgfa.pdf</div>
                            </div>
                        </a>
                    </div>
                </div>
                <!-- ทวิภาคี -->
                <div class="col-12">
                    <p class="text-uppercase fw-bold text-muted small mb-2" style="letter-spacing:.08em;">📁 แบบทวิภาคี</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="../template/twipa.pdf" target="_blank" class="doc-btn">
                            <div class="icon-wrap" style="background:#dbeafe; color:#2563eb;">📄</div>
                            <div>
                                <div>ใบปะหน้า <span class="badge-type bg-primary text-white">ทวิภาคี</span></div>
                                <div class="text-muted fw-normal" style="font-size:.78rem;">twipa.pdf</div>
                            </div>
                        </a>
                        <a href="../template/twifa.pdf" target="_blank" class="doc-btn">
                            <div class="icon-wrap" style="background:#dbeafe; color:#2563eb;">📝</div>
                            <div>
                                <div>ใบนิเทศ <span class="badge-type bg-primary text-white">ทวิภาคี</span></div>
                                <div class="text-muted fw-normal" style="font-size:.78rem;">twifa.pdf</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- อัปโหลดไฟล์ -->
    <div class="section-card">
        <div class="card-header-custom">
            <span style="color:#7c3aed; font-size:1.2rem;">⬆️</span>
            <span>อัปโหลดใบนิเทศก์ที่เซ็นแล้ว</span>
        </div>
        <div class="p-4">
            <p class="text-muted small mb-3">หลังกรอกข้อมูลและลงลายมือชื่อในเอกสารจริงแล้ว ถ่ายภาพหรือสแกนเป็น PDF แล้วอัปโหลดที่นี่</p>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="upload_physical_file" value="1">
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark mb-2">
                        <span class="text-danger">*</span> เลือกสถานประกอบการ:
                        <span class="text-muted fw-normal small ms-1">(<?= count($companies) ?> แห่ง)</span>
                    </label>
                    <!-- Hidden native select for form submission -->
                    <select name="company_ids[]" id="companySelect" class="d-none" multiple required>
                        <?php foreach ($companies as $comp): ?>
                            <option value="<?= (int)$comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Custom picker -->
                    <div class="cpicker-wrap" id="mainPickerWrap">
                        <div class="cpicker-trigger" id="mainPickerTrigger" tabindex="0" role="combobox" aria-expanded="false">
                            <div class="cpicker-icon">🏢</div>
                            <div class="cpicker-body">
                                <div class="cpicker-placeholder" id="mainPickerLabel">กรุณาเลือกสถานประกอบการ (เลือกได้หลายแห่ง)</div>
                            </div>
                            <i class="bi bi-chevron-down cpicker-arrow"></i>
                        </div>
                        <div class="cpicker-dropdown" id="mainPickerDropdown">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                                <button type="button" class="btn btn-sm btn-link text-primary p-0 fw-bold text-decoration-none" id="btnSelectAllComp">
                                    <i class="bi bi-check-all fs-6 me-1"></i>เลือกทั้งหมด
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="btnClearComp">
                                    <i class="bi bi-x-circle me-1"></i>ล้างการเลือก
                                </button>
                            </div>
                            <div class="cpicker-search-wrap">
                                <input type="text" class="cpicker-search" id="mainPickerSearch" placeholder="🔍  ค้นหาชื่อสถานประกอบการ..." autocomplete="off">
                            </div>
                            <div class="cpicker-list" id="mainPickerList">
                                <?php foreach ($companies as $comp): ?>
                                <div class="cpicker-item" data-id="<?= (int)$comp['id'] ?>" data-name="<?= htmlspecialchars($comp['name'], ENT_QUOTES) ?>">
                                    <span class="ci-dot"></span>
                                    <span><?= htmlspecialchars($comp['name']) ?></span>
                                    <i class="bi bi-check2 ci-check"></i>
                                </div>
                                <?php endforeach; ?>
                                <div class="cpicker-empty d-none">ไม่พบสถานประกอบการที่ค้นหา 🔍</div>
                            </div>
                            <div class="p-2 border-top bg-light text-end">
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" id="btnDoneCompPicker">
                                    <i class="bi bi-check-lg me-1"></i>ตกลง
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dropzone-wrap mb-3" id="dropzoneArea">
                    <div style="font-size:2.5rem; margin-bottom:.5rem;">☁️</div>
                    <p class="fw-bold mb-1 text-dark">คลิกเพื่อเลือกไฟล์ หรือลากมาวางตรงนี้</p>
                    <p class="text-muted small mb-0">รองรับ PDF, JPG, PNG — อัปโหลดหลายไฟล์พร้อมกันได้ (ระบบจะแยกเป็นรายเอกสารโดยอัตโนมัติ)</p>
                    <input type="file" name="physical_file[]" id="fileInput" class="d-none" accept=".pdf,image/jpeg,image/png" multiple>
                </div>
                <div id="fileListWrap" class="d-none mb-3">
                    <div class="fw-bold small text-dark mb-2">✅ ไฟล์ที่เลือก:</div>
                    <div id="fileList" class="d-flex flex-wrap gap-2"></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-lg px-5 fw-bold rounded-pill shadow-sm" id="btnSubmit" disabled
                        style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;">
                        <i class="bi bi-send-fill me-2"></i> ส่งใบนิเทศก์
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg rounded-pill" onclick="clearFiles()">ล้าง</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ประวัติการส่ง -->
    <?php if (!empty($my_files)): ?>
    <div class="section-card">
        <div class="card-header-custom">
            <span style="color:#0891b2; font-size:1.2rem;">🕐</span>
            <span>ประวัติการส่ง (20 รายการล่าสุด)</span>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size:.875rem;">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th class="ps-4 fw-700">วันที่ส่ง</th>
                            <th class="fw-700">สถานประกอบการ</th>
                            <th class="pe-4 text-center" style="width: 250px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_files as $mf): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold"><?= date('d/m/Y', strtotime($mf['uploaded_at'])) ?></div>
                                <div class="text-muted small"><?= date('H:i น.', strtotime($mf['uploaded_at'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($mf['company_name']) ?></div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-2">
                                    <?php
                                    $file_url = '../uploads/supervision_docs/original/' . htmlspecialchars($mf['file_path']);
                                    ?>
                                    <a href="<?= $file_url ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="ดูไฟล์">
                                        <i class="bi bi-eye"></i> ดูไฟล์
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="editFile(<?= $mf['id'] ?>, <?= (int)$mf['company_id'] ?>)" title="แก้ไขไฟล์">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmDelete(<?= $mf['id'] ?>)" title="ลบไฟล์">
                                        <i class="bi bi-trash"></i> ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
var dz = document.getElementById('dropzoneArea');
var fi = document.getElementById('fileInput');
var btnSubmit = document.getElementById('btnSubmit');
var selectedFiles = [];

dz.addEventListener('click', function() { fi.click(); });

dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', function() { dz.classList.remove('drag-over'); });
dz.addEventListener('drop', function(e) {
    e.preventDefault(); dz.classList.remove('drag-over');
    addFiles(e.dataTransfer.files);
});

fi.addEventListener('change', function() {
    addFiles(this.files);
    this.value = ''; // รีเซ็ตค่าเพื่ออนุญาตให้เลือกไฟล์เดิมซ้ำได้หลังจากลบออก
});

function addFiles(filesList) {
    if (!filesList) return;
    Array.from(filesList).forEach(function(f) {
        // ตรวจสอบไฟล์ซ้ำ (อ้างอิงชื่อและขนาดไฟล์)
        var isDuplicate = selectedFiles.some(function(existing) {
            return existing.name === f.name && existing.size === f.size;
        });
        if (!isDuplicate) {
            selectedFiles.push(f);
        }
    });
    updateFilesUI();
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFilesUI();
}

function updateFilesUI() {
    var wrap = document.getElementById('fileListWrap');
    var list = document.getElementById('fileList');
    if (selectedFiles.length > 0) {
        list.innerHTML = '';
        selectedFiles.forEach(function(f, index) {
            var el = document.createElement('div');
            el.className = 'file-list-item d-flex align-items-center justify-content-between gap-3';
            el.innerHTML = '<span>📎 ' + f.name + ' <span class="text-muted fw-normal">(' + (f.size/1024/1024).toFixed(2) + ' MB)</span></span>' +
                          '<button type="button" class="btn btn-link p-0 text-danger border-0 line-height-1" onclick="removeFile(' + index + ')" title="ลบไฟล์นี้" style="text-decoration:none; line-height:1;">' +
                          '<i class="bi bi-x-circle-fill"></i>' +
                          '</button>';
            list.appendChild(el);
        });
        wrap.classList.remove('d-none');
        btnSubmit.disabled = false;
    } else {
        wrap.classList.add('d-none');
        btnSubmit.disabled = true;
    }
}

function clearFiles() {
    selectedFiles = [];
    fi.value = '';
    updateFilesUI();
}

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    var compSelect = document.getElementById('companySelect');
    var compOpts = compSelect ? compSelect.selectedOptions : null;
    if (!compOpts || compOpts.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'คำเตือน',
            text: 'กรุณาเลือกสถานประกอบการอย่างน้อย 1 แห่งก่อนอัปโหลดไฟล์',
            confirmButtonColor: '#4f46e5'
        });
        return false;
    }

    if (selectedFiles.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'คำเตือน',
            text: 'กรุณาเลือกไฟล์อย่างน้อย 1 ไฟล์',
            confirmButtonColor: '#4f46e5'
        });
        return false;
    }
    
    var dt = new DataTransfer();
    selectedFiles.forEach(function(f) {
        dt.items.add(f);
    });
    fi.files = dt.files;
});

function confirmDelete(fileId) {
    Swal.fire({
        title: 'ยืนยันการลบไฟล์?',
        text: 'ไฟล์และข้อมูลใบนิเทศก์นี้จะถูกลบออกจากระบบอย่างถาวร',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('deleteForm').submit();
        }
    });
}

/* ---- Edit Modal State ---- */
var editFileId = null;
var editSelectedFiles = [];

function editFile(fileId, companyId) {
    editFileId = fileId;
    editSelectedFiles = [];

    // Sync hidden native select
    var editCompanySelect = document.getElementById('editCompanySelect');
    if (editCompanySelect) editCompanySelect.value = companyId;

    // Sync custom edit picker UI
    syncEditPicker(companyId);

    updateEditUI();
    var modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function addEditFiles(filesList) {
    if (!filesList) return;
    var allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    Array.from(filesList).forEach(function(f) {
        var ext = f.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) return;
        var dup = editSelectedFiles.some(function(e) { return e.name === f.name && e.size === f.size; });
        if (!dup) editSelectedFiles.push(f);
    });
    updateEditUI();
}

function removeEditFile(idx) {
    editSelectedFiles.splice(idx, 1);
    updateEditUI();
}

function updateEditUI() {
    var list   = document.getElementById('editFileList');
    var wrap   = document.getElementById('editFileListWrap');
    var btnOk  = document.getElementById('btnEditSubmit');
    var count  = document.getElementById('editFileCount');
    
    var editCompanySelect = document.getElementById('editCompanySelect');
    var hasCompany = editCompanySelect && editCompanySelect.value !== '';
    btnOk.disabled = !hasCompany;

    if (editSelectedFiles.length > 0) {
        list.innerHTML = '';
        editSelectedFiles.forEach(function(f, idx) {
            var div = document.createElement('div');
            div.className = 'edit-file-item';
            div.innerHTML =
                '<span class="edit-file-name">📎 ' + f.name +
                ' <span class="text-muted fw-normal">(' + (f.size/1024/1024).toFixed(2) + ' MB)</span></span>' +
                '<button type="button" class="btn btn-link p-0 text-danger" onclick="removeEditFile(' + idx + ')" style="text-decoration:none;">' +
                '<i class="bi bi-x-circle-fill"></i></button>';
            list.appendChild(div);
        });
        wrap.style.display = 'block';
        count.textContent = editSelectedFiles.length + ' ไฟล์ (จะถูกรวมเป็น PDF เดียว)';
    } else {
        wrap.style.display = 'none';
        count.textContent = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var edz      = document.getElementById('editDropzone');
    var efi      = document.getElementById('editFileInput');
    var btnOk    = document.getElementById('btnEditSubmit');
    var progress = document.getElementById('editProgress');
    var progBar  = document.getElementById('editProgressBar');
    var editCompanySelect = document.getElementById('editCompanySelect');

    if (editCompanySelect) {
        editCompanySelect.addEventListener('change', function() {
            updateEditUI();
        });
    }

    edz.addEventListener('click', function() { efi.click(); });
    edz.addEventListener('dragover',  function(e) { e.preventDefault(); edz.classList.add('drag-over'); });
    edz.addEventListener('dragleave', function()  { edz.classList.remove('drag-over'); });
    edz.addEventListener('drop', function(e) {
        e.preventDefault(); edz.classList.remove('drag-over');
        addEditFiles(e.dataTransfer.files);
    });
    efi.addEventListener('change', function() {
        addEditFiles(this.files);
        this.value = '';
    });

    btnOk.addEventListener('click', function() {
        if (!editFileId) return;

        // แสดง progress bar
        btnOk.disabled = true;
        progress.style.display = 'block';
        progBar.style.width = '0%';

        var formData = new FormData();
        formData.append('action', 'edit');
        formData.append('file_id', editFileId);
        formData.append('company_id', editCompanySelect ? editCompanySelect.value : '');
        
        editSelectedFiles.forEach(function(f) {
            formData.append('physical_file[]', f);
        });

        // Simulate progress while uploading
        var pct = 0;
        var tick = setInterval(function() {
            pct = Math.min(pct + Math.random() * 12, 90);
            progBar.style.width = pct + '%';
        }, 300);

        fetch('upload_supervision.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            clearInterval(tick);
            progBar.style.width = '100%';
            setTimeout(function() {
                var modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                if (modal) modal.hide();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'แก้ไขสำเร็จ!',
                        html: data.only_company 
                            ? 'แก้ไขสถานประกอบการเรียบร้อยแล้ว' 
                            : 'รวมไฟล์ <strong>' + editSelectedFiles.length + ' ไฟล์</strong> เป็น PDF เดียวเรียบร้อยแล้ว',
                        confirmButtonColor: '#4f46e5',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(function() { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.error || 'แก้ไขล้มเหลว', confirmButtonColor: '#dc3545' });
                    btnOk.disabled = false;
                    progress.style.display = 'none';
                }
            }, 400);
        })
        .catch(function() {
            clearInterval(tick);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonColor: '#dc3545' });
            btnOk.disabled = false;
            progress.style.display = 'none';
        });
    });

    // รีเซ็ต modal เมื่อปิด
    document.getElementById('editModal').addEventListener('hidden.bs.modal', function() {
        editSelectedFiles = [];
        editFileId = null;
        if (editCompanySelect) editCompanySelect.value = '';
        resetEditPicker();
        updateEditUI();
        document.getElementById('editProgress').style.display = 'none';
        document.getElementById('editProgressBar').style.width = '0%';
    });
});

/* ============================================================
   CUSTOM COMPANY PICKER — Main Upload Form (Multi-Select)
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    var wrap          = document.getElementById('mainPickerWrap');
    var trigger       = document.getElementById('mainPickerTrigger');
    var dropdown      = document.getElementById('mainPickerDropdown');
    var label         = document.getElementById('mainPickerLabel');
    var search        = document.getElementById('mainPickerSearch');
    var native        = document.getElementById('companySelect');
    var btnSelectAll  = document.getElementById('btnSelectAllComp');
    var btnClearAll   = document.getElementById('btnClearComp');
    var btnDone       = document.getElementById('btnDoneCompPicker');
    if (!wrap) return;

    var items       = wrap.querySelectorAll('.cpicker-item');
    var empty       = wrap.querySelector('.cpicker-empty');
    var selectedIds = [];

    function openPicker() {
        dropdown.classList.add('show');
        trigger.classList.add('open');
        trigger.setAttribute('aria-expanded','true');
        search.value = ''; filterItems('');
        setTimeout(function(){ search.focus(); }, 60);
    }
    function closePicker() {
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
        trigger.setAttribute('aria-expanded','false');
    }

    trigger.addEventListener('click', function() {
        dropdown.classList.contains('show') ? closePicker() : openPicker();
    });
    trigger.addEventListener('keydown', function(e) {
        if (e.key==='Enter'||e.key===' ') { e.preventDefault(); trigger.click(); }
        if (e.key==='Escape') closePicker();
    });
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) closePicker();
    });
    if (btnDone) {
        btnDone.addEventListener('click', function(e) {
            e.stopPropagation();
            closePicker();
        });
    }

    function updatePickerState() {
        // Sync native multi-select options
        Array.from(native.options).forEach(function(opt) {
            opt.selected = selectedIds.includes(opt.value);
        });

        // Sync items UI
        items.forEach(function(item) {
            var id = item.dataset.id;
            if (selectedIds.includes(id)) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });

        // Sync trigger label
        if (selectedIds.length === 0) {
            label.innerHTML = '<span class="cpicker-placeholder">กรุณาเลือกสถานประกอบการ (เลือกได้หลายแห่ง)</span>';
            trigger.classList.remove('has-value');
        } else if (selectedIds.length === 1) {
            var selectedItem = Array.from(items).find(function(i){ return i.dataset.id === selectedIds[0]; });
            var name = selectedItem ? selectedItem.dataset.name : '';
            label.innerHTML = '<span class="cpicker-selected">' + name + '</span><br><span class="cpicker-badge"><i class="bi bi-building me-1"></i>เลือก 1 สถานประกอบการ</span>';
            trigger.classList.add('has-value');
        } else {
            label.innerHTML = '<span class="cpicker-selected">เลือกแล้ว ' + selectedIds.length + ' สถานประกอบการ</span><br><span class="cpicker-badge"><i class="bi bi-buildings me-1"></i>เลือกหลายสถานประกอบการพร้อมกัน</span>';
            trigger.classList.add('has-value');
        }
    }

    items.forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            var id = this.dataset.id;
            var idx = selectedIds.indexOf(id);
            if (idx > -1) {
                selectedIds.splice(idx, 1);
            } else {
                selectedIds.push(id);
            }
            updatePickerState();
        });
    });

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function(e) {
            e.stopPropagation();
            selectedIds = [];
            items.forEach(function(i) {
                if (i.style.display !== 'none') {
                    selectedIds.push(i.dataset.id);
                }
            });
            updatePickerState();
        });
    }

    if (btnClearAll) {
        btnClearAll.addEventListener('click', function(e) {
            e.stopPropagation();
            selectedIds = [];
            updatePickerState();
        });
    }

    search.addEventListener('input', function() { filterItems(this.value.trim()); });
    function filterItems(q) {
        var n = 0;
        items.forEach(function(i) {
            var match = i.dataset.name.toLowerCase().indexOf(q.toLowerCase()) > -1;
            i.style.display = match ? '' : 'none';
            if (match) n++;
        });
        empty.classList.toggle('d-none', n > 0);
    }
});

/* ============================================================
   CUSTOM COMPANY PICKER — Edit Modal
   ============================================================ */
var _editPickerItems = [];
function syncEditPicker(companyId) {
    var wrap    = document.getElementById('editPickerWrap');
    var trigger = document.getElementById('editPickerTrigger');
    var label   = document.getElementById('editPickerLabel');
    if (!wrap) return;
    var items = wrap.querySelectorAll('.cpicker-item');
    items.forEach(function(i) { i.classList.remove('selected'); });
    trigger.classList.remove('has-value');
    label.innerHTML = '<span class="cpicker-placeholder">กรุณาเลือกสถานประกอบการ</span>';
    if (!companyId) return;
    items.forEach(function(i) {
        if (i.dataset.id == companyId) {
            i.classList.add('selected');
            trigger.classList.add('has-value');
            label.innerHTML = '<span class="cpicker-selected">' + i.dataset.name + '</span><br><span class="cpicker-badge"><i class="bi bi-building me-1"></i>สถานประกอบการที่เลือก</span>';
        }
    });
}
function resetEditPicker() { syncEditPicker(null); }

document.addEventListener('DOMContentLoaded', function() {
    var wrap    = document.getElementById('editPickerWrap');
    var trigger = document.getElementById('editPickerTrigger');
    var dropdown= document.getElementById('editPickerDropdown');
    var label   = document.getElementById('editPickerLabel');
    var search  = document.getElementById('editPickerSearch');
    var native  = document.getElementById('editCompanySelect');
    if (!wrap) return;
    var items   = wrap.querySelectorAll('.cpicker-item');
    var empty   = wrap.querySelector('.cpicker-empty');

    function openPicker() {
        dropdown.classList.add('show');
        trigger.classList.add('open');
        trigger.setAttribute('aria-expanded','true');
        search.value = ''; filterItems('');
        setTimeout(function(){ search.focus(); }, 60);
    }
    function closePicker() {
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
        trigger.setAttribute('aria-expanded','false');
    }
    trigger.addEventListener('click', function() {
        dropdown.classList.contains('show') ? closePicker() : openPicker();
    });
    trigger.addEventListener('keydown', function(e) {
        if (e.key==='Enter'||e.key===' ') { e.preventDefault(); trigger.click(); }
        if (e.key==='Escape') closePicker();
    });
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) closePicker();
    });
    items.forEach(function(item) {
        item.addEventListener('click', function() {
            var id   = this.dataset.id;
            var name = this.dataset.name;
            native.value = id;
            // Dispatch change so existing updateEditUI() fires
            native.dispatchEvent(new Event('change'));
            label.innerHTML = '<span class="cpicker-selected">' + name + '</span><br><span class="cpicker-badge"><i class="bi bi-building me-1"></i>สถานประกอบการที่เลือก</span>';
            items.forEach(function(i){ i.classList.remove('selected'); });
            this.classList.add('selected');
            trigger.classList.add('has-value');
            closePicker();
        });
    });
    search.addEventListener('input', function() { filterItems(this.value.trim()); });
    function filterItems(q) {
        var n = 0;
        items.forEach(function(i) {
            var match = i.dataset.name.toLowerCase().indexOf(q.toLowerCase()) > -1;
            i.style.display = match ? '' : 'none';
            if (match) n++;
        });
        empty.classList.toggle('d-none', n > 0);
    }
});
</script>

<!-- ======== Edit Modal ======== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#4f46e5,#7c3aed); border-radius:1rem 1rem 0 0;">
        <h5 class="modal-title fw-bold text-white" id="editModalLabel">✏️ แก้ไขใบนิเทศก์</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-3">แก้ไขข้อมูลใบนิเทศก์ — เลือกสถานประกอบการใหม่ หรืออัปโหลดไฟล์ใหม่แทนที่ไฟล์เดิม</p>
        
        <!-- Workplace Selection -->
        <div class="mb-3">
            <label class="form-label fw-bold text-dark mb-2"><span class="text-danger">*</span> สถานประกอบการ:</label>
            <!-- Hidden native select —used by existing JS (.value reads) -->
            <select id="editCompanySelect" class="d-none">
                <option value=""></option>
                <?php foreach ($companies as $comp): ?>
                    <option value="<?= (int)$comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Custom picker -->
            <div class="cpicker-wrap" id="editPickerWrap">
                <div class="cpicker-trigger" id="editPickerTrigger" tabindex="0" role="combobox" aria-expanded="false">
                    <div class="cpicker-icon" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);">🏢</div>
                    <div class="cpicker-body">
                        <div class="cpicker-placeholder" id="editPickerLabel">กรุณาเลือกสถานประกอบการ</div>
                    </div>
                    <i class="bi bi-chevron-down cpicker-arrow"></i>
                </div>
                <div class="cpicker-dropdown" id="editPickerDropdown">
                    <div class="cpicker-search-wrap">
                        <input type="text" class="cpicker-search" id="editPickerSearch" placeholder="🔍  ค้นหาชื่อสถานประกอบการ..." autocomplete="off">
                    </div>
                    <div class="cpicker-list" id="editPickerList">
                        <?php foreach ($companies as $comp): ?>
                        <div class="cpicker-item" data-id="<?= (int)$comp['id'] ?>" data-name="<?= htmlspecialchars($comp['name'], ENT_QUOTES) ?>">
                            <span class="ci-dot"></span>
                            <span><?= htmlspecialchars($comp['name']) ?></span>
                            <i class="bi bi-check2 ci-check"></i>
                        </div>
                        <?php endforeach; ?>
                        <div class="cpicker-empty d-none">ไม่พบสถานประกอบการที่ค้นหา 🔍</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dropzone -->
        <div id="editDropzone" class="edit-dropzone mb-3">
          <div style="font-size:2rem; margin-bottom:.4rem;">☁️</div>
          <p class="fw-bold mb-1 small">คลิกเพื่อเลือกไฟล์ หรือลากมาวางตรงนี้</p>
          <p class="text-muted mb-0" style="font-size:.78rem;">PDF, JPG, PNG — รองรับหลายไฟล์ (ระบบจะรวมเป็น PDF เดียว)</p>
          <input type="file" id="editFileInput" class="d-none" accept=".pdf,image/jpeg,image/png" multiple>
        </div>
        <!-- File list -->
        <div id="editFileListWrap" style="display:none; margin-bottom:1rem;">
          <div class="fw-bold small text-dark mb-2">✅ ไฟล์ที่เลือก: <span id="editFileCount" class="text-primary"></span></div>
          <div id="editFileList" class="d-flex flex-column gap-2"></div>
        </div>
        <!-- Progress -->
        <div id="editProgress" style="display:none; margin-bottom:.5rem;">
          <div class="d-flex justify-content-between small text-muted mb-1">
            <span>กำลังประมวลผลและอัปโหลด...</span>
          </div>
          <div class="progress" style="height:8px; border-radius:50rem;">
            <div id="editProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                 style="width:0%; background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn rounded-pill px-5 fw-bold" id="btnEditSubmit" disabled
                style="background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff;">
          <i class="bi bi-send-fill me-1"></i> บันทึกการแก้ไข
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.edit-dropzone {
    border: 2px dashed #c7d2fe;
    border-radius: 1rem;
    background: #f8faff;
    text-align: center;
    padding: 1.75rem 1.25rem;
    cursor: pointer;
    transition: .2s;
}
.edit-dropzone:hover, .edit-dropzone.drag-over {
    border-color: #6366f1;
    background: #eef2ff;
}
.edit-file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    background: #f1f5f9;
    border-radius: .6rem;
    padding: .4rem .75rem;
    font-size: .82rem;
    font-weight: 600;
    color: #334155;
}
.edit-file-name { flex: 1; word-break: break-all; }
</style>

<!-- Hidden delete form -->
<form id="deleteForm" method="POST" action="upload_supervision.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="file_id" id="deleteFileId">
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
