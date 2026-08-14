<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

// ดึงรายชื่อครูทุกคนและนับจำนวนแผนที่ส่ง
$sql = "SELECT u.id, u.fullname, u.email, COUNT(p.id) as total_plans 
        FROM users u 
        LEFT JOIN plans p ON u.id = p.teacher_id 
        WHERE u.role = 'teacher' 
        GROUP BY u.id 
        ORDER BY u.fullname ASC";
$result = $conn->query($sql);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
.teacher-profile-card {
    background: white;
    border-radius: 1.25rem;
    padding: 2rem 1.5rem;
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
}
.teacher-profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px -5px rgba(0,0,0,0.1);
}
.avatar-circle {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    font-size: 2.5rem;
    color: var(--admin-primary);
    border: 4px solid #f1f5f9;
}
.counter-pill {
    background: rgba(79, 70, 229, 0.05);
    border: 1px dashed rgba(79, 70, 229, 0.3);
    border-radius: 1rem;
    padding: 0.75rem;
}
</style>

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-user-tie"></i>
                ติดตามการนิเทศของครู
            </h2>
            <p class="text-muted small mb-0 mt-1">แสดงจำนวนการส่งแผนการฝึกและแผนการนิเทศแยกรายบุคคล</p>
        </div>
        <div>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> กลับหน้าแรก
            </a>
        </div>
    </div>

    <div class="row g-4">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="teacher-profile-card text-center shadow-sm">
                        <div class="avatar-circle">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1 text-truncate px-2"><?php echo htmlspecialchars($row['fullname']); ?></h5>
                        <div class="text-muted small mb-3"><i class="fas fa-id-card-alt me-1 opacity-50"></i>ตำแหน่ง ครูนิเทศก์</div>
                        
                        <div class="counter-pill mb-4">
                            <div class="small text-muted fw-bold mb-1">จำนวนแผนที่ส่งเข้าระบบ</div>
                            <div class="d-flex align-items-baseline justify-content-center gap-1">
                                <span class="fs-3 fw-bolder text-primary"><?php echo $row['total_plans']; ?></span>
                                <span class="small text-muted">รายการ</span>
                            </div>
                        </div>

                        <a href="teacher_details.php?id=<?php echo $row['id']; ?>" class="btn btn-admin-primary w-100 rounded-pill py-2">
                            <i class="fas fa-file-signature me-2"></i> ตรวจสอบแผน
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <div class="bg-light d-inline-flex p-4 rounded-circle mb-3">
                    <i class="fas fa-users-slash fs-1 opacity-25"></i>
                </div>
                <p class="fs-5 mb-0">ยังไม่มีรายชื่อครูนิเทศก์ลงทะเบียนในระบบ</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>