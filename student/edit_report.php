<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

$student_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err = '';

// 1. ดึงข้อมูลเดิมมาแสดง
$stmt = $conn->prepare("SELECT * FROM daily_reports WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("ไม่พบข้อมูลรายงาน หรือคุณไม่มีสิทธิ์แก้ไขรายการนี้");
}

// 2. จัดการบันทึกข้อมูลเมื่อมีการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_work = $_POST['date_work'];
    $details   = $_POST['details'];
    $problems  = $_POST['problems'];
    $solutions = $_POST['solutions'];

    $img1_new = save_report_image($_FILES['image1'] ?? null, $_POST['image1_base64'] ?? '', 'rpt1_');
    if ($img1_new !== null) {
        $img1 = $img1_new;
    }

    $img2_new = save_report_image($_FILES['image2'] ?? null, $_POST['image2_base64'] ?? '', 'rpt2_');
    if ($img2_new !== null) {
        $img2 = $img2_new;
    }

    if (empty($err)) {
        // แก้ไขจุดที่ 1: เพิ่ม status='pending' เพื่อให้ครูเห็นงานที่แก้ไขแล้ว
        $update_sql = "UPDATE daily_reports SET date_work=?, details=?, problems=?, solutions=?, image1=?, image2=?, status='pending' WHERE id=? AND student_id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ssssssii', $date_work, $details, $problems, $solutions, $img1, $img2, $id, $student_id);
        
        if ($update_stmt->execute()) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>window.addEventListener('load',function(){ Swal.fire({ icon:'success', title:'สำเร็จ', text:'แก้ไขและส่งให้ครูตรวจอีกครั้งเรียบร้อยแล้ว', confirmButtonText:'ตกลง', confirmButtonColor:'#6366f1' }).then(function(){ window.location='view_report.php'; }); });</script>";
            exit;
        } else {
            $err = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    }
} // ปิด if POST

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/student-premium.css">
<style>
    .edit-glass {
        background: var(--glass);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid var(--glass-border);
        border-radius: 2rem;
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        padding: 0;
        overflow: hidden;
    }

    .form-control {
        border-radius: 1rem;
        padding: 0.75rem 1.25rem;
        border: 1px solid rgba(0,0,0,0.1);
        background: rgba(255,255,255,0.8);
        transition: all 0.3s ease;
    }

    .form-control:focus {
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        border-color: var(--primary);
    }

    .teacher-comment-box {
        background: rgba(239, 68, 68, 0.05);
        border-left: 5px solid var(--danger);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .image-preview-container {
        position: relative;
        width: 100%;
        height: 200px;
        background: #f1f5f9;
        border-radius: 1.5rem;
        overflow: hidden;
        border: 2px dashed #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .image-preview-container:hover {
        border-color: var(--primary);
    }

    .image-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .preview-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.4);
        color: white;
        padding: 0.5rem;
        text-align: center;
        font-size: 0.75rem;
        opacity: 0;
        transition: 0.3s;
    }

    .image-preview-container:hover .preview-overlay {
        opacity: 1;
    }

    .btn-premium-update,
    .btn-premium-submit {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        border: none;
        border-radius: 1rem;
        padding: 1rem 2rem;
        font-weight: 700;
        color: white;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        transition: all 0.3s ease;
    }

    .btn-premium-update:hover,
    .btn-premium-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 25px rgba(99, 102, 241, 0.3);
        color: white;
    }

    .back-link {
        color: var(--secondary);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: var(--primary);
        transform: translateX(-5px);
    }

    /* PVC AI Tabbed Smart Suggestion Styling */
    .ai-tab-btn {
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 700;
        border-radius: 0.75rem;
    }
    .ai-tab-btn:hover {
        color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
    }
    .ai-tab-btn.active {
        background: white !important;
        color: var(--primary) !important;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.15);
    }
    .keyword-badge {
        background: rgba(99, 102, 241, 0.06);
        border: 1px solid rgba(99, 102, 241, 0.12);
        color: var(--primary-dark);
        font-size: 0.75rem;
        padding: 0.35rem 0.75rem;
        border-radius: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.3s ease;
    }
    .keyword-badge:hover {
        transform: translateY(-2px);
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.25);
    }
    .keyword-badge .raw-word {
        text-decoration: line-through;
        color: #ef4444;
        font-weight: 500;
    }
    .keyword-badge .corrected-word {
        color: #10b981;
        font-weight: 700;
    }
    .smallest {
        font-size: 0.7rem;
    }
    .fs-7 {
        font-size: 0.8rem;
    }
</style>

<div class="dashboard-page">
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <!-- Header Actions -->
                <div class="d-flex justify-content-between align-items-center mb-5 animate-fade-in">
                    <a href="view_report.php" class="btn btn-link text-decoration-none p-0 back-link">
                        <i class="bi bi-arrow-left-circle-fill fs-4 me-2"></i> ยกเลิกการแก้ไข
                    </a>
                    <div class="badge rounded-pill bg-white bg-opacity-50 backdrop-blur text-dark border p-2 px-4 shadow-sm">
                        <i class="bi bi-hash text-primary me-2"></i> รายงานรหัส: #<?= $id ?>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="form-glass animate-slide-up">
                    <div class="row align-items-center mb-5">
                        <div class="col-md-8">
                            <h1 class="fw-extrabold mb-1">แก้ไขรายงานการปฏิบัติงาน</h1>
                            <p class="text-secondary">ปรับปรุงข้อมูลการบันทึกงานประจำวันของคุณตามข้อเสนอแนะ</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="stat-icon-wrap d-inline-flex bg-primary bg-opacity-10 text-primary p-3 rounded-4 shadow-sm">
                                <i class="bi bi-pencil-square fs-2"></i>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($data['teacher_comment'])): ?>
                        <div class="alert alert-warning border-0 shadow-lg mb-4 animate-fade-in" style="border-radius: var(--radius-md); border-left: 6px solid #f59e0b !important;">
                            <div class="d-flex align-items-start p-2">
                                <i class="bi bi-person-badge-fill me-3 fs-3 text-warning"></i>
                                <div>
                                    <div class="fw-bold fs-5 mb-1 text-warning-emphasis">ข้อเสนอแนะจากครูนิเทศก์</div>
                                    <div class="text-secondary italic">"<?= htmlspecialchars($data['teacher_comment']) ?>"</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($data['supervisor_comment'])): ?>
                        <div class="alert border-0 shadow-lg mb-5 animate-fade-in" style="border-radius: var(--radius-md); border-left: 6px solid #0ea5e9 !important; background: #f0f9ff;">
                            <div class="d-flex align-items-start p-2">
                                <i class="bi bi-person-workspace me-3 fs-3" style="color:#0ea5e9;"></i>
                                <div>
                                    <div class="fw-bold fs-5 mb-1" style="color:#0369a1;">Feedback จากผู้ดูแลการฝึกงาน (Supervisor)</div>
                                    <div class="text-secondary italic">"<?= htmlspecialchars($data['supervisor_comment']) ?>"</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($err): ?>
                        <div class="alert alert-danger border-0 shadow-lg mb-5 animate-fade-in" style="border-radius: var(--radius-md);">
                            <div class="d-flex align-items-center p-2">
                                <i class="bi bi-exclamation-circle-fill me-3 fs-3"></i>
                                <div class="fw-bold"><?= $err ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" id="editReportForm">
                        <div class="row g-5">
                            <!-- Left Column: Details -->
                            <div class="col-lg-7">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase tracking-wider text-primary">
                                        <i class="bi bi-calendar-check me-1"></i> วันที่ปฏิบัติงาน
                                    </label>
                                    <input type="date" name="date_work" class="form-control form-control-lg shadow-sm" value="<?= $data['date_work'] ?>" required>
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold small text-uppercase tracking-wider text-primary mb-0">
                                            <i class="bi bi-card-text me-1"></i> รายละเอียดงานที่ทำ
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold shadow-sm" id="btnAiPolish" style="font-size: 0.75rem; display: none !important;">
                                            <i class="bi bi-stars text-warning animate-beat"></i> ขัดเกลาภาษาด้วย AI
                                        </button>
                                    </div>
                                    <textarea name="details" id="detailsTextarea" class="form-control shadow-sm" rows="10" 
                                              placeholder="อธิบายรายละเอียดงานที่ทำในวันนี้ เช่น ขั้นตอนการทำงาน เครื่องมือที่ใช้ และผลที่ได้รับ&#10;&#10;ตัวอย่าง: ทำการซ่อมบำรุงเครื่องคอมพิวเตอร์ที่อาการเปิดไม่ติด ตรวจสอบพบว่า RAM สกปรก จึงทำความสะอาดและติดตั้งกลับเข้าไปใหม่ ผลคือเครื่องกลับมาใช้งานได้ปกติ" required><?= htmlspecialchars($data['details']) ?></textarea>
                                    
                                    <!-- AI Suggestion Container (Upgraded Glassmorphism) -->
                                    <div class="card border-0 shadow mt-4 d-none animate-slide-up" id="aiSuggestionBox" style="background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-radius: 1.8rem; border: 1px solid rgba(99, 102, 241, 0.25); overflow: hidden;">
                                        <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-primary bg-opacity-10 text-primary p-2 px-3 rounded-pill fw-bold" style="font-size: 0.85rem;">
                                                    <i class="bi bi-stars text-warning animate-beat me-1"></i> PVC AI Smart Suggestion
                                                </span>
                                                <span class="text-muted small fs-7" id="aiStatsBadge"><i class="bi bi-cpu-fill"></i> ระบบสะกดคำสะท้อนทักษะ</span>
                                            </div>
                                            
                                            <!-- Detected Keywords Section -->
                                            <div class="mb-3" id="aiDetectedSection" style="display:none;">
                                                <div class="text-muted smallest text-uppercase tracking-wider fw-bold mb-2"><i class="bi bi-search me-1 text-primary"></i> คำศัพท์ที่ระบบช่วยตรวจจับและแปลง:</div>
                                                <div id="aiDetectedKeywords" class="d-flex flex-wrap gap-2"></div>
                                            </div>
                                            
                                            <!-- Premium Tabs -->
                                            <div class="ai-tabs-container p-1 bg-light rounded-4 d-flex gap-1" style="border: 1px solid rgba(0,0,0,0.05);">
                                                <button type="button" class="btn btn-sm w-100 py-2 rounded-3 fw-bold ai-tab-btn active" data-style="formal" style="transition: all 0.3s;">
                                                    👔 เป็นทางการ
                                                </button>
                                                <button type="button" class="btn btn-sm w-100 py-2 rounded-3 fw-bold ai-tab-btn" data-style="concise" style="transition: all 0.3s;">
                                                    ⚡ แบบกระชับ
                                                </button>
                                                <button type="button" class="btn btn-sm w-100 py-2 rounded-3 fw-bold ai-tab-btn" data-style="reflective" style="transition: all 0.3s;">
                                                    💡 เน้นเรียนรู้
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="card-body p-4 pt-3">
                                            <div class="bg-white bg-opacity-50 p-3 rounded-4 border border-white mb-4 position-relative" style="min-height: 100px;">
                                                <p class="card-text text-dark fw-medium mb-0" id="aiPolishedText" style="line-height: 1.7; font-size: 1.05rem; text-align: justify; transition: opacity 0.2s ease-in-out;"></p>
                                            </div>
                                            
                                            <div class="d-flex gap-3">
                                                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold w-100 shadow-sm" id="btnAcceptAi">
                                                    <i class="bi bi-check-circle-fill me-1"></i> ยืนยันใช้ข้อความสไตล์นี้
                                                </button>
                                                <button type="button" class="btn btn-light rounded-pill px-3 py-2 fw-bold text-danger border w-auto" id="btnRejectAi">
                                                    <i class="bi bi-x-circle-fill me-1"></i> ยกเลิก
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-uppercase tracking-wider text-warning">
                                            <i class="bi bi-shield-exclamation me-1"></i> ปัญหาที่พบ
                                        </label>
                                        <textarea name="problems" class="form-control shadow-sm" rows="3" placeholder="ระบุอุปสรรคที่เกิดขึ้น..."><?= htmlspecialchars($data['problems']) ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-uppercase tracking-wider text-success">
                                            <i class="bi bi-shield-check me-1"></i> การแก้ไขปัญหา
                                        </label>
                                        <textarea name="solutions" class="form-control shadow-sm" rows="3" placeholder="วิธีที่คุณจัดการกับปัญหา..."><?= htmlspecialchars($data['solutions']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Images & Submission -->
                            <div class="col-lg-5">
                                <div class="mb-5">
                                    <label class="form-label fw-bold small text-uppercase tracking-wider text-primary mb-3">
                                        <i class="bi bi-camera-fill me-1"></i> ภาพประกอบการทำงาน
                                    </label>
                                    
                                    <div class="mb-4">
                                        <div class="image-upload-card shadow-sm" onclick="document.getElementById('img1').click()">
                                            <div class="upload-preview-container mb-3 position-relative animate-fade-in" style="height: 160px; overflow: hidden; border-radius: 1rem; background: rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.05);">
                                                <img src="<?= $data['image1'] ? '../uploads/images/'.$data['image1'] : "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='1' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'/><circle cx='8.5' cy='8.5' r='1.5'/><polyline points='21 15 16 10 5 21'/></svg>" ?>" id="view1" class="img-fluid rounded-3" style="height: 100%; width: 100%; <?= $data['image1'] ? 'object-fit: cover; padding: 0;' : 'object-fit: contain; padding: 1.5rem;' ?> opacity: 0.9; transition: all 0.3s ease;">
                                                <div class="position-absolute top-50 start-50 translate-middle d-none" id="loader-1">
                                                    <div class="spinner-border text-primary" role="status"></div>
                                                </div>
                                            </div>
                                            <div class="upload-content text-center py-1">
                                                <h6 class="fw-bold mb-1 small">ภาพที่ 1: สถานที่/ชิ้นงาน</h6>
                                                <p class="text-muted smallest mb-0" id="hint-1"><?= $data['image1'] ? 'แตะเพื่อเปลี่ยนรูปภาพ' : 'แตะเพื่ออัปโหลดรูปภาพ' ?></p>
                                            </div>
                                            <input type="file" id="img1" name="image1" class="d-none" accept="image/*" onchange="handleImageSelect(this, 'view1', 'loader-1', 'hint-1', 'file-name-1')" onclick="event.stopPropagation()">
                                            <input type="hidden" id="img1_base64" name="image1_base64">
                                            <div id="file-name-1" class="mt-2 small text-primary fw-bold"><?= $data['image1'] ? '<i class="bi bi-image text-success me-1"></i> มีรูปภาพเดิมอยู่แล้ว' : '' ?></div>
                                        </div>
                                    </div>
 
                                    <div class="mb-4">
                                        <div class="image-upload-card shadow-sm" onclick="document.getElementById('img2').click()">
                                            <div class="upload-preview-container mb-3 position-relative animate-fade-in" style="height: 160px; overflow: hidden; border-radius: 1rem; background: rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.05);">
                                                <img src="<?= $data['image2'] ? '../uploads/images/'.$data['image2'] : "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='1' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>" ?>" id="view2" class="img-fluid rounded-3" style="height: 100%; width: 100%; <?= $data['image2'] ? 'object-fit: cover; padding: 0;' : 'object-fit: contain; padding: 1.5rem;' ?> opacity: 0.9; transition: all 0.3s ease;">
                                                <div class="position-absolute top-50 start-50 translate-middle d-none" id="loader-2">
                                                    <div class="spinner-border text-primary" role="status"></div>
                                                </div>
                                            </div>
                                            <div class="upload-content text-center py-1">
                                                <h6 class="fw-bold mb-1 small">ภาพที่ 2: ร่วมกับทีมงาน/ครูฝึก</h6>
                                                <p class="text-muted smallest mb-0" id="hint-2"><?= $data['image2'] ? 'แตะเพื่อเปลี่ยนรูปภาพ' : 'แตะเพื่ออัปโหลดรูปภาพ' ?></p>
                                            </div>
                                            <input type="file" id="img2" name="image2" class="d-none" accept="image/*" onchange="handleImageSelect(this, 'view2', 'loader-2', 'hint-2', 'file-name-2')" onclick="event.stopPropagation()">
                                            <input type="hidden" id="img2_base64" name="image2_base64">
                                            <div id="file-name-2" class="mt-2 small text-primary fw-bold"><?= $data['image2'] ? '<i class="bi bi-image text-success me-1"></i> มีรูปภาพเดิมอยู่แล้ว' : '' ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="sticky-top" style="top: 2rem;">
                                    <button type="submit" class="btn btn-premium-submit w-100 py-3 fs-5 mb-4 shadow-lg">
                                        <i class="bi bi-send-check-fill me-2"></i> บันทึกและส่งตรวจใหม่
                                    </button>
                                    
                                    <div class="p-4 rounded-4 bg-white bg-opacity-50 border border-white border-opacity-30 backdrop-blur">
                                        <h6 class="fw-bold small text-uppercase mb-3">สถานะหลังการแก้ไข</h6>
                                        <p class="small text-muted mb-0">
                                            เมื่อกดบันทึก ข้อมูลของคุณจะถูกเปลี่ยนสถานะเป็น <span class="badge bg-warning bg-opacity-20 text-warning-emphasis">รอตรวจ</span> อีกครั้งเพื่อให้อาจารย์ตรวจสอบความถูกต้อง
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dependency: HEIC/HEIF Image Converter CDN -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<script>
// client-side Image Processing & Resizing Engine
async function processAndCompressImage(file, maxWidth = 1600, maxHeight = 1600, quality = 0.85) {
    const ext = file.name.split('.').pop().toLowerCase();
    let targetFile = file;
    
    // Apple HEIC / HEIF dynamic conversion
    if (ext === 'heic' || ext === 'heif') {
        if (typeof heic2any === 'function') {
            try {
                const convertedBlob = await heic2any({
                    blob: file,
                    toType: 'image/jpeg',
                    quality: quality
                });
                const newName = file.name.substring(0, file.name.lastIndexOf('.')) + '.jpg';
                targetFile = new File([convertedBlob], newName, { type: 'image/jpeg' });
            } catch (err) {
                console.error("HEIC conversion failed:", err);
                throw new Error("ไม่สามารถแปลงไฟล์ HEIC ได้ในขณะนี้");
            }
        } else {
            throw new Error("ระบบต้องการอินเทอร์เน็ตในการอัปโหลดภาพประเภท HEIC/HEIF (โปรดต่อเน็ต หรือแปลงเป็น JPG/PNG ก่อน)");
        }
    }
    
    // Resize & Compress via Canvas
    return new Promise((resolve) => {
        // Skip canvas scaling for animated GIFs to preserve motion frames
        if (ext === 'gif' || ext === 'svg') {
            resolve(targetFile);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                let width = img.width;
                let height = img.height;
                
                // Only scale down if width or height exceeds maximum
                if (width > maxWidth || height > maxHeight) {
                    if (width > height) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    } else {
                        width = Math.round((width * maxHeight) / height);
                        height = maxHeight;
                    }
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Keep PNG transparent, otherwise output standard JPEG
                const outputType = (ext === 'png') ? 'image/png' : 'image/jpeg';
                
                canvas.toBlob((blob) => {
                    if (!blob) {
                        resolve(targetFile);
                        return;
                    }
                    const compressedFile = new File([blob], targetFile.name, {
                        type: outputType,
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, outputType, quality);
            };
            img.onerror = function() {
                resolve(targetFile);
            };
            img.src = e.target.result;
        };
        reader.onerror = function() {
            resolve(targetFile);
        };
        reader.readAsDataURL(targetFile);
    });
}

// Visual Preview and Compression Handler
async function handleImageSelect(input, viewId, loaderId, hintId, fileNameId) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    
    // Extended validation
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'];
    const fileExt = file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExt)) {
        alert('ประเภทไฟล์รูปภาพไม่ถูกต้อง! กรุณาเลือกไฟล์ประเภท: ' + allowedExtensions.join(', ').toUpperCase());
        input.value = '';
        return;
    }
    
    const viewImg = document.getElementById(viewId);
    const loader = document.getElementById(loaderId);
    const hint = document.getElementById(hintId);
    const fileNameDiv = document.getElementById(fileNameId);
    
    // Loading State
    loader.classList.remove('d-none');
    viewImg.style.opacity = '0.3';
    
    // Instant preview for browser-supported images
    if (fileExt !== 'heic' && fileExt !== 'heif') {
        const reader = new FileReader();
        reader.onload = function(e) {
            viewImg.src = e.target.result;
            viewImg.style.objectFit = 'cover';
            viewImg.style.padding = '0';
            viewImg.style.opacity = '1';
        };
        reader.readAsDataURL(file);
    }
    
    try {
        const compressedFile = await processAndCompressImage(file);
        
        // Update input file with compressed one using DataTransfer API safely
        try {
            if (window.DataTransfer) {
                const container = new DataTransfer();
                container.items.add(compressedFile);
                input.files = container.files;
            }
        } catch (dtErr) {
            console.warn("DataTransfer not supported on this browser:", dtErr);
        }
        
        // Also write Base64 data to hidden fallback input
        const base64Input = document.getElementById(input.id + '_base64');
        if (base64Input) {
            const b64Reader = new FileReader();
            b64Reader.onload = function(e) {
                base64Input.value = e.target.result;
            };
            b64Reader.readAsDataURL(compressedFile);
        }
        
        // Show status feedback
        const originalKB = (file.size / 1024).toFixed(1);
        const compressedKB = (compressedFile.size / 1024).toFixed(1);
        
        fileNameDiv.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ${compressedFile.name}`;
        hint.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success p-1 px-2 rounded"><i class="bi bi-shield-check"></i> บีบอัดแล้ว: ${originalKB}KB ➔ ${compressedKB}KB</span>`;
        
        // HEIC conversion finished, preview converted image
        if (fileExt === 'heic' || fileExt === 'heif') {
            const reader = new FileReader();
            reader.onload = function(e) {
                viewImg.src = e.target.result;
                viewImg.style.objectFit = 'cover';
                viewImg.style.padding = '0';
                viewImg.style.opacity = '1';
            };
            reader.readAsDataURL(compressedFile);
        }
    } catch (err) {
        console.error(err);
        alert('แจ้งเตือนประมวลผลรูปภาพ: ' + err.message);
        fileNameDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> ${file.name}`;
        hint.innerHTML = 'แตะเพื่อเปลี่ยนรูปภาพ';
        
        // If file type is standard, at least keep original preview
        if (fileExt !== 'heic' && fileExt !== 'heif') {
            viewImg.style.opacity = '1';
        }
    } finally {
        loader.classList.add('d-none');
    }
}

    // 1. AI-Powered Smart Suggestion (Upgraded with 3 Styles and Fuzzy Highlights)
    let aiTones = {};
    let selectedToneStyle = 'formal';

    document.getElementById('btnAiPolish').addEventListener('click', function() {
        const details = document.getElementById('detailsTextarea').value.trim();
        if (!details) {
            alert('กรุณากรอกคีย์เวิร์ดรายละเอียดงานสั้นๆ ก่อนกดปุ่มขัดเกลาด้วย AI');
            return;
        }
        
        const originalText = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> กำลังวิเคราะห์...';
        this.disabled = true;

        fetch('api_ai_polish.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ text: details })
        })
        .then(res => res.json())
        .then(data => {
            this.innerHTML = originalText;
            this.disabled = false;
            
            if (data.success && data.tones) {
                aiTones = data.tones;
                
                // Render detected keywords visualization
                const detectedContainer = document.getElementById('aiDetectedKeywords');
                const section = document.getElementById('aiDetectedSection');
                detectedContainer.innerHTML = '';
                
                if (data.detected && data.detected.length > 0) {
                    data.detected.forEach(item => {
                        const badge = document.createElement('span');
                        badge.className = 'keyword-badge shadow-sm animate-fade-in';
                        if (item.exact) {
                            badge.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> ตรวจพบ: <strong>${item.corrected}</strong>`;
                        } else {
                            badge.innerHTML = `<i class="bi bi-magic text-warning animate-beat"></i> <span class="raw-word">${item.raw}</span> ➔ <span class="corrected-word">${item.corrected}</span> <span class="badge bg-secondary smallest">${item.similarity}%</span>`;
                        }
                        detectedContainer.appendChild(badge);
                    });
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
                
                // Set default selected style and display text
                selectedToneStyle = 'formal';
                document.querySelectorAll('.ai-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.getAttribute('data-style') === 'formal') {
                        btn.classList.add('active');
                    }
                });
                
                updatePolishedPreviewText();
                document.getElementById('aiSuggestionBox').classList.remove('d-none');
                
                // Smooth scroll to suggestion box
                document.getElementById('aiSuggestionBox').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                alert('ไม่พบคำแนะนำการขัดเกลาสำหรับคีย์เวิร์ดนี้ โปรดเขียนรายละเอียดเพิ่มเติม');
            }
        })
        .catch(err => {
            this.innerHTML = originalText;
            this.disabled = false;
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบ AI');
        });
    });

    function updatePolishedPreviewText() {
        const textEl = document.getElementById('aiPolishedText');
        textEl.style.opacity = 0;
        setTimeout(() => {
            textEl.innerText = aiTones[selectedToneStyle] || '';
            textEl.style.opacity = 1;
        }, 150);
    }

    // Bind click listener to the dynamic tone tabs
    document.querySelectorAll('.ai-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.ai-tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedToneStyle = this.getAttribute('data-style');
            updatePolishedPreviewText();
        });
    });

    document.getElementById('btnAcceptAi').addEventListener('click', function() {
        const polishedText = aiTones[selectedToneStyle];
        if (polishedText) {
            document.getElementById('detailsTextarea').value = polishedText;
            document.getElementById('aiSuggestionBox').classList.add('d-none');
        }
    });

    document.getElementById('btnRejectAi').addEventListener('click', function() {
        document.getElementById('aiSuggestionBox').classList.add('d-none');
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>