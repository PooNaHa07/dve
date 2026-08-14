<?php
// detail_company.php - แสดงรายละเอียดบริษัท
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/includes/functions.php'; 
require_once __DIR__ . '/includes/configdb.php'; 

global $conn;

// 1. ตรวจสอบสิทธิ์การจัดการ (Staff/Admin เท่านั้นที่เห็นปุ่มแก้ไข)
$can_manage = is_logged_in() && in_array(get_current_role(), ['staff', 'admin']);

// 2. รับ ID จาก URL และตรวจสอบ
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = 'รายละเอียดสถานประกอบการ';
include __DIR__ . '/includes/header_public.php';
?>

<style>
    /* Animated Floating Blobs Background matching Calendar & News */
    .bg-blob {
        position: fixed;
        width: 600px;
        height: 600px;
        filter: blur(140px);
        z-index: -1;
        opacity: 0.12;
        pointer-events: none;
        border-radius: 50%;
        animation: floatBlob 18s infinite alternate ease-in-out;
    }
    .blob-1 { background: #6366f1; top: -150px; right: -150px; }
    .blob-2 { background: #3b82f6; bottom: -150px; left: -150px; animation-delay: -5s; }

    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(80px, 50px) scale(1.1); }
    }

    /* Premium Detail Container Wrapper */
    .detail-outer-container {
        max-width: 800px;
        margin: 3.5rem auto 6rem;
    }

    .detail-header-section {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .detail-icon-box {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25);
        color: white;
    }

    .detail-title {
        font-size: 2.25rem;
        font-weight: 850;
        letter-spacing: -0.5px;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .detail-subtitle {
        font-size: 1.05rem;
        color: #64748b;
        font-weight: 500;
    }

    /* Glassmorphic Cards Layout */
    .glass-detail-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 32px;
        padding: 3.5rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.05);
        margin-bottom: 2rem;
    }

    /* Section Sub-Header with glowing accents */
    .section-accent-header {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 1.75rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        padding-bottom: 0.5rem;
    }

    .section-accent-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: linear-gradient(90deg, #6366f1, #3b82f6);
        border-radius: 2px;
    }

    .accent-icon {
        color: #6366f1;
        font-size: 1.35rem;
    }

    /* Premium Data Display Elements */
    .data-row-box {
        background: rgba(248, 250, 252, 0.5);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1rem;
        border-left: 4px solid #e2e8f0;
        transition: all 0.25s;
    }

    .data-row-box:hover {
        background: rgba(99, 102, 241, 0.02);
        border-left-color: #6366f1;
    }

    .data-label {
        font-weight: 800;
        color: #64748b;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .data-value {
        font-weight: 600;
        color: #1e293b;
        font-size: 1.05rem;
        word-break: break-word;
    }

    /* Action buttons design */
    .btn-edit-company {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        border-radius: 14px;
        padding: 0.85rem 2rem;
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        text-decoration: none;
    }

    .btn-edit-company:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        color: white;
    }

    .btn-back-list {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 0.85rem 2rem;
        font-weight: 700;
        border-radius: 14px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.25s;
    }

    .btn-back-list:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b;
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container detail-outer-container">
    
    <?php if ($company_id === 0): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 rounded-4 p-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon fs-4"></i>
            <div>ไม่พบ ID สถานประกอบการที่ต้องการดูรายละเอียด</div>
        </div>
        <div class="text-center mt-3">
            <a href="companies/list.php" class="btn-back-list d-inline-flex">
                <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
            </a>
        </div>
    <?php else:
        // 3. ดึงข้อมูลบริษัทตาม ID (ใช้ Prepared Statement เพื่อความปลอดภัย)
        $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();
        
        if ($company): ?>
            <!-- Header section -->
            <div class="detail-header-section">
                <div class="detail-icon-box">
                    <i class="bi bi-building"></i>
                </div>
                <h1 class="detail-title"><?= e($company['name']) ?></h1>
                <p class="detail-subtitle">ตรวจสอบข้อมูลและพิกัดผู้ประสานงานหลักของทางบริษัทคู่ค้า</p>
            </div>

            <!-- Glassmorphic Details Card -->
            <div class="glass-detail-card">
                <!-- Section 1: Establishment Info -->
                <h4 class="section-accent-header">
                    <i class="bi bi-geo-alt-fill accent-icon"></i>
                    <span>ข้อมูลสถานประกอบการ</span>
                </h4>
                
                <div class="data-row-box">
                    <div class="data-label">ชื่อสถานประกอบการ</div>
                    <div class="data-value"><?= e($company['name']) ?></div>
                </div>

                <div class="data-row-box mb-5">
                    <div class="data-label">ที่อยู่จัดตั้งบริษัท</div>
                    <div class="data-value"><?= !empty($company['address']) ? e($company['address']) : 'ไม่ระบุข้อมูลที่อยู่ชัดเจน' ?></div>
                </div>

                <!-- Section 2: Contact Person Info -->
                <h4 class="section-accent-header">
                    <i class="bi bi-person-badge-fill accent-icon"></i>
                    <span>ข้อมูลผู้ประสานงานหลัก</span>
                </h4>

                <div class="data-row-box">
                    <div class="data-label">ชื่อผู้ติดต่อประสานงาน</div>
                    <div class="data-value"><?= e($company['contact_name']) ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="data-row-box">
                            <div class="data-label">เบอร์โทรศัพท์ติดต่อ</div>
                            <div class="data-value"><?= e($company['contact_phone']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="data-row-box">
                            <div class="data-label">อีเมลติดต่อหลัก</div>
                            <div class="data-value"><?= !empty($company['contact_email']) ? e($company['contact_email']) : '-' ?></div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Supervision Reports in this Company -->
                <h4 class="section-accent-header mt-5">
                    <i class="bi bi-file-earmark-check-fill accent-icon"></i>
                    <span>รายงานการนิเทศก์ของสถานประกอบการนี้</span>
                </h4>
                
                <?php
                $sup_stmt = $conn->prepare("
                    SELECT sf.id AS sup_id, sf.uploaded_at, sf.status AS sup_status, sf.signed_file, sf.file_path,
                           tch.fullname AS teacher_name
                    FROM supervision_files sf
                    LEFT JOIN users tch ON sf.teacher_id = tch.id
                    WHERE sf.company_id = ?
                    ORDER BY sf.uploaded_at DESC
                ");
                $sup_stmt->bind_param("i", $company_id);
                $sup_stmt->execute();
                $sup_res = $sup_stmt->get_result();
                ?>

                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary fw-bold">
                            <tr>
                                <th style="width: 80px;">ลำดับ</th>
                                <th>วันที่ส่ง</th>
                                <th>ครูผู้นิเทศก์</th>
                                <th>สถานะรายงาน</th>
                                <th class="text-center" style="width: 150px;">เอกสาร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sup_res && $sup_res->num_rows > 0): $si = 1; ?>
                                <?php while ($sup_row = $sup_res->fetch_assoc()): 
                                    $s_url = !empty($sup_row['signed_file']) 
                                        ? 'uploads/supervision_docs/signed/' . htmlspecialchars($sup_row['signed_file']) 
                                        : (!empty($sup_row['file_path']) ? 'uploads/supervision_docs/original/' . htmlspecialchars($sup_row['file_path']) : '');
                                    
                                    $s_url_fixed = BASE_URL . '/' . $s_url; 
                                ?>
                                <tr>
                                    <td class="text-secondary fw-bold"><?= $si++ ?></td>
                                    <td class="text-muted"><?= date('d/m/Y H:i', strtotime($sup_row['uploaded_at'])) ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($sup_row['teacher_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if ((int)$sup_row['sup_status'] === 0): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning px-2.5 py-1.5 rounded-pill">รอเจ้าหน้าที่ตรวจ</span>
                                        <?php elseif ((int)$sup_row['sup_status'] === 1): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info px-2.5 py-1.5 rounded-pill">รอผู้บริหารลงนาม</span>
                                        <?php elseif ((int)$sup_row['sup_status'] === 2): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ลงนามแล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($s_url): ?>
                                            <a href="<?= htmlspecialchars($s_url_fixed) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size:0.75rem;">
                                                <i class="bi bi-eye-fill me-1"></i> ดูไฟล์ PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-file-earmark-x d-block fs-3 mb-1 text-muted"></i>
                                        ยังไม่มีประวัติการส่งรายงานการนิเทศก์สำหรับสถานประกอบการนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section 4: Intern Students in this Company -->
                <h4 class="section-accent-header mt-5">
                    <i class="bi bi-people-fill accent-icon"></i>
                    <span>นักเรียนที่ฝึกประสบการณ์วิชาชีพ ณ สถานประกอบการนี้</span>
                </h4>

                <?php
                $std_stmt = $conn->prepare("
                    SELECT u.student_code, u.fullname, u.phone, c.class_name
                    FROM users u
                    LEFT JOIN classrooms c ON u.classroom_id = c.id
                    WHERE (u.company_id = ? OR u.company_name = ?) AND u.role = 'student'
                    ORDER BY u.fullname ASC
                ");
                $comp_name = $company['name'];
                $std_stmt->bind_param("is", $company_id, $comp_name);
                $std_stmt->execute();
                $std_res = $std_stmt->get_result();
                ?>

                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary fw-bold">
                            <tr>
                                <th style="width: 80px;">ลำดับ</th>
                                <th>รหัสนักศึกษา</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ห้องเรียน / สาขา</th>
                                <th>เบอร์โทรศัพท์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($std_res && $std_res->num_rows > 0): $si = 1; ?>
                                <?php while ($std_row = $std_res->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-secondary fw-bold"><?= $si++ ?></td>
                                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold"><?= htmlspecialchars($std_row['student_code'] ?? '-') ?></span></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($std_row['fullname']) ?></td>
                                    <td>
                                        <?php if (!empty($std_row['class_name'])): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border-0"><i class="bi bi-building me-1"></i><?= htmlspecialchars($std_row['class_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($std_row['phone'] ?? '-') ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-person-exclamation d-block fs-3 mb-1 text-muted"></i>
                                        ไม่มีรายชื่อนักศึกษาฝึกงานที่สังกัดสถานประกอบการนี้ในปัจจุบัน
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Action Row -->
                <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 mt-4 pt-4 border-top">
                    <a href="companies/list.php" class="btn-back-list">
                        <i class="bi bi-arrow-left"></i> กลับหน้ารายการทั้งหมด
                    </a>
                    <a href="director/view_supervision.php?company_id=<?= $company['id'] ?>" class="btn-edit-company" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                        <i class="bi bi-file-earmark-bar-graph"></i> ดูรายงานนิเทศประจำบริษัทนี้
                    </a>
                    <?php if ($can_manage): ?>
                        <a href="companies/edit_company.php?id=<?= $company['id'] ?>" class="btn-edit-company">
                            <i class="bi bi-pencil-square"></i> แก้ไขข้อมูลสถานประกอบการ
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning text-center rounded-4 p-4 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2 text-warning"></i>
                <div class="fw-bold fs-5">ไม่พบข้อมูลบริษัทคู่ค้า ID #<?= $company_id ?></div>
                <div class="small text-muted mt-1">ข้อมูลสถานประกอบการรายการนี้อาจถูกถอนออกจากระบบหลักไปแล้ว</div>
            </div>
            <div class="text-center mt-3">
                <a href="companies/list.php" class="btn-back-list d-inline-flex">
                    <i class="bi bi-arrow-left"></i> กลับหน้ารายการทั้งหมด
                </a>
            </div>
        <?php endif;
    endif; ?>
</div>

<?php 
include __DIR__ . '/includes/footer.php'; 
include __DIR__ . '/includes/footer_close.php';
?>