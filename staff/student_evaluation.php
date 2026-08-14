<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff', 'admin']);

if (!isset($_GET['std_id']) || empty($_GET['std_id'])) {
    header('Location: assessment_list.php');
    exit;
}

$std_id = (int) $_GET['std_id'];
$u = current_user();

$std_res = $conn->query("SELECT u.*, c.name as company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.id = " . $std_id);
$std = $std_res ? $std_res->fetch_assoc() : null;
if (!$std) {
    header('Location: assessment_list.php');
    exit;
}

// ดึงข้อมูลเกรดฝั่งเจ้าหน้าที่
$as_res = $conn->query("SELECT * FROM staff_evaluations WHERE student_id = " . $std_id);
$as = $as_res ? $as_res->fetch_assoc() : null;

if (isset($_POST['save_grade'])) {
    $s_work = (int) ($_POST['score_work'] ?? 0);
    $s_report = (int) ($_POST['score_report'] ?? 0);
    $s_behavior = (int) ($_POST['score_behavior'] ?? 0);
    $total = $s_work + $s_report + $s_behavior;
    $term = trim($_POST['term'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($total >= 80) $grade = '4';
    elseif ($total >= 75) $grade = '3.5';
    elseif ($total >= 70) $grade = '3';
    elseif ($total >= 65) $grade = '2.5';
    elseif ($total >= 60) $grade = '2';
    elseif ($total >= 55) $grade = '1.5';
    elseif ($total >= 50) $grade = '1';
    else $grade = '0';

    $staff_id = (int) $u['id'];

    if ($as) {
        $stmt = $conn->prepare("UPDATE staff_evaluations SET term=?, score_work=?, score_report=?, score_behavior=?, total_score=?, grade=?, remarks=?, staff_id=? WHERE student_id=?");
        $stmt->bind_param("siiiissii", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $staff_id, $std_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO staff_evaluations (term, score_work, score_report, score_behavior, total_score, grade, remarks, staff_id, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiissii", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $staff_id, $std_id);
    }

    if ($stmt->execute()) {
        header('Location: assessment_list.php?msg=grade_saved');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
    }

    body {
        background: #f8fafc;
        background-attachment: fixed;
    }

    .bg-blobs {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
    }

    .blob {
        position: absolute;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.05) 100%);
        border-radius: 50%;
        filter: blur(80px);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
    }

    .form-control-modern {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(226, 232, 240, 1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
    }

    .form-control-modern:focus {
        background: white;
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        outline: none;
    }

    .score-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid #f1f5f9;
        transition: all 0.3s ease;
    }

    .score-card:focus-within {
        border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05);
        transform: translateY(-2px);
    }

    .score-input {
        font-size: 2rem;
        font-weight: 800;
        text-align: center;
        border: none;
        width: 100%;
        color: #1e293b;
    }

    .score-input:focus { outline: none; }

    .result-display {
        background: var(--primary-gradient);
        color: white;
        border-radius: 24px;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
    }

    .result-display::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        transform: rotate(45deg);
    }

    .btn-save {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 16px;
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s;
        box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(99, 102, 241, 0.4);
        color: white;
    }

    .animate-up {
        animation: slideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<div class="bg-blobs">
    <div class="blob" style="top: -10%; right: -10%;"></div>
    <div class="blob" style="bottom: 10%; left: -10%; width: 600px; height: 600px;"></div>
</div>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 animate-up">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="assessment_list.php" class="text-decoration-none text-muted small">รายชื่อนักเรียน</a></li>
                    <li class="breadcrumb-item active small" aria-current="page">ประเมินผล</li>
                </ol>
            </nav>
            <h2 class="fw-bold text-dark mb-0">ลงคะแนนการประเมิน</h2>
        </div>
        <a href="assessment_list.php" class="btn btn-white glass-card border-0 rounded-pill px-4 py-2 text-dark shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
        </a>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Student Profile Card -->
        <div class="col-lg-10">
            <div class="glass-card p-4 mb-4 animate-up" style="animation-delay: 0.1s;">
                <div class="d-flex align-items-center gap-4">
                    <div class="flex-shrink-0">
                        <div class="bg-primary-soft text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 72px; height: 72px; font-size: 2rem;">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($std['fullname']) ?></h4>
                        <div class="d-flex flex-wrap gap-3 text-muted small">
                            <span><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($std['username']) ?></span>
                            <?php if(!empty($std['company_name'])): ?>
                                <span><i class="bi bi-building me-1"></i><?= htmlspecialchars($std['company_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evaluation Form -->
        <div class="col-lg-10">
            <form method="POST" class="animate-up" style="animation-delay: 0.2s;">
                <div class="glass-card p-5">
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-3">ภาคเรียน / ปีการศึกษา</label>
                            <input type="text" name="term" class="form-control form-control-modern" 
                                   placeholder="เช่น 2/2568" 
                                   value="<?= $as ? htmlspecialchars($as['term']) : '2/2568' ?>" required>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-award text-primary"></i> รายละเอียดคะแนน
                    </h6>

                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <div class="score-card text-center">
                                <div class="stat-icon bg-primary-soft text-primary mx-auto">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div class="small fw-bold text-muted text-uppercase mb-2">ทักษะการปฏิบัติ (70)</div>
                                <input type="number" name="score_work" id="s1" class="score-input" 
                                       max="70" value="<?= $as ? (int)$as['score_work'] : 0 ?>" min="0" oninput="cal()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="score-card text-center">
                                <div class="stat-icon bg-success-soft text-success mx-auto">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <div class="small fw-bold text-muted text-uppercase mb-2">สมุดบันทึก (20)</div>
                                <input type="number" name="score_report" id="s2" class="score-input" 
                                       max="20" value="<?= $as ? (int)$as['score_report'] : 0 ?>" min="0" oninput="cal()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="score-card text-center">
                                <div class="stat-icon bg-warning-soft text-warning mx-auto">
                                    <i class="bi bi-stars"></i>
                                </div>
                                <div class="small fw-bold text-muted text-uppercase mb-2">จิตพิสัย (10)</div>
                                <input type="number" name="score_behavior" id="s3" class="score-input" 
                                       max="10" value="<?= $as ? (int)$as['score_behavior'] : 0 ?>" min="0" oninput="cal()">
                            </div>
                        </div>
                    </div>

                    <!-- Result Summary -->
                    <div class="result-display mb-5 shadow-lg">
                        <div class="row align-items-center text-center">
                            <div class="col-md-6 border-end border-white border-opacity-25">
                                <div class="small fw-bold text-uppercase text-white text-opacity-75 mb-1">คะแนนรวมทั้งหมด</div>
                                <div class="display-3 fw-800" id="total_display"><?= $as ? (int)$as['total_score'] : 0 ?></div>
                                <div class="small fw-bold">/ 100 คะแนน</div>
                            </div>
                            <div class="col-md-6 mt-4 mt-md-0">
                                <div class="small fw-bold text-uppercase text-white text-opacity-75 mb-1">เกรดที่ได้รับ</div>
                                <div class="display-3 fw-800" id="grade_display"><?= $as && $as['grade'] !== '' ? htmlspecialchars($as['grade']) : '0' ?></div>
                                <div class="small fw-bold" id="grade_text">ตามเกณฑ์การวัดผล</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-3">หมายเหตุและข้อเสนอแนะ</label>
                        <textarea name="remarks" class="form-control form-control-modern" rows="4" 
                                  placeholder="ระบุข้อเสนอแนะเกี่ยวกับการปฏิบัติงานของนักเรียน..."><?= $as ? htmlspecialchars($as['remarks']) : '' ?></textarea>
                    </div>

                    <div class="d-grid gap-3 d-md-flex justify-content-md-end">
                        <button type="submit" name="save_grade" class="btn btn-save px-5 order-md-2">
                            <i class="bi bi-cloud-arrow-up me-2"></i>บันทึกผลการประเมิน
                        </button>
                        <a href="assessment_list.php" class="btn btn-light rounded-pill px-5 fw-bold order-md-1">ยกเลิก</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cal() {
    let s1 = parseInt(document.getElementById('s1').value) || 0;
    let s2 = parseInt(document.getElementById('s2').value) || 0;
    let s3 = parseInt(document.getElementById('s3').value) || 0;
    
    // Auto correct max values visually but enforce in logic
    if(s1 > 70) s1 = 70;
    if(s2 > 20) s2 = 20;
    if(s3 > 10) s3 = 10;
    
    let total = s1 + s2 + s3;
    document.getElementById('total_display').innerText = total;

    let grade = '0';
    let gradeLabel = 'ไม่ผ่านเกณฑ์';
    
    if (total >= 80) { grade = '4'; gradeLabel = 'ดีเยี่ยม'; }
    else if (total >= 75) { grade = '3.5'; gradeLabel = 'ดีมาก'; }
    else if (total >= 70) { grade = '3'; gradeLabel = 'ดี'; }
    else if (total >= 65) { grade = '2.5'; gradeLabel = 'ดีพอใช้'; }
    else if (total >= 60) { grade = '2'; gradeLabel = 'พอใช้'; }
    else if (total >= 55) { grade = '1.5'; gradeLabel = 'ผ่านเกณฑ์ขั้นต่ำ'; }
    else if (total >= 50) { grade = '1'; gradeLabel = 'ผ่าน'; }
    
    document.getElementById('grade_display').innerText = grade;
    document.getElementById('grade_text').innerText = gradeLabel;
}

// Initial call to set labels if data exists
document.addEventListener('DOMContentLoaded', cal);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

