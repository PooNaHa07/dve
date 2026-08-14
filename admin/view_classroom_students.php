<?php
// admin/view_classroom_students.php - View students in a specific classroom
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

$classroom_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($classroom_id <= 0) {
    header("Location: manage_classrooms.php");
    exit;
}

// Fetch classroom details
$stmt = $conn->prepare("SELECT * FROM classrooms WHERE id = ?");
$stmt->bind_param("i", $classroom_id);
$stmt->execute();
$classroom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$classroom) {
    header("Location: manage_classrooms.php");
    exit;
}

// Fetch students in this classroom
$sql_students = "SELECT u.*, m.fullname as mentor_name 
                 FROM users u 
                 LEFT JOIN users m ON u.mentor_id = m.id 
                 WHERE u.classroom_id = ? AND u.role = 'student' 
                 ORDER BY u.fullname ASC";
$stmt = $conn->prepare($sql_students);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

$student_count = $students->num_rows;

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
:root {
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(255, 255, 255, 0.2);
    --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}

body {
    background: #f1f5f9;
    min-height: 100vh;
}

.bg-blob {
    position: fixed;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.05) 100%);
    border-radius: 50%;
    filter: blur(100px);
    z-index: -1;
}
.blob-1 { top: -200px; right: -200px; }
.blob-2 { bottom: -200px; left: -200px; }

.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 1.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.stat-mini-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(0,0,0,0.05);
}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container admin-content-wrapper py-5">
    <div class="admin-header-section d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-graduation-cap"></i>
                รายชื่อนักเรียน: <?php echo htmlspecialchars($classroom['class_name']); ?>
            </h2>
            <p class="text-muted mb-0">ตรวจสอบและดูข้อมูลนักเรียนทั้งหมดในห้องเรียนนี้</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_classrooms.php" class="btn btn-admin-outline px-4">
                <i class="fas fa-arrow-left me-2"></i> กลับหน้าจัดการห้องเรียน
            </a>
            <a href="manage_users.php?role=student" class="btn btn-primary px-4 rounded-pill">
                <i class="fas fa-user-plus me-2"></i> จัดการผู้ใช้ทั้งหมด
            </a>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="stat-mini-card">
                <div>
                    <div class="fs-4 fw-bold text-dark"><?php echo $student_count; ?> คน</div>
                    <div class="text-muted small text-uppercase fw-bold">นักเรียนในห้องเรียนนี้</div>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fas fa-users fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini-card">
                <div>
                    <div class="fs-4 fw-bold text-dark"><?php echo (int)$classroom['total_students']; ?> คน</div>
                    <div class="text-muted small text-uppercase fw-bold">เป้าหมายจำนวนนักเรียน</div>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fas fa-bullseye fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="p-4">
            <div class="table-responsive">
                <table id="studentsTable" class="table align-middle table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>ระดับ</th>
                            <th>ครูนิเทศก์ที่รับผิดชอบ</th>
                            <th class="text-end" style="width: 100px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $students->fetch_assoc()): ?>
                        <tr>
                            <td><span class="text-muted fw-bold">#<?php echo $row['id']; ?></span></td>
                            <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($row['student_code'] ?: '-'); ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar text-white">
                                        <?php echo mb_substr($row['fullname'], 0, 1); ?>
                                    </div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                </div>
                            </td>
                            <td><i class="fas fa-phone-alt me-1 opacity-50 small"></i> <?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['student_level'] ?: '-'); ?></td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill fw-bold">
                                    <i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($row['mentor_name'] ?: 'ยังไม่ได้กำหนด'); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="manage_users.php?role=student" class="btn btn-light btn-sm rounded-3 px-3" title="ไปหน้าจัดการเพื่อแก้ไข">
                                    <i class="fas fa-edit text-warning"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#studentsTable').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
