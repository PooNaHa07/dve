<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

// 🛡️ BACKUP ENGINE: Single-click Database Export
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    // Log the audit action before generating download
    log_audit('BACKUP_DB', 'แอดมินทำการดาวน์โหลดไฟล์สำรองข้อมูล SQL');
    
    ini_set('display_errors', '0');
    $filename = 'dve_backup_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "-- 📦 DVE SYSTEM SQL BACKUP\n";
    echo "-- วันที่สร้าง: " . date('Y-m-d H:i:s') . "\n";
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET time_zone = \"+00:00\";\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    $tables = [];
    $t_res = $conn->query("SHOW TABLES");
    while($t_row = $t_res->fetch_row()) {
        $tables[] = $t_row[0];
    }
    
    foreach($tables as $table) {
        // Drop if exist & Recreate
        echo "DROP TABLE IF EXISTS `$table`;\n";
        $c_res = $conn->query("SHOW CREATE TABLE `$table`");
        $c_row = $c_res->fetch_row();
        echo $c_row[1] . ";\n\n";
        
        // Export rows
        $d_res = $conn->query("SELECT * FROM `$table`");
        $field_cnt = $d_res->field_count;
        
        while($d_row = $d_res->fetch_row()) {
            echo "INSERT INTO `$table` VALUES(";
            for($j=0; $j<$field_cnt; $j++) {
                if (isset($d_row[$j])) {
                    $val = str_replace("\n", "\\n", $conn->real_escape_string($d_row[$j]));
                    echo "'" . $val . "'";
                } else {
                    echo "NULL";
                }
                if ($j < ($field_cnt - 1)) echo ",";
            }
            echo ");\n";
        }
        echo "\n\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// 🛠️ REPAIR ENGINE: Repair Student Classrooms (AJAX endpoint)
if (isset($_GET['action']) && $_GET['action'] === 'repair_classrooms') {
    log_audit('REPAIR_CLASSROOMS', 'แอดมินรันการซ่อมแซมข้อมูลห้องเรียนนักเรียนทุกคน');
    
    function localResolveClassroom($conn, $student_level, $affiliation) {
        $lvl = preg_replace('/([ปวชส]+)\.\s+/', '$1.', trim($student_level));
        $affName = trim($affiliation);
        
        if (empty($affName)) {
            return null;
        }

        $cleanAff = preg_replace('/[\(\)\+\-\[\]\{\}\.,\/_]/u', ' ', $affName);
        $rawTokens = preg_split('/\s+/u', $cleanAff);
        $tokens = [];
        $stopWords = ['ห้อง', 'สาขาวิชา', 'สาขา'];
        
        foreach ($rawTokens as $t) {
            $t = trim($t);
            if ($t !== '' && !in_array($t, $stopWords)) {
                $tokens[] = $t;
            }
        }

        static $allClassrooms = null;
        if ($allClassrooms === null) {
            $allClassrooms = [];
            $res = $conn->query("SELECT id, class_name FROM classrooms");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $allClassrooms[] = $row;
                }
            }
        }

        foreach ($allClassrooms as $class) {
            $className = $class['class_name'];
            if (!empty($lvl)) {
                if (mb_strpos($className, $lvl) === false) {
                    continue;
                }
            }
            $allMatched = true;
            foreach ($tokens as $token) {
                if (mb_strpos($className, $token) === false) {
                    $allMatched = false;
                    break;
                }
            }
            if ($allMatched) {
                return $class;
            }
        }

        if (empty($lvl)) {
            foreach ($allClassrooms as $class) {
                $className = $class['class_name'];
                $allMatched = true;
                foreach ($tokens as $token) {
                    if (mb_strpos($className, $token) === false) {
                        $allMatched = false;
                        break;
                    }
                }
                if ($allMatched) {
                    return $class;
                }
            }
        }

        return null;
    }

    $students_res = $conn->query("SELECT id, fullname, username, student_code, student_level, affiliation, classroom_id FROM users WHERE role = 'student'");
    $total_processed = 0;
    $updated_count = 0;
    $no_change = 0;
    $unresolved = [];

    if ($students_res) {
        while ($student = $students_res->fetch_assoc()) {
            $total_processed++;
            $resolvedClass = localResolveClassroom($conn, $student['student_level'], $student['affiliation']);
            
            if ($resolvedClass) {
                $new_id = (int)$resolvedClass['id'];
                if ($student['classroom_id'] != $new_id) {
                    $up_q = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ?");
                    $up_q->bind_param("ii", $new_id, $student['id']);
                    $up_q->execute();
                    $up_q->close();
                    $updated_count++;
                } else {
                    $no_change++;
                }
            } else {
                $unresolved[] = [
                    'fullname' => $student['fullname'],
                    'student_code' => !empty($student['student_code']) ? $student['student_code'] : $student['username'],
                    'level' => $student['student_level'],
                    'affiliation' => $student['affiliation']
                ];
            }
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'total' => $total_processed,
        'updated' => $updated_count,
        'no_change' => $no_change,
        'unresolved' => $unresolved
    ]);
    exit;
}

$success_msg = '';
$error_msg = '';

// 📣 1. Handle Broadcast Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast_msg'])) {
    $target = $_POST['target_role']; // student, teacher, or all
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $sql = "SELECT id FROM users";
        if($target !== 'all') {
            $sql .= " WHERE role = '" . $conn->real_escape_string($target) . "'";
        }
        $res = $conn->query($sql);
        
        if ($res && $res->num_rows > 0) {
            $ins = $conn->prepare("INSERT INTO contact_messages (student_id, message, status, created_at) VALUES (?, ?, 'read', NOW())");
            $count = 0;
            while ($u = $res->fetch_assoc()) {
                $formatted_msg = "📢 [ประกาศจากระบบ]: " . $message;
                $ins->bind_param("is", $u['id'], $formatted_msg);
                $ins->execute();
                $count++;
            }
            $ins->close();
            
            // Log to audit
            log_audit('BROADCAST', 'ส่งประกาศไปยังกลุ่ม ' . $target . ' จำนวน ' . $count . ' ราย');
            
            $success_msg = "📣 ส่งประกาศสำเร็จไปยังผู้ใช้จำนวน " . $count . " ราย";
        } else {
            $error_msg = "ไม่พบกลุ่มเป้าหมายที่เลือก";
        }
    } else {
        $error_msg = "กรุณากรอกข้อความประกาศ";
    }
}

// 🔍 2. Fetch Real-time Activity Aggregate via UNIONS (EXPANDED)
// Combines user signups, reports, messages, AND system audits into a chronological stream.
$activities = [];
$act_sql = "(SELECT 'user' as type, fullname as detail, created_at FROM users ORDER BY created_at DESC LIMIT 20)
            UNION ALL
            (SELECT 'report' as type, CONCAT('ส่งรายงานวันที่ ', date_work) as detail, created_at FROM daily_reports ORDER BY created_at DESC LIMIT 20)
            UNION ALL
            (SELECT 'message' as type, LEFT(message, 50) as detail, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 20)
            UNION ALL
            (SELECT 'audit' as type, CONCAT('[', action_type, '] ', details) as detail, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 20)
            ORDER BY created_at DESC LIMIT 25";

$act_res = $conn->query($act_sql);
if($act_res) {
    while($row = $act_res->fetch_assoc()) {
        $activities[] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .timeline-item {
        position: relative;
        padding-left: 2rem;
        padding-bottom: 1.5rem;
        border-left: 2px solid #f1f5f9;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #cbd5e1;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #f1f5f9;
    }
    .timeline-item.type-user::before { background: #10b981; }
    .timeline-item.type-report::before { background: #4f46e5; }
    .timeline-item.type-message::before { background: #f59e0b; }
    .timeline-item.type-audit::before { background: #ef4444; } /* Audit Red */
    .timeline-item:last-child {
        border-left: 2px solid transparent;
    }
</style>

<div class="container admin-content-wrapper">
    <div class="admin-header-section">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-toolbox"></i>
                เครื่องมือ & กิจกรรมระบบ
            </h2>
            <p class="text-white text-opacity-75 mb-0 mt-1 small">ดูแลการประกาศข้อมูล, สำรองฐานข้อมูล และตรวจสอบประวัติระบบ</p>
        </div>
        <div class="d-flex gap-2">
            <!-- 🛠️ REPAIR CLASSROOMS BUTTON -->
            <a href="repair_classroom_mapping.php" class="btn btn-primary fw-bold shadow-sm" style="border-radius: 2rem; background: #6366f1; border-color: #6366f1;">
                <i class="fas fa-tools me-1"></i> ซ่อมห้องเรียนเด็ก
            </a>
            <!-- 🛢️ STORAGE MANAGER BUTTON -->
            <a href="storage_manager.php" class="btn btn-info fw-bold shadow-sm text-white" style="border-radius: 2rem; background: #0284c7; border-color: #0284c7;">
                <i class="fas fa-hdd me-1"></i> พื้นที่ดิสก์
            </a>
            <!-- 🔄 DB SYNC BUTTON -->
            <a href="#" onclick="confirmDbSync(event)" class="btn btn-success fw-bold shadow-sm" style="border-radius: 2rem;">
                <i class="fas fa-sync-alt me-1"></i> ปรับโครงสร้าง DB
            </a>
            <!-- 📦 DATABASE BACKUP BUTTON -->
            <a href="system_tools.php?action=download_backup" class="btn btn-warning fw-bold shadow-sm" style="border-radius: 2rem;">
                <i class="fas fa-download me-1"></i> Backup SQL
            </a>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <?php if($success_msg): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center" style="border-radius: 0.75rem;">
        <i class="fas fa-check-circle fs-4 me-3"></i>
        <div><?php echo $success_msg; ?></div>
    </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center" style="border-radius: 0.75rem;">
        <i class="fas fa-exclamation-circle fs-4 me-3"></i>
        <div><?php echo $error_msg; ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Broadcast Interface -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm admin-card" style="height:100%;">
                <div class="card-header border-0 bg-white pt-4 pb-2">
                    <h5 class="fw-bold text-dark mb-1"><i class="fas fa-bullhorn text-primary me-2"></i>ส่งข้อความประกาศระบบ</h5>
                    <p class="text-muted small mb-0">กระจายข้อความสำคัญถึงกลุ่มเป้าหมายพร้อมกันทันที</p>
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="return confirmBroadcast()">
                        <div class="mb-3">
                            <label class="admin-form-label">เลือกกลุ่มเป้าหมาย</label>
                            <select name="target_role" class="form-select admin-form-control" required>
                                <option value="student">👨‍🎓 นักเรียนทุกคน</option>
                                <option value="teacher">👨‍🏫 ครูนิเทศก์ทุกคน</option>
                                <option value="all">🌐 ผู้ใช้ทุกคนในระบบ</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="admin-form-label">ข้อความประกาศ</label>
                            <textarea name="message" class="form-control admin-form-control" rows="5" placeholder="ระบุข้อความที่ต้องการสื่อสาร..." required></textarea>
                        </div>
                        <button type="submit" name="broadcast_msg" class="btn-admin-primary w-100 py-2">
                            <i class="fas fa-paper-plane me-2"></i>ส่งประกาศเดี๋ยวนี้
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Real-time Activity Auditing Log -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm admin-card" style="height:100%;">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="fas fa-history text-indigo me-2" style="color:#6366f1;"></i>Log กิจกรรมสดของระบบ</h5>
                        <p class="text-muted small mb-0">ประวัติการทำรายการล่าสุดตามลำดับเวลา</p>
                    </div>
                    <a href="system_tools.php" class="btn btn-sm btn-light rounded-pill"><i class="fas fa-sync-alt"></i> รีเฟรช</a>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if(empty($activities)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-stream fs-2 mb-3 opacity-50"></i>
                            <p>ยังไม่มีกิจกรรมใดๆ บันทึกในฐานข้อมูล</p>
                        </div>
                    <?php else: ?>
                        <div class="ps-3 pt-2">
                            <?php foreach($activities as $act): 
                                $typeLabel = ''; $iconClass = ''; $colorClass = '';
                                switch($act['type']) {
                                    case 'user': $typeLabel = 'สร้างบัญชีใหม่'; $iconClass = 'fa-user-plus'; $colorClass = 'text-success'; break;
                                    case 'report': $typeLabel = 'นักเรียนส่งรายงาน'; $iconClass = 'fa-file-signature'; $colorClass = 'text-primary'; break;
                                    case 'message': $typeLabel = 'มีการติดต่อ'; $iconClass = 'fa-comment-dots'; $colorClass = 'text-warning'; break;
                                    case 'audit': $typeLabel = 'กิจกรรมระบบ'; $iconClass = 'fa-shield-alt'; $colorClass = 'text-danger'; break;
                                }
                                $formattedDate = date('d/m/Y H:i', strtotime($act['created_at']));
                            ?>
                            <div class="timeline-item type-<?php echo $act['type']; ?>">
                                <div class="d-flex justify-content-between">
                                    <strong class="small <?php echo $colorClass; ?>"><i class="fas <?php echo $iconClass; ?> me-1"></i> <?php echo $typeLabel; ?></strong>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?php echo $formattedDate; ?> น.</span>
                                </div>
                                <p class="mb-0 text-dark fw-medium mt-1" style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($act['detail']); ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmBroadcast() {
    return confirm("❗ คุณกำลังจะส่งข้อความประกาศนี้ไปยังผู้ใช้ทั้งหมดตามกลุ่มที่เลือก ยืนยันดำเนินการหรือไม่?");
}

function confirmDbSync(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ปรับโครงสร้างฐานข้อมูล?',
        text: 'ระบบจะเปรียบเทียบและสร้างตาราง/ฟิลด์ที่ขาดหายไปให้อัตโนมัติ โดยไม่แตะต้องข้อมูลเดิม ปลอดภัย 100%',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-play me-1"></i> เริ่มการปรับปรุง',
        cancelButtonText: 'ยกเลิก',
        background: '#fff',
        backdrop: `rgba(15, 23, 42, 0.6)`
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../db/sync_db_structure.php';
        }
    });
}

function runClassroomRepair() {
    Swal.fire({
        title: 'ยืนยันซ่อมห้องเรียนนักเรียน?',
        text: 'ระบบจะสแกนข้อมูลแผนก/ระดับของเด็กทุกคนเพื่อจับคู่ห้องเรียนใหม่ที่ถูกต้องให้อัตโนมัติ เพื่อแก้ไขปัญหาเด็กไม่ขึ้นในห้องของครู',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'เริ่มสแกนและซ่อมแซม',
        cancelButtonText: 'ยกเลิก',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('system_tools.php?action=repair_classrooms')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('การเชื่อมต่อล้มเหลว');
                    }
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error}`);
                });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            const data = result.value;
            let unresolvedHtml = '';
            
            if (data.unresolved && data.unresolved.length > 0) {
                unresolvedHtml = `
                    <div class="mt-3 text-start">
                        <h6 class="fw-bold text-danger"><i class="fas fa-exclamation-triangle me-1"></i> รายชื่อเด็กที่ไม่พบห้องที่ตรงกัน (${data.unresolved.length} คน):</h6>
                        <div style="max-height: 180px; overflow-y: auto; font-size: 0.8rem; background: #f8fafc; border-radius: 6px; padding: 10px; border: 1px solid #e2e8f0;">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr style="border-bottom: 1px solid #cbd5e1;">
                                        <th>รหัสนักศึกษา</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>ระดับ</th>
                                        <th>สังกัด/แผนก</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.unresolved.map(s => `
                                        <tr>
                                            <td class="text-secondary">${s.student_code}</td>
                                            <td class="fw-medium">${s.fullname}</td>
                                            <td><span class="badge bg-secondary">${s.level}</span></td>
                                            <td class="text-wrap" style="max-width: 150px;">${s.affiliation}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i> กรุณาเพิ่มห้องเรียนให้ตรงกับแผนกหรือระดับข้างต้น จากนั้นกดปุ่มซ่อมแซมใหม่อีกครั้ง</p>
                    </div>
                `;
            }

            Swal.fire({
                title: '🎉 ซ่อมแซมห้องเรียนสำเร็จ!',
                html: `
                    <div style="font-size: 0.95rem;">
                        <p class="mb-2">ระบบประมวลผลเสร็จสิ้นเรียบร้อยแล้ว:</p>
                        <div class="d-flex justify-content-around my-3 py-2 bg-light rounded">
                            <div class="text-center">
                                <span class="d-block fs-3 fw-bold text-primary">${data.total}</span>
                                <span class="text-muted small">สแกนทั้งหมด</span>
                            </div>
                            <div class="text-center">
                                <span class="d-block fs-3 fw-bold text-success">${data.updated}</span>
                                <span class="text-muted small">อัพเดทสำเร็จ</span>
                            </div>
                            <div class="text-center">
                                <span class="d-block fs-3 fw-bold text-secondary">${data.no_change}</span>
                                <span class="text-muted small">ถูกต้องอยู่แล้ว</span>
                            </div>
                        </div>
                        ${unresolvedHtml}
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#10b981',
                width: '600px'
            }).then(() => {
                window.location.reload();
            });
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
