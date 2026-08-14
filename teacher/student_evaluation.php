<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher', 'admin']);

// 1. ตรวจสอบว่ามีการส่ง std_id มาจริงไหม
if (!isset($_GET['std_id']) || empty($_GET['std_id'])) {
    die("Error: ไม่พบรหัสนักเรียน (std_id is missing)");
}

$std_id = (int)($_GET['std_id'] ?? 0); // รับค่า ID นักเรียน & ป้องกัน SQL Injection
$u = current_user();
$teacher_id = (int)$u['id'];

// 2. ดึงข้อมูลนักเรียนและตรวจสอบสิทธิ์ (IDOR check)
$stmt_std = $conn->prepare("SELECT u.* FROM users u 
                            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id 
                            WHERE u.id = ? AND ta.teacher_id = ?");
$stmt_std->bind_param("ii", $std_id, $teacher_id);
$stmt_std->execute();
$std = $stmt_std->get_result()->fetch_assoc();

if (!$std) {
    die("Error: ไม่พบข้อมูลนักเรียน หรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้");
}

// 3. ดึงข้อมูลเกรดเดิม
$stmt_as = $conn->prepare("SELECT * FROM evaluations WHERE student_id = ?");
$stmt_as->bind_param("i", $std_id);
$stmt_as->execute();
$as = $stmt_as->get_result()->fetch_assoc();

if (isset($_POST['save_grade'])) {
    $s_work = $_POST['score_work'];
    $s_report = $_POST['score_report'];
    $s_behavior = $_POST['score_behavior'];
    $total = $s_work + $s_report + $s_behavior;
    $term = $_POST['term'];
    $remarks = $_POST['remarks'];
    
    // คำนวณเกรด
    if($total >= 80) $grade = '4';
    elseif($total >= 75) $grade = '3.5';
    elseif($total >= 70) $grade = '3';
    elseif($total >= 65) $grade = '2.5';
    elseif($total >= 60) $grade = '2';
    elseif($total >= 55) $grade = '1.5';
    elseif($total >= 50) $grade = '1';
    else $grade = '0';

    if ($as) {
        $stmt = $conn->prepare("UPDATE evaluations SET term=?, score_work=?, score_report=?, score_behavior=?, total_score=?, grade=?, remarks=?, teacher_id=? WHERE student_id=?");
        $stmt->bind_param("siiiisssi", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $u['id'], $std_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO evaluations (term, score_work, score_report, score_behavior, total_score, grade, remarks, teacher_id, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiisssi", $term, $s_work, $s_report, $s_behavior, $total, $grade, $remarks, $u['id'], $std_id);
    }
    
    if ($stmt->execute()) {
        header("Location: assessment_list.php?msg=grade_saved");
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.75);
        --glass-border: rgba(255, 255, 255, 0.4);
        --teacher-gradient: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
    }

    body {
        background: #f1f5f9;
        background-attachment: fixed;
    }

    .bg-blobs {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
        overflow: hidden; pointer-events: none;
    }
    .blob {
        position: absolute; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(6, 182, 212, 0.04) 100%);
        border-radius: 50%; filter: blur(80px);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.05);
        overflow: hidden;
    }

    .score-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .score-card:focus-within {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        transform: translateY(-2px);
    }

    .score-input {
        font-size: 2.5rem;
        font-weight: 800;
        border: none;
        background: transparent;
        color: #1e293b;
        text-align: center;
        width: 100%;
        padding: 0;
    }

    .score-input:focus { outline: none; }

    .result-panel {
        background: var(--teacher-gradient);
        border-radius: 24px;
        padding: 2.5rem;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(79, 70, 229, 0.2);
    }

    .grade-circle {
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 900;
        margin: 1rem auto;
        color: white;
    }

    .btn-tch-save {
        background: white;
        color: #4f46e5;
        border: none;
        border-radius: 16px;
        padding: 1rem 2rem;
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .btn-tch-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        color: #4338ca;
    }

    .animate-in {
        animation: fadeIn 0.6s ease-out forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="bg-blobs">
    <div class="blob" style="top: -10%; right: -10%;"></div>
    <div class="blob" style="bottom: 10%; left: -10%;"></div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <!-- Header -->
            <div class="d-flex align-items-center mb-5 animate-in">
                <a href="assessment_list.php" class="btn btn-white glass-card border-0 rounded-circle p-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px;">
                    <i class="bi bi-arrow-left text-dark"></i>
                </a>
                <div class="ms-4">
                    <h2 class="fw-bold text-dark mb-1">บันทึกผลการประเมิน</h2>
                    <p class="text-muted small mb-0">นักเรียน: <span class="fw-bold text-primary"><?= htmlspecialchars($std['fullname']) ?></span></p>
                </div>
            </div>

            <form method="POST" class="animate-in" style="animation-delay: 0.1s;">
                <div class="row g-4">
                    <!-- Score Inputs -->
                    <div class="col-lg-7">
                        <div class="glass-card p-4 h-100">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark small">ภาคเรียน / ปีการศึกษา</label>
                                <input type="text" name="term" class="form-control form-control-lg border-0 bg-white shadow-sm rounded-4 px-4" placeholder="เช่น 2/2567" value="<?= $as ? htmlspecialchars($as['term']) : '2/2568' ?>" required>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <div class="score-card d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-bold text-dark">คะแนนปฏิบัติ</div>
                                            <div class="small text-muted">เต็ม 70 คะแนน</div>
                                        </div>
                                        <div style="width: 120px;">
                                            <input type="number" name="score_work" id="s1" class="score-input" max="70" min="0" value="<?= $as ? (int)$as['score_work'] : 0 ?>" oninput="cal()">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="score-card d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-bold text-dark">สมุดบันทึก</div>
                                            <div class="small text-muted">เต็ม 20 คะแนน</div>
                                        </div>
                                        <div style="width: 120px;">
                                            <input type="number" name="score_report" id="s2" class="score-input" max="20" min="0" value="<?= $as ? (int)$as['score_report'] : 0 ?>" oninput="cal()">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="score-card d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-bold text-dark">จิตพิสัย</div>
                                            <div class="small text-muted">เต็ม 10 คะแนน</div>
                                        </div>
                                        <div style="width: 120px;">
                                            <input type="number" name="score_behavior" id="s3" class="score-input" max="10" min="0" value="<?= $as ? (int)$as['score_behavior'] : 0 ?>" oninput="cal()">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-bold text-dark small">หมายเหตุ / ข้อเสนอแนะ</label>
                                <textarea name="remarks" class="form-control border-0 bg-white shadow-sm rounded-4 p-3" rows="3" placeholder="ระบุความคิดเห็นเพิ่มเติม..."><?= $as ? htmlspecialchars($as['remarks']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Result Panel -->
                    <div class="col-lg-5">
                        <div class="result-panel h-100 d-flex flex-column justify-content-center">
                            <div class="small text-uppercase fw-bold opacity-75 mb-2">ผลการประเมินสรุป</div>
                            
                            <div class="mb-4">
                                <div class="display-1 fw-bold mb-0" id="total_display"><?= $as ? (int)$as['total_score'] : 0 ?></div>
                                <div class="small opacity-75">คะแนนเต็ม 100</div>
                            </div>

                            <div class="mb-5">
                                <div class="grade-circle" id="grade_display"><?= $as ? htmlspecialchars($as['grade']) : '-' ?></div>
                                <div class="fw-bold">ระดับผลการเรียน (เกรด)</div>
                            </div>

                            <div class="d-grid px-3">
                                <button type="submit" name="save_grade" class="btn btn-tch-save shadow">
                                    <i class="bi bi-check-lg me-2"></i> บันทึกข้อมูล
                                </button>
                                <a href="assessment_list.php" class="btn btn-link text-white mt-3 text-decoration-none small opacity-75 hover-opacity-100">
                                    ยกเลิกและย้อนกลับ
                                </a>
                            </div>
                        </div>
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
    
    // Validate max values
    if(s1 > 70) document.getElementById('s1').value = 70, s1 = 70;
    if(s2 > 20) document.getElementById('s2').value = 20, s2 = 20;
    if(s3 > 10) document.getElementById('s3').value = 10, s3 = 10;
    
    let total = s1 + s2 + s3;
    document.getElementById('total_display').innerText = total;
    
    let grade = '0';
    if(total >= 80) grade = '4';
    else if(total >= 75) grade = '3.5';
    else if(total >= 70) grade = '3';
    else if(total >= 65) grade = '2.5';
    else if(total >= 60) grade = '2';
    else if(total >= 55) grade = '1.5';
    else if(total >= 50) grade = '1';
    document.getElementById('grade_display').innerText = grade;
}

// Initial calculation
window.onload = cal;
</script>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cal() {
    let s1 = parseInt(document.getElementById('s1').value) || 0;
    let s2 = parseInt(document.getElementById('s2').value) || 0;
    let s3 = parseInt(document.getElementById('s3').value) || 0;
    let total = s1 + s2 + s3;
    document.getElementById('total_display').innerText = total;
    
    let grade = '0';
    if(total >= 80) grade = '4';
    else if(total >= 75) grade = '3.5';
    else if(total >= 70) grade = '3';
    else if(total >= 65) grade = '2.5';
    else if(total >= 60) grade = '2';
    else if(total >= 55) grade = '1.5';
    else if(total >= 50) grade = '1';
    document.getElementById('grade_display').innerText = grade;
}
</script>