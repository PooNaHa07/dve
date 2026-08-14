<?php
require_once '../includes/configdb.php';
require_once '../includes/functions.php';
require_login();
require_role(['director','admin']);
$hide_welcome = true;
include '../includes/header.php';

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Count stats
$total_students = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
$total_graded   = $conn->query("SELECT COUNT(DISTINCT student_id) c FROM evaluations WHERE grade IS NOT NULL AND grade != ''")->fetch_assoc()['c'];
?>
<link rel="stylesheet" href="../includes/director-pages.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid py-4">

    <!-- Header — gold/amber gradient for certificates -->
    <div class="dp-header d-flex align-items-center justify-content-between gap-3 mb-4"
         style="background:linear-gradient(135deg,#78350f 0%,#d97706 45%,#f59e0b 100%);box-shadow:0 20px 50px rgba(245,158,11,0.35);">
        <div class="d-flex align-items-center gap-3">
            <div class="dp-header-icon">🏅</div>
            <div>
                <h4>ทะเบียนเกียรติบัตรนักเรียน</h4>
                <p>รายชื่อนักเรียนที่ได้รับการประเมินและออกเกียรติบัตร</p>
            </div>
        </div>
        <a href="../roles/director.php" class="dp-back-btn">
            <i class="bi bi-house-fill"></i> กลับหน้าหลัก
        </a>
    </div>

    <!-- Mini stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div style="background:#fff;border-radius:1rem;padding:1.25rem;border:1px solid rgba(245,158,11,0.15);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,0.12);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:0.75rem;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div style="font-size:1.75rem;font-weight:800;color:#1e293b;line-height:1;"><?= number_format($total_students) ?></div>
                <div style="font-size:0.8rem;color:#64748b;margin-top:4px;">นักเรียนทั้งหมด</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div style="background:#fff;border-radius:1rem;padding:1.25rem;border:1px solid rgba(245,158,11,0.15);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,0.12);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:0.75rem;">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div style="font-size:1.75rem;font-weight:800;color:#1e293b;line-height:1;"><?= number_format($total_graded) ?></div>
                <div style="font-size:0.8rem;color:#64748b;margin-top:4px;">ได้รับการประเมินแล้ว</div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="dp-filter">
        <label class="fw-semibold text-secondary small mb-2 d-block">
            <i class="bi bi-funnel me-1 text-warning"></i>เลือกห้องเรียน
        </label>
        <form method="GET">
            <select name="class_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- เลือกห้องเรียน --</option>
                <?php
                $classes = $conn->query("SELECT * FROM classrooms ORDER BY class_name ASC");
                while ($c = $classes->fetch_assoc()) {
                    $sel = ($class_id == $c['id']) ? 'selected' : '';
                    echo "<option value='{$c['id']}' $sel>{$c['class_name']}</option>";
                }
                ?>
            </select>
        </form>
    </div>

    <!-- Table -->
    <div class="dp-card">
        <div class="dp-card-header" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#92400e;">
            <i class="bi bi-trophy-fill" style="color:#d97706;"></i>
            รายชื่อนักเรียนและเกียรติบัตร
        </div>
        <div class="table-responsive">
            <table id="viewCertsTable" class="dp-table table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-start">ชื่อ-นามสกุล</th>
                        <th class="text-start">ชื่อเกียรติบัตร / รางวัล</th>
                        <th>หน่วยงานที่ออกให้</th>
                        <th>วันที่ได้รับ</th>
                        <th>เกรด</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($class_id > 0) {
                    $sql = "SELECT u.id AS student_id, u.fullname, u.username,
                                   e.grade, e.created_at AS eval_date,
                                   COALESCE(c.name, u.company_name) AS company_name
                            FROM users u
                            LEFT JOIN evaluations e ON u.id = e.student_id
                            LEFT JOIN companies c ON u.company_id = c.id
                            WHERE u.classroom_id = $class_id
                              AND u.role = 'student'
                              AND e.grade IS NOT NULL AND e.grade != ''
                            ORDER BY e.created_at DESC";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0) {
                        while ($row = $res->fetch_assoc()) {
                            $certTitle = "เกียรติบัตรการฝึกประสบการณ์วิชาชีพ";
                            $org       = htmlspecialchars($row['company_name'] ?: '—');
                            $date      = $row['eval_date'] ? date('d/m/Y', strtotime($row['eval_date'])) : '—';
                            $viewUrl   = "../certificates/generate_certificate.php?id=".(int)$row['student_id'];
                            $grade     = htmlspecialchars($row['grade']);
                            $gradeBadge = in_array($grade, ['A','4','ดีเยี่ยม'])
                                ? 'dp-badge-success' : (in_array($grade, ['B','3','ดี']) ? 'dp-badge-info' : 'dp-badge-warning');
                            echo "
                            <tr>
                                <td class='text-start'>
                                    <div class='d-flex align-items-center gap-2'>
                                        <div style='width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#d97706,#f59e0b);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.85rem;font-weight:700;flex-shrink:0;'>".mb_substr($row['fullname'],0,1)."</div>
                                        <div><div class='fw-semibold'>".htmlspecialchars($row['fullname'])."</div><small class='text-muted'>".htmlspecialchars($row['username'])."</small></div>
                                    </div>
                                </td>
                                <td class='text-start'>
                                    <i class='bi bi-award-fill text-warning me-1'></i>
                                    <span class='fw-medium'>$certTitle</span>
                                </td>
                                <td class='text-muted'>$org</td>
                                <td class='text-center text-nowrap'>$date</td>
                                <td class='text-center'><span class='dp-badge $gradeBadge'>$grade</span></td>
                                <td class='text-center'>
                                    <a href='$viewUrl' target='_blank' class='dp-btn dp-btn-primary'>
                                        <i class='bi bi-eye'></i>ดูเกียรติบัตร
                                    </a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'><div class='dp-empty'><i class='bi bi-award'></i><p>ห้องนี้ยังไม่มีนักเรียนที่ได้รับการประเมิน</p></div></td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'><div class='dp-empty'><i class='bi bi-arrow-up-circle'></i><p>กรุณาเลือกห้องเรียนด้านบน</p></div></td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang = {search:'ค้นหา:',lengthMenu:'แสดง _MENU_ รายการ',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',infoEmpty:'ไม่มีข้อมูล',infoFiltered:'(กรองจาก _MAX_ รายการ)',paginate:{first:'แรก',last:'ท้าย',next:'ถัดไป',previous:'ก่อนหน้า'},zeroRecords:'ไม่พบข้อมูล'};
    var tbl = document.getElementById('viewCertsTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        $(tbl).DataTable({ order:[[3,'desc']], language:dtLang, pageLength:25 });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>