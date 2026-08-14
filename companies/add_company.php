<?php
// companies/add_company.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_role(['staff']);

$page_title = 'เพิ่มสถานประกอบการ';
include __DIR__ . '/../includes/header_public.php';

$error = '';
$success = '';
$name = '';
$address = '';
$contact_name = '';
$contact_phone = '';
$contact_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับและทำความสะอาดข้อมูล
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $contact_name = trim(isset($_POST['contact_name']) ? $_POST['contact_name'] : '');
    $contact_phone = trim(isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '');
    $contact_email = trim(isset($_POST['contact_email']) ? $_POST['contact_email'] : '');
    $created_at = date('Y-m-d H:i:s');

    // ตรวจสอบข้อมูลพื้นฐาน
    if (empty($name) || empty($contact_name) || empty($contact_phone) || empty($contact_email)) {
        $error = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (*)";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลติดต่อไม่ถูกต้อง";
    } else {
        // ใช้ Prepared Statement สำหรับ INSERT
        global $conn;
        $stmt = $conn->prepare(
            "INSERT INTO companies (name, address, contact_name, contact_phone, contact_email, created_at) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $name, $address, $contact_name, $contact_phone, $contact_email, $created_at);
        
        if ($stmt->execute()) {
            $new_company_id = $conn->insert_id;
            echo "<script>
                setTimeout(function() {
                    window.location.href = '../companies/list.php?id=" . $new_company_id . "';
                }, 500);
            </script>";
            $success = "บันทึกข้อมูลบริษัท **" . htmlspecialchars($name) . "** เรียบร้อยแล้ว กำลังนำท่านกลับ...";
        } else {
            $errno = $conn->errno;
            $errmsg = $conn->error;
            if ($errno == 1062 || (is_string($errmsg) && stripos($errmsg, 'Duplicate') !== false)) {
                $error = "ข้อมูลบริษัทหรืออีเมลติดต่อซ้ำในระบบแล้ว กรุณาตรวจสอบ";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล กรุณาลองใหม่อีกครั้ง";
            }
        }
        $stmt->close();
    }
}
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
            <i class="bi bi-building-plus"></i>
        </div>
        <h1 class="form-title">เพิ่มสถานประกอบการใหม่</h1>
        <p class="form-subtitle">สร้างสารบบข้อมูลบริษัทคู่ค้าที่สนับสนุนการฝึกอาชีวศึกษาระบบทวิภาคี</p>
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
    <div class="glass-form-card">
        <form method="POST">
            <div class="mb-4">
                <label for="name" class="form-label">ชื่อบริษัท / สถานประกอบการ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" placeholder="ระบุชื่อบริษัทคู่ค้าหลัก..." value="<?= e($name) ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="address" class="form-label">ที่อยู่จัดตั้งบริษัท</label>
                <textarea class="form-control" id="address" name="address" placeholder="เลขที่, ถนน, ตำบล, อำเภอ, จังหวัด..."><?= e($address) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="contact_name" class="form-label">ชื่อผู้ติดต่อประสานงานหลัก <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contact_name" name="contact_name" placeholder="ระบุชื่อผู้ติดต่อ..." value="<?= e($contact_name) ?>" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label for="contact_phone" class="form-label">เบอร์โทรศัพท์ติดต่อ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" placeholder="ระบุเบอร์โทรศัพท์ติดต่อ..." value="<?= e($contact_phone) ?>" required>
                </div>
            </div>

            <div class="mb-5">
                <label for="contact_email" class="form-label">อีเมลผู้ติดต่อ <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="contact_email" name="contact_email" placeholder="ระบุอีเมลหลักผู้ประสานงาน..." value="<?= e($contact_email) ?>" required>
            </div>
            
            <div class="row g-3">
                <div class="col-sm-6">
                    <a href="list.php" class="btn-cancel-link">
                        <i class="bi bi-x-circle"></i> ยกเลิกขั้นตอน
                    </a>
                </div>
                <div class="col-sm-6">
                    <button type="submit" class="btn-save-company">
                        <i class="bi bi-check-circle"></i> บันทึกข้อมูลบริษัท
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
include __DIR__ . '/../includes/footer_close.php';
?>