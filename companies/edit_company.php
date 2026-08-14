<?php
// companies/edit_company.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['staff']);

$company_id = isset($_GET['id']) ? $_GET['id'] : null;
$error = '';
$success = '';
$company = null; // ข้อมูลบริษัทที่ดึงมา

// 1. ดึงข้อมูลบริษัทปัจจุบันมาแสดงในฟอร์ม (หรือกำหนดค่าเริ่มต้นหากมีการส่งฟอร์มแล้วเกิด error)
if ($company_id) {
    $stmt = $conn->prepare("SELECT id, name, address, contact_name, contact_phone, contact_email FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $company = $result->fetch_assoc();
    } else {
        $error = "ไม่พบข้อมูลบริษัทที่ระบุ";
    }
    $stmt->close();
} else {
    $error = "ไม่พบ ID บริษัท";
}

// 2. ประมวลผลเมื่อมีการส่งฟอร์ม (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $company_id) {
    // รับค่าจากฟอร์มและทำความสะอาดข้อมูล
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $contact_name = trim(isset($_POST['contact_name']) ? $_POST['contact_name'] : '');
    $contact_phone = trim(isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '');
    $contact_email = trim(isset($_POST['contact_email']) ? $_POST['contact_email'] : '');

    // การตรวจสอบข้อมูลพื้นฐาน
    if (empty($name) || empty($contact_name) || empty($contact_phone) || empty($contact_email)) {
        $error = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลติดต่อไม่ถูกต้อง";
    } else {
        // ใช้ Prepared Statement สำหรับ UPDATE
        $stmt_update = $conn->prepare(
            "UPDATE companies SET name = ?, address = ?, contact_name = ?, contact_phone = ?, contact_email = ? WHERE id = ?"
        );
        $stmt_update->bind_param("sssssi", $name, $address, $contact_name, $contact_phone, $contact_email, $company_id);
        
        if ($stmt_update->execute()) {
            $success = "แก้ไขข้อมูลบริษัท **" . htmlspecialchars($name) . "** สำเร็จเรียบร้อยแล้ว!";
            
            // อัปเดตตัวแปร $company ให้แสดงข้อมูลใหม่ในฟอร์มทันที
            $company = [
                'id' => $company_id, 
                'name' => $name, 
                'address' => $address, 
                'contact_name' => $contact_name, 
                'contact_phone' => $contact_phone, 
                'contact_email' => $contact_email
            ];
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error;
        }
        $stmt_update->close();
    }
}

$page_title = 'แก้ไขข้อมูลสถานประกอบการ';
include __DIR__ . '/../includes/header_public.php';
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

    /* Premium Form Wrapper */
    .form-outer-container {
        max-width: 750px;
        margin: 3rem auto 5rem;
    }

    .form-header-box {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .form-icon-circle {
        width: 76px;
        height: 76px;
        background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        margin-bottom: 1.25rem;
        box-shadow: 0 12px 24px rgba(99, 102, 241, 0.25);
    }

    .form-title {
        font-size: 2rem;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.5px;
    }

    .form-subtitle {
        font-size: 1rem;
        color: #64748b;
        font-weight: 500;
        margin-top: 0.25rem;
    }

    /* Glassmorphic Card Container */
    .glass-form-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 28px;
        padding: 3rem;
        box-shadow: 0 25px 55px rgba(0,0,0,0.05);
    }

    /* Luxury Styled Input Elements */
    .form-label {
        font-weight: 700;
        color: #334155;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }

    .form-control {
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: #1e293b;
        transition: all 0.3s;
        background-color: white;
    }

    .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        background-color: white;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    /* Save Button Style */
    .btn-save-company {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        border-radius: 14px;
        padding: 0.85rem 2rem;
        font-weight: 700;
        font-size: 1rem;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
    }

    .btn-save-company:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        color: white;
    }

    .btn-cancel-link {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 0.85rem 2rem;
        font-weight: 700;
        border-radius: 14px;
        text-align: center;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.25s;
        width: 100%;
    }

    .btn-cancel-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b;
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container form-outer-container">
    <!-- Header Block -->
    <div class="form-header-box">
        <div class="form-icon-circle">
            <i class="bi bi-pencil-square"></i>
        </div>
        <h1 class="form-title">แก้ไขข้อมูลสถานประกอบการ</h1>
        <p class="form-subtitle">ปรับปรุงแก้ไขรายละเอียดข้อมูลบริษัทคู่ค้าให้มีความสมบูรณ์เป็นปัจจุบัน</p>
    </div>

    <!-- Alert Dialogues -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 rounded-4 p-3 mb-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon fs-5"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 rounded-4 p-3 mb-4 border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($success) ?></div>
        </div>
    <?php endif; ?>

    <!-- Glass Card Form -->
    <?php if ($company): ?>
        <div class="glass-form-card">
            <form method="POST">
                <div class="mb-4">
                    <label for="name" class="form-label">ชื่อบริษัท / สถานประกอบการ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($company['name']) ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="address" class="form-label">ที่อยู่จัดตั้งบริษัท</label>
                    <textarea class="form-control" id="address" name="address"><?= e($company['address']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="contact_name" class="form-label">ชื่อผู้ติดต่อประสานงานหลัก <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($company['contact_name']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="contact_phone" class="form-label">เบอร์โทรศัพท์ติดต่อ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= e($company['contact_phone']) ?>" required>
                    </div>
                </div>

                <div class="mb-5">
                    <label for="contact_email" class="form-label">อีเมลผู้ติดต่อ <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($company['contact_email']) ?>" required>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="list.php" class="btn-cancel-link">
                            <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <button type="submit" class="btn-save-company">
                            <i class="bi bi-check-circle"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center rounded-4 p-4">
            <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
            ไม่สามารถโหลดรายละเอียดบริษัทชิ้นนี้ได้ หรือข้อมูลถูกนำออกไปแล้ว
        </div>
        <div class="text-center mt-3">
            <a href="list.php" class="btn-cancel-link max-width-200 mx-auto">
                <i class="bi bi-arrow-left"></i> กลับหน้ารวมบริษัท
            </a>
        </div>
    <?php endif; ?>
</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
include __DIR__ . '/../includes/footer_close.php';
?>