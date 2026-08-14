<?php
require_once '../includes/configdb.php';
include '../includes/header.php';

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-danger"><i class="fas fa-chart-line me-2"></i>ผลการเรียนแยกห้อง</h3>
        <a href="../roles/director.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-home me-1"></i> กลับหน้าหลัก</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">เลือกห้องเรียน</label>
                    <select name="class_id" class="form-select shadow-none" onchange="this.form.submit()">
                        <option value="">-- กรุณาเลือกห้องเรียน --</option>
                        <?php
                        $classes = $conn->query("SELECT * FROM classrooms ORDER BY class_name ASC");
                        while($c = $classes->fetch_assoc()) {
                            $sel = ($class_id == $c['id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>{$c['class_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table id="viewGradesTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead class="table-danger text-white">
                    <tr>
                        <th class="py-3 ps-4">ชื่อ-นามสกุล</th>
                        <th class="text-center">คะแนนรวม</th>
                        <th class="text-center">เกรดเฉลี่ย (GPA)</th>
                        <th class="text-center">สถานะการประเมิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($class_id > 0) {
                        $sql = "SELECT u.fullname, g.score_total, g.gpa, g.grade_status 
                                FROM users u 
                                JOIN grades g ON u.id = g.student_id 
                                WHERE u.classroom_id = $class_id";
                        $res = $conn->query($sql);
                        if($res->num_rows > 0) {
                            while($row = $res->fetch_assoc()) {
                                $gpa_color = ($row['gpa'] >= 3.00) ? 'text-success' : (($row['gpa'] >= 2.00) ? 'text-primary' : 'text-danger');
                                echo "<tr>
                                        <td class='ps-4 fw-bold'>{$row['fullname']}</td>
                                        <td class='text-center'>".number_format($row['score_total'], 2)."</td>
                                        <td class='text-center fw-bold $gpa_color'>".number_format($row['gpa'], 2)."</td>
                                        <td class='text-center'><span class='badge bg-light text-dark border'>{$row['grade_status']}</span></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-5 text-muted'>ยังไม่มีข้อมูลผลการเรียนของห้องนี้</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'>กรุณาเลือกห้องเรียนด้านบน</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtLang = { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', infoEmpty: 'ไม่มีข้อมูล', infoFiltered: '(กรองจาก _MAX_ รายการ)', paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, zeroRecords: 'ไม่พบข้อมูล' };
    var tbl = document.getElementById('viewGradesTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) $(tbl).DataTable({ order: [[0, 'asc']], language: dtLang, pageLength: 25 });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>