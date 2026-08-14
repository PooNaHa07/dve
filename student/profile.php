<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

$u = current_user();
$student_id = $u['id'];

// 2. ดึงข้อมูลปัจจุบันของนักเรียน
$curr_res = $conn->query("SELECT * FROM users WHERE id = $student_id");
$curr = $curr_res->fetch_assoc();

// ดึงข้อมูลการตั้งค่าแจ้งเตือน
$notif_set_res = $conn->query("SELECT * FROM user_notification_settings WHERE user_id = $student_id LIMIT 1");
$notif_set = $notif_set_res ? $notif_set_res->fetch_assoc() : null;
if (!$notif_set) {
    $conn->query("INSERT IGNORE INTO user_notification_settings (user_id, enable_web, enable_line, enable_email) VALUES ($student_id, 1, 0, 0)");
    $notif_set = ['enable_web' => 1, 'enable_line' => 0, 'enable_email' => 0, 'line_token' => null];
}
// บังคับแสดงผลเป็นปิดชั่วคราว
$notif_set['enable_line'] = 0;
$notif_set['enable_email'] = 0;

// 1. บันทึกข้อมูลเมื่อมีการกดปุ่ม Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $classroom_id = $_POST['classroom_id']; 
    $company_name = $_POST['company_name'];
    $company_address = $_POST['company_address'];
    $trainer_name = $_POST['trainer_name'];
    $trainer_phone = $_POST['trainer_phone']; 
    $company_manager = $_POST['company_manager']; 
    $student_code = $_POST['student_code'];
    // company_id จาก live-search (0 = พิมพ์เองโดยไม่เลือก)
    $company_id_fk = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    if ($company_id_fk <= 0) $company_id_fk = null;
    $branch_id_fk = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    if ($branch_id_fk <= 0) $branch_id_fk = null;

    $profile_image = $curr['profile_image']; // ค่ายังคงเดิมถ้าไม่มีการเลือกรูปใหม่
    
    // ตรวจสอบภาพจากระบบ Camera Direct Base64 ก่อน (ชัวร์ที่สุดบน Mobile)
    if (!empty($_POST['captured_image_base64'])) {
        $img_str = $_POST['captured_image_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $img_str, $type)) {
            $data = substr($img_str, strpos($img_str, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $file_name = 'avatar_cam_' . uniqid() . '.jpg';
                $upload_dir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (file_put_contents($upload_dir . $file_name, $data)) {
                    replace_old_avatar($curr['profile_image']);
                    $profile_image = $file_name;
                }
            }
        }
    }
    // หากไม่มี Base64 จึงค่อยประมวลผลจากการอัปโหลดไฟล์มาตรฐาน
    else if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = upload_file($_FILES['profile_image'], ['jpg', 'jpeg', 'png', 'webp', 'gif'], __DIR__ . '/../uploads/avatars', 10*1024*1024);
        if ($uploaded !== null) {
            replace_old_avatar($curr['profile_image']);
            $profile_image = $uploaded;
        }
    }

    $sql = "UPDATE users SET 
            fullname = ?, 
            phone = ?, 
            classroom_id = ?, 
            company_id = ?,
            branch_id = ?,
            company_name = ?, 
            company_address = ?, 
            trainer_name = ?, 
            trainer_phone = ?, 
            company_manager = ?,
            student_code = ?,
            profile_image = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiisssssssi", $fullname, $phone, $classroom_id, $company_id_fk, $branch_id_fk, $company_name, $company_address, $trainer_name, $trainer_phone, $company_manager, $student_code, $profile_image, $student_id);
    
    $profile_ok = $stmt->execute();
    if ($profile_ok) {
        $curr['fullname'] = $fullname;
        $curr['phone'] = $phone;
        $curr['classroom_id'] = $classroom_id;
        $curr['company_id'] = $company_id_fk;
        $curr['branch_id'] = $branch_id_fk;
        $curr['company_name'] = $company_name;
        $curr['trainer_name'] = $trainer_name;
        $curr['trainer_phone'] = $trainer_phone;
        $curr['company_manager'] = $company_manager;
        $curr['student_code'] = $student_code;
        $curr['profile_image'] = $profile_image;
    }

    // บันทึกการตั้งค่าการแจ้งเตือน
    $enable_web = isset($_POST['enable_web']) ? 1 : 0;
    $enable_line = 0; // ปิดใช้งานชั่วคราวตามนโยบาย LINE Notify ยกเลิกให้บริการ
    $enable_email = 0; // ปิดใช้งานชั่วคราว
    $line_token = isset($_POST['line_token']) ? trim($_POST['line_token']) : '';

    $stmt_notif = $conn->prepare("INSERT INTO user_notification_settings (user_id, enable_web, enable_line, enable_email, line_token) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enable_web = VALUES(enable_web), enable_line = VALUES(enable_line), enable_email = VALUES(enable_email), line_token = VALUES(line_token)");
    if ($stmt_notif) {
        $stmt_notif->bind_param("iiiis", $student_id, $enable_web, $enable_line, $enable_email, $line_token);
        $stmt_notif->execute();
        $stmt_notif->close();
    }
    
    $notif_set['enable_web'] = $enable_web;
    $notif_set['enable_line'] = $enable_line;
    $notif_set['enable_email'] = $enable_email;
    $notif_set['line_token'] = $line_token;

    if ($profile_ok) {
        $show_success_alert = true;
    }
}

// 3. ดึงรายชื่อห้องเรียน
$class_res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");

// 4. ดึงรายชื่อครูนิเทศก์ (ลบออกตามคำขอ)

$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student-premium.css">
<style>
    .profile-banner-premium {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--accent) 100%);
        border-radius: var(--radius-lg);
        position: relative;
        overflow: hidden;
        margin-bottom: -30px;
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.25);
    }
    
    .avatar-wrapper-premium {
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 4px solid rgba(255, 255, 255, 0.8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .avatar-wrapper-premium:hover {
        transform: rotate(10deg) scale(1.08);
        border-color: var(--accent);
        box-shadow: 0 15px 35px rgba(217, 70, 239, 0.4);
    }

    .input-group-premium {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-group-premium .form-control, 
    .input-group-premium .form-select {
        padding-left: 3rem !important;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .input-group-premium .form-control:focus, 
    .input-group-premium .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.15);
        background: white;
    }

    .input-icon {
        position: absolute;
        left: 1.25rem;
        color: var(--primary);
        font-size: 1.2rem;
        z-index: 5;
        transition: all 0.3s ease;
    }

    .input-group-premium:focus-within .input-icon {
        color: var(--accent);
        transform: scale(1.1);
    }

    /* Tab styles */
    .premium-tab-nav {
        display: flex;
        gap: 1rem;
        background: rgba(241, 245, 249, 0.8);
        padding: 0.5rem;
        border-radius: 1.5rem;
        border: 1px solid rgba(0,0,0,0.03);
    }

    .premium-tab-btn {
        flex: 1;
        border: none;
        background: transparent;
        padding: 1rem;
        border-radius: 1.25rem;
        font-weight: 700;
        color: var(--secondary);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .premium-tab-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }

    /* Progress bar */
    .progress-container-premium {
        background: rgba(255, 255, 255, 0.4);
        padding: 1.25rem;
        border-radius: 1.5rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .shimmer-progress {
        height: 10px;
        border-radius: 10px;
        background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
        position: relative;
        overflow: hidden;
    }

    .shimmer-progress::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transform: translateX(-100%);
        animation: shimmer-load 2s infinite;
    }

    @keyframes shimmer-load {
        100% { transform: translateX(100%); }
    }

    .btn-premium-submit {
        background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        border: none;
        border-radius: 1.5rem;
        padding: 1.25rem 3.5rem;
        font-weight: 800;
        color: white;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.35);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .btn-premium-submit:hover {
        transform: translateY(-3px) scale(1.03);
        box-shadow: 0 20px 40px rgba(217, 70, 239, 0.45);
        color: white;
    }

    /* Premium Image Viewer Modal */
    .premium-image-modal {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .premium-image-modal.show {
        display: flex;
        opacity: 1;
    }
    .modal-image-content {
        max-width: 90%;
        max-height: 80vh;
        border-radius: 1.5rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 4px solid rgba(255, 255, 255, 0.2);
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        object-fit: contain;
    }
    .premium-image-modal.show .modal-image-content {
        transform: scale(1);
    }
    .modal-image-close {
        position: absolute;
        top: 2rem;
        right: 2rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 1.5rem;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        backdrop-filter: blur(5px);
    }
    .modal-image-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1) rotate(90deg);
    }
    .avatar-wrapper-premium, .avatar-preview-wrapper {
        cursor: pointer;
    }

    /* Premium Alert Toast Styles */
    .premium-alert-toast {
        position: fixed;
        top: 2rem;
        right: 2rem;
        z-index: 10000;
        background: rgba(255, 255, 255, 0.95);
        border-left: 5px solid #ef4444;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        max-width: 420px;
        transform: translateY(-20px) scale(0.95);
        opacity: 0;
        pointer-events: none;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .premium-alert-toast.show {
        transform: translateY(0) scale(1);
        opacity: 1;
        pointer-events: auto;
    }
    .input-error-pulse {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15) !important;
        animation: error-shake 0.4s ease-in-out;
    }
    @keyframes error-shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-4px); }
        75% { transform: translateX(4px); }
    }
    /* Premium Glassmorphic SweetAlert2 Styles */
    .premium-swal-popup {
        border-radius: 2rem !important;
        padding: 3rem 2.5rem !important;
        background: rgba(255, 255, 255, 0.65) !important;
        backdrop-filter: blur(25px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
        border: 1px solid rgba(255, 255, 255, 0.45) !important;
        box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.15), 
                    inset 0 1px 0 rgba(255, 255, 255, 0.6) !important;
        font-family: 'Prompt', 'Inter', sans-serif !important;
        animation: glass-fade-scale 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    }
    .premium-swal-title {
        color: #1e1b4b !important;
        font-size: 1.7rem !important;
        font-weight: 800 !important;
        margin-bottom: 0.75rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.75rem !important;
        letter-spacing: -0.02em !important;
    }
    .premium-swal-title i {
        color: #10b981 !important;
        font-size: 2.2rem !important;
        text-shadow: 0 0 15px rgba(16, 185, 129, 0.3) !important;
        animation: scale-up-bounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .premium-swal-text {
        color: #374151 !important;
        font-size: 1.1rem !important;
        line-height: 1.7 !important;
        font-weight: 500 !important;
    }
    .premium-swal-confirm-btn {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.85) 0%, rgba(168, 85, 247, 0.85) 100%) !important;
        backdrop-filter: blur(5px) !important;
        -webkit-backdrop-filter: blur(5px) !important;
        color: white !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        border-radius: 1.25rem !important;
        padding: 0.85rem 3rem !important;
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        margin-top: 1.75rem !important;
        box-shadow: 0 10px 20px -5px rgba(168, 85, 247, 0.3) !important;
    }
    .premium-swal-confirm-btn:hover {
        transform: translateY(-3px) scale(1.05) !important;
        box-shadow: 0 15px 30px rgba(168, 85, 247, 0.45) !important;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%) !important;
    }
    .premium-swal-confirm-btn:active {
        transform: translateY(1px) scale(0.97) !important;
    }
    @keyframes scale-up-bounce {
        0% { transform: scale(0.3); opacity: 0; }
        50% { transform: scale(1.1); }
        70% { transform: scale(0.9); }
        100% { transform: scale(1); opacity: 1; }
    }
    @keyframes glass-fade-scale {
        0% { transform: scale(0.85); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    /* Camera Modal Styles */
    .webcam-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .webcam-overlay.show {
        display: flex;
        opacity: 1;
    }
    .webcam-window {
        background: white;
        border-radius: 2rem;
        width: 90%;
        max-width: 480px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .webcam-overlay.show .webcam-window {
        transform: scale(1);
    }
    .webcam-preview-container {
        width: 100%;
        aspect-ratio: 3/4;
        border-radius: 1.5rem;
        overflow: hidden;
        background: #000;
        position: relative;
        margin-bottom: 1.5rem;
        border: 4px solid #f1f5f9;
    }
    #webcam-video, #webcam-canvas {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scaleX(-1); /* Mirror */
    }
    #webcam-canvas {
        display: none;
    }

    /* Premium Notification Settings Styles */
    .card-premium-switch {
        background: rgba(255, 255, 255, 0.45);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.65);
        border-radius: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .card-premium-switch:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.85);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05) !important;
        border-color: var(--primary);
    }
    .switch-icon-circle {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    .card-premium-switch:hover .switch-icon-circle {
        transform: scale(1.1) rotate(5deg);
    }
    .custom-premium-switch .form-check-input {
        width: 3.25rem;
        height: 1.75rem;
        cursor: pointer;
        border-radius: 2rem;
        background-color: rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0,0,0,0.1);
        transition: background-position 0.2s ease-in-out, background-color 0.2s ease-in-out;
    }
    .custom-premium-switch .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }
    .custom-premium-switch .form-check-input:focus {
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
    }
    .custom-premium-switch .form-check-label {
        padding-top: 0.25rem;
        margin-left: 0.5rem;
        cursor: pointer;
    }
    @keyframes bell-ring {
        0%, 100% { transform: rotate(0); }
        15% { transform: rotate(15deg); }
        30% { transform: rotate(-15deg); }
        45% { transform: rotate(10deg); }
        60% { transform: rotate(-10deg); }
        75% { transform: rotate(5deg); }
        85% { transform: rotate(-5deg); }
    }
    .animate-bell {
        display: inline-block;
    }
    .premium-tab-btn:hover .animate-bell {
        animation: bell-ring 1s ease-in-out;
    }

    /* Responsive adjustments for Alert Toasts on Mobile */
    @media (max-width: 575.98px) {
        .premium-alert-toast {
            top: 16px !important;
            right: 16px !important;
            left: 16px !important;
            max-width: none !important;
            width: auto !important;
            padding: 1rem 1.25rem !important;
            transform: translateY(-20px) scale(0.98) !important;
        }
        .premium-alert-toast.show {
            transform: translateY(0) scale(1) !important;
        }
    }
</style>

<div class="dashboard-page">
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="container py-5">
        <div class="mb-5 animate-fade-in d-flex justify-content-between align-items-center">
            <a href="../roles/student.php" class="btn btn-link text-decoration-none p-0 back-link fw-bold text-secondary">
                <i class="bi bi-arrow-left-circle-fill fs-4 me-2"></i> กลับหน้าหลัก
            </a>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                <i class="bi bi-shield-check me-1"></i> ยืนยันตัวตนแล้ว
            </span>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <!-- Profile Header -->
                <div class="profile-banner-premium shadow-lg animate-fade-in mb-4">
                    <div class="banner-overlay"></div>
                    <div class="banner-content p-4 p-md-5 text-white">
                        <div class="d-flex flex-column flex-md-row align-items-center gap-4 text-center text-md-start">
                             <div class="avatar-wrapper-premium" onclick="openImageModal(this.querySelector('img'))" style="position: relative; overflow: hidden; width: 100px; height: 100px; border-radius: 50%;">
                                <?php if (!empty($curr['profile_image'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($curr['profile_image']) ?>?v=<?= time() ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="bi bi-person-circle fs-1 text-white" style="font-size: 3rem;"></i>
                                <?php endif; ?>
                             </div>
                            <div>
                                <span class="badge px-3 py-1 rounded-pill mb-2 fw-bold text-uppercase tracking-wider small" style="background: var(--accent); color: white; box-shadow: 0 4px 12px rgba(217, 70, 239, 0.4);">
                                    Student Profile
                                </span>
                                <h2 class="fw-extrabold mb-1 display-6 text-white"><?= htmlspecialchars($curr['fullname']) ?></h2>
                                <p class="mb-0 opacity-75">
                                    <i class="bi bi-journal-bookmark me-1"></i> นักศึกษาฝึกประสบการณ์วิชาชีพ | วิทยาลัยอาชีวศึกษาเพชรบุรี
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Progress Rate (Dynamic Calculation) -->
                <?php
                $profile_fields = [
                    $curr['fullname'],
                    $curr['phone'],
                    $curr['classroom_id'],
                    $curr['student_code'],
                    $curr['profile_image'],
                    $curr['company_name'],
                    $curr['trainer_name'],
                    $curr['trainer_phone'],
                    $curr['company_manager'],
                    $curr['company_address']
                ];
                $filled_fields = 0;
                foreach ($profile_fields as $fld) {
                    if (!empty($fld) && $fld != '0') {
                        $filled_fields++;
                    }
                }
                $completion_pct = round(($filled_fields / count($profile_fields)) * 100);
                $badge_class = $completion_pct === 100 ? 'bg-success' : 'bg-warning text-dark';
                ?>
                <div class="progress-container-premium mb-4 shadow-sm animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-secondary small"><i class="bi bi-award-fill text-warning me-1"></i> ความสมบูรณ์ของข้อมูลโปรไฟล์</span>
                        <span class="badge <?= $badge_class ?> rounded-pill fw-bold"><?= $completion_pct ?>% Complete</span>
                    </div>
                    <div class="progress-premium" style="height: 10px; background: rgba(0,0,0,0.05); border-radius: 5px; overflow: hidden;">
                        <div class="shimmer-progress" style="width: <?= $completion_pct ?>%;"></div>
                    </div>
                </div>

                <div class="glass-card p-4 p-md-5 mt-3 position-relative z-index-10 animate-slide-up">
                    <div class="text-center mb-5">
                        <h3 class="fw-bold mb-1">ตั้งค่าโปรไฟล์และข้อมูลการฝึกงาน</h3>
                        <p class="text-secondary">โปรดตรวจสอบและรักษาข้อมูลให้เป็นปัจจุบันเพื่อประโยชน์ในการประสานงาน</p>
                    </div>

                    <!-- Modern Tabs -->
                    <div class="premium-tab-nav mb-5 shadow-sm">
                        <button class="premium-tab-btn active" type="button" onclick="switchTab('personal-info-pane', this)">
                            <i class="bi bi-person-lines-fill"></i> ข้อมูลส่วนตัว
                        </button>
                        <button class="premium-tab-btn" type="button" onclick="switchTab('company-info-pane', this)">
                            <i class="bi bi-building-fill"></i> ข้อมูลการฝึกงาน
                        </button>
                        <button class="premium-tab-btn" type="button" onclick="switchTab('notification-settings-pane', this)">
                            <i class="bi bi-bell-fill animate-bell"></i> การแจ้งเตือน
                        </button>
                    </div>

                     <form id="profileForm" method="POST" enctype="multipart/form-data" novalidate>
                        <!-- Tab Content Panes -->
                        <div id="personal-info-pane" class="profile-tab-pane animate-fade-in">
                            <div class="d-flex align-items-center mb-4">
                                <div class="stat-icon-wrap me-3 bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                    <i class="bi bi-person-badge-fill fs-5"></i>
                                </div>
                                <h5 class="fw-bold mb-0">ข้อมูลส่วนตัวเบื้องต้น</h5>
                            </div>

                            <!-- Profile Image Upload Section -->
                            <div class="row align-items-center mb-5 p-4 rounded-4" style="background: rgba(99, 102, 241, 0.03); border: 1px dashed rgba(99, 102, 241, 0.15);">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <div class="position-relative d-inline-block">
                                        <div class="avatar-preview-wrapper" onclick="openImageModal(this.querySelector('img'))" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid white; box-shadow: 0 8px 25px rgba(99,102,241,0.25); background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                            <?php if (!empty($curr['profile_image'])): ?>
                                                <img id="avatar-preview" src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($curr['profile_image']) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <img id="avatar-preview" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                                <div id="avatar-placeholder" class="d-flex align-items-center justify-content-center h-100 text-secondary">
                                                    <i class="bi bi-person-fill" style="font-size: 3.5rem; color: var(--primary);"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-9 text-center text-md-start">
                                    <h6 class="fw-bold mb-1" style="color: var(--primary-dark);"><i class="bi bi-camera-fill me-1"></i> รูปโปรไฟล์ของคุณ (PROFILE PICTURE)</h6>
                                    <p class="text-muted small mb-3">รองรับไฟล์ภาพ JPG, PNG หรือ WebP ขนาดไม่เกิน 2MB เพื่อใช้แสดงในบัตรนักศึกษาทวิภาคีและเมนูด้านบน</p>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <div style="flex: 1; min-width: 200px;">
                                            <input type="file" name="profile_image" id="profile-image-input" class="form-control form-control-sm shadow-sm rounded-pill px-3" accept="image/*" onchange="previewImage(this)">
                                            <input type="hidden" name="captured_image_base64" id="captured-image-base64">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-bold" onclick="openWebcam()">
                                            <i class="bi bi-camera-fill me-1"></i> ถ่ายภาพ
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ชื่อ-นามสกุล</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-person input-icon"></i>
                                        <input type="text" name="fullname" class="form-control form-control-lg shadow-sm" value="<?= htmlspecialchars($curr['fullname']) ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">รหัสนักศึกษา</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-card-text input-icon"></i>
                                        <input type="text" name="student_code" class="form-control form-control-lg shadow-sm" value="<?= htmlspecialchars($curr['student_code'] ?? '') ?>" placeholder="ระบุรหัสนักศึกษา">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">เบอร์โทรศัพท์ติดต่อ</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-telephone input-icon"></i>
                                        <input type="text" name="phone" class="form-control form-control-lg shadow-sm" value="<?= htmlspecialchars($curr['phone']) ?>" placeholder="08x-xxx-xxxx">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ห้องเรียน / สาขาวิชา</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-door-open input-icon"></i>
                                        <select name="classroom_id" class="form-select form-select-lg shadow-sm" required>
                                            <option value="">-- เลือกห้องเรียน --</option>
                                            <?php while($class = $class_res->fetch_assoc()): ?>
                                                <option value="<?= $class['id'] ?>" <?= ($curr['classroom_id'] == $class['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($class['class_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- อาจารย์นิเทศก์ถูกถอนออกตามคำขอ ให้ครูเลือกห้องเอง -->
                            </div>
                        </div>

                        <div id="company-info-pane" class="profile-tab-pane animate-fade-in" style="display: none;">
                            <div class="d-flex align-items-center mb-4">
                                <div class="stat-icon-wrap me-3 bg-indigo-50 text-primary p-2 rounded-3">
                                    <i class="bi bi-building-fill-check fs-5"></i>
                                </div>
                                <h5 class="fw-bold mb-0">ข้อมูลสถานประกอบการและผู้รับผิดชอบ</h5>
                            </div>

                            <div class="mb-4" id="company-search-section">
                                <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ชื่อบริษัท / สถานที่ฝึกงาน</label>

                                <!-- Hidden FK fields -->
                                <input type="hidden" name="company_id" id="company_id_field" value="<?= (int)($curr['company_id'] ?? 0) ?>">
                                <input type="hidden" name="branch_id"  id="branch_id_field"  value="<?= (int)($curr['branch_id'] ?? 0) ?>">

                                <!-- Live-search wrapper -->
                                <div class="position-relative" id="company-search-wrapper">
                                    <div class="input-group-premium">
                                        <i class="bi bi-search input-icon" id="company-search-icon"></i>
                                        <input type="text"
                                               name="company_name"
                                               id="company_name_input"
                                               class="form-control form-control-lg shadow-sm"
                                               value="<?= htmlspecialchars($curr['company_name'] ?? '') ?>"
                                               required
                                               placeholder="พิมพ์ชื่อสถานประกอบการเพื่อค้นหา..."
                                               autocomplete="off">
                                    </div>
                                    <!-- Dropdown results -->
                                    <div id="company-dropdown"
                                         style="display:none; position:absolute; top:100%; left:0; right:0; z-index:9999;
                                                background:white; border:1.5px solid #e2e8f0; border-radius:1rem;
                                                box-shadow:0 20px 40px rgba(0,0,0,0.12); max-height:300px; overflow-y:auto;
                                                margin-top:6px;">
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted" id="company-selected-hint" style="<?= empty($curr['company_name']) ? 'display:none;' : '' ?>">
                                    <i class="bi bi-building-check text-success me-1"></i>
                                    เลือก: <strong id="company-selected-label"><?= htmlspecialchars($curr['company_name'] ?? '') ?></strong>
                                    &nbsp;<a href="#" onclick="clearCompanySelection();return false;" class="text-danger small">(ล้างการเลือก)</a>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ผู้มีอำนาจลงนามสูงสุด</label>
                                <div class="input-group-premium">
                                    <i class="bi bi-person-check-fill input-icon"></i>
                                    <input type="text" name="company_manager" class="form-control form-control-lg shadow-sm" 
                                           value="<?= htmlspecialchars($curr['company_manager'] ?? '') ?>" placeholder="ชื่อ-นามสกุล ผู้จัดการ/ผู้อำนวยการ">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ครูฝึก/ผู้ควบคุม</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-person-workspace input-icon"></i>
                                        <input type="text" name="trainer_name" class="form-control form-control-lg shadow-sm" 
                                               value="<?= htmlspecialchars($curr['trainer_name'] ?? '') ?>" placeholder="ชื่อ-นามสกุลครูฝึก">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">เบอร์โทรครูฝึก</label>
                                    <div class="input-group-premium">
                                        <i class="bi bi-phone-vibrate input-icon"></i>
                                        <input type="text" name="trainer_phone" class="form-control form-control-lg shadow-sm" 
                                               value="<?= htmlspecialchars($curr['trainer_phone'] ?? '') ?>" placeholder="เบอร์โทรติดต่อ">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-primary text-uppercase tracking-wider">ที่อยู่สถานประกอบการ</label>
                                <div class="input-group-premium">
                                    <i class="bi bi-geo-alt input-icon" style="top: 1.2rem;"></i>
                                    <textarea name="company_address" class="form-control shadow-sm" rows="4" style="padding-left: 3rem !important;" placeholder="ระบุเลขที่ ถนน ตำบล อำเภอ จังหวัด..."><?= htmlspecialchars($curr['company_address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings Tab Pane -->
                        <div id="notification-settings-pane" class="profile-tab-pane animate-fade-in" style="display: none;">
                            <div class="d-flex align-items-center mb-4">
                                <div class="stat-icon-wrap me-3 bg-warning bg-opacity-10 text-warning p-2 rounded-3">
                                    <i class="bi bi-bell-fill fs-5 text-warning"></i>
                                </div>
                                <h5 class="fw-bold mb-0">การตั้งค่ารับแจ้งเตือน (Notification Preferences)</h5>
                            </div>

                            <p class="text-muted small mb-4">เลือกช่องทางที่คุณต้องการเปิดรับการแจ้งเตือนจากระบบ (เช่น เมื่ออาจารย์นิเทศก์ตรวจรายงาน ข่าวประกาศจากวิทยาลัย หรือการประสานงานต่างๆ)</p>

                            <!-- Switches container with Premium glassmorphism cards -->
                            <div class="row">
                                <!-- Web notification -->
                                <div class="col-md-6 mx-auto mb-4">
                                    <div class="card-premium-switch shadow-sm h-100 p-4 d-flex flex-column justify-content-between">
                                        <div class="d-flex align-items-center mb-3 gap-3">
                                            <div class="switch-icon-circle bg-primary bg-opacity-10 text-primary">
                                                <i class="bi bi-globe fs-4 text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-extrabold mb-0 text-dark">ระบบเว็บแอป (In-App)</h6>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-bold" style="font-size: 0.7rem;">Real-time</span>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-3">แสดงผ่านไอคอนกระดิ่งแจ้งเตือนสีแดงด้านบนเมื่อล็อกอินเข้าใช้งานในระบบ</p>
                                        <div class="form-check form-switch custom-premium-switch mt-auto">
                                            <input class="form-check-input" type="checkbox" name="enable_web" id="enable_web_switch" value="1" <?= ($notif_set['enable_web'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold small text-secondary" for="enable_web_switch">เปิดการแจ้งเตือนในเว็บ</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email notification (Hidden) -->
                                <div class="col-md-4 mb-4" style="display: none !important;">
                                    <div class="card-premium-switch shadow-sm h-100 p-4 d-flex flex-column justify-content-between">
                                        <div class="d-flex align-items-center mb-3 gap-3">
                                            <div class="switch-icon-circle bg-info bg-opacity-10 text-info">
                                                <i class="bi bi-envelope-fill fs-4 text-info"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-extrabold mb-0 text-dark">อีเมล (Email)</h6>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill fw-bold" style="font-size: 0.7rem;">Official</span>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-3">ส่งการแจ้งเตือนไปยังอีเมลหลักของคุณ (กรณีมีข้อมูลสำคัญหรือสถิติต่างๆ)</p>
                                        <div class="form-check form-switch custom-premium-switch mt-auto mb-2">
                                            <input class="form-check-input" type="checkbox" name="enable_email" id="enable_email_switch" value="1" disabled>
                                            <label class="form-check-label fw-bold small text-secondary" for="enable_email_switch">เปิดการแจ้งเตือนผ่านอีเมล</label>
                                        </div>
                                        <div class="small fw-bold text-warning"><i class="bi bi-exclamation-triangle-fill"></i> ปิดปรับปรุงชั่วคราว</div>
                                    </div>
                                </div>

                                <!-- LINE Notify notification (Hidden) -->
                                <div class="col-md-4 mb-4" style="display: none !important;">
                                    <div class="card-premium-switch shadow-sm h-100 p-4 d-flex flex-column justify-content-between">
                                        <div class="d-flex align-items-center mb-3 gap-3">
                                            <div class="switch-icon-circle bg-success bg-opacity-10 text-success">
                                                <i class="bi bi-line fs-4 text-success"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-extrabold mb-0 text-dark">LINE Notify</h6>
                                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill fw-bold" style="font-size: 0.7rem;">Instant</span>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-3">ส่งข้อความแจ้งเตือนตรงเข้าแอป LINE ส่วนตัวของคุณ ทันทีที่มีการเคลื่อนไหว</p>
                                        <div class="form-check form-switch custom-premium-switch mt-auto mb-2">
                                            <input class="form-check-input" type="checkbox" name="enable_line" id="enable_line_switch" value="1" disabled>
                                            <label class="form-check-label fw-bold small text-secondary" for="enable_line_switch">เปิดการแจ้งเตือนผ่าน LINE</label>
                                        </div>
                                        <div class="small fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ปิดบริการ (LINE ยกเลิกให้บริการ)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- LINE Notify Token Input Section (Only visible if LINE is enabled) - Hidden completely -->
                            <div id="line-notify-token-section" class="mt-4 p-4 rounded-4 animate-fade-in" style="background: rgba(22, 163, 74, 0.03); border: 1.5px solid rgba(22, 163, 74, 0.08); display: none !important;">
                                <h6 class="fw-bold mb-3 text-success d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-lock-fill"></i> ตั้งค่าการเชื่อมต่อ LINE Notify
                                </h6>
                                
                                <div class="row align-items-end">
                                    <div class="col-md-8 mb-3 mb-md-0">
                                        <label class="form-label small fw-bold text-success text-uppercase tracking-wider">LINE Notify Access Token</label>
                                        <div class="input-group-premium">
                                            <i class="bi bi-key-fill input-icon text-success"></i>
                                            <input type="password" name="line_token" id="line_token_input" class="form-control form-control-lg shadow-sm" value="<?= htmlspecialchars($notif_set['line_token'] ?? '') ?>" placeholder="ใส่ Access Token ของคุณเพื่อรับแจ้งเตือน">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('line_token_input', this)" style="border-radius: 0 1.25rem 1.25rem 0; border: 1px solid rgba(0,0,0,0.08); background: rgba(255, 255, 255, 0.65); border-left: none; padding-right: 1.25rem; z-index: 10;">
                                                <i class="bi bi-eye-slash-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-lg btn-success rounded-pill px-4 shadow-sm w-100 fw-bold d-flex align-items-center justify-content-center gap-2" id="test-line-btn" onclick="testLineConnection()">
                                            <i class="bi bi-send-fill"></i> ทดสอบส่งข้อความ
                                        </button>
                                    </div>
                                </div>

                                <!-- LINE Notify step by step instructions card -->
                                <div class="mt-4 p-4 rounded-4" style="background: white; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                                    <h6 class="fw-bold mb-3 text-dark small text-uppercase tracking-wider"><i class="bi bi-info-circle text-primary me-1"></i> ขั้นตอนการรับ LINE Token เพื่อรับแจ้งเตือนฟรี!</h6>
                                    <ol class="small text-secondary mb-0 ps-3" style="line-height: 1.8;">
                                        <li>ไปที่เว็บไซต์ <a href="https://notify-bot.line.me/" target="_blank" class="text-decoration-none fw-bold text-primary"><i class="bi bi-box-arrow-up-right"></i> LINE Notify Portal</a> แล้วเข้าสู่ระบบด้วยบัญชี LINE ของคุณ</li>
                                        <li>ไปที่ **"หน้าของฉัน" (My Page)** จากเมนูด้านขวาบน</li>
                                        <li>คลิกที่ปุ่ม **"ออกโทเคน" (Generate Token)** ด้านล่าง</li>
                                        <li>ระบุชื่อผู้รับแจ้งเตือน (เช่น <i>DVE Notification</i>) และเลือกห้องแชทที่ต้องการรับข้อความ (เลือก **"รับการแจ้งเตือนแบบตัวต่อตัวจาก LINE Notify"** เพื่อรับเฉพาะส่วนตัว)</li>
                                        <li>คลิกปุ่มสีเขียว **"ออกโทเคน" (Generate)** จากนั้นทำการ **คัดลอก (Copy)** โทเคนนั้นไว้</li>
                                        <li>นำโทเคนมาวางในช่องด้านบนนี้ กด **ทดสอบส่งข้อความ** และกด **บันทึกการเปลี่ยนแปลง** ด้านล่าง</li>
                                    </ol>
                                    <div class="alert alert-warning border-0 rounded-3 mt-3 mb-0 py-2 small d-flex align-items-center gap-2" style="background: rgba(245, 158, 11, 0.08); color: #d97706; border: none;">
                                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0"></i>
                                        <span><strong>ข้อแนะนำ:</strong> อย่าลืมเพิ่มเพื่อนบัญชี <strong>LINE Notify</strong> ในแอป LINE ของคุณด้วย มิฉะนั้นจะไม่ได้รับข้อความแจ้งเตือน!</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5 opacity-10">

                        <div class="text-center">
                            <button type="submit" name="update_profile" class="btn btn-premium-submit px-5 py-3 fs-5 shadow-lg">
                                <i class="bi bi-cloud-check-fill me-2"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- View Image Modal -->
<div id="premiumImageModal" class="premium-image-modal" onclick="closeImageModal()">
    <div class="modal-image-close" onclick="closeImageModal()">
        <i class="bi bi-x-lg"></i>
    </div>
    <img id="modalImgTarget" src="" class="modal-image-content" onclick="event.stopPropagation()">
</div>

<!-- Webcam Modal -->
<div id="webcamModal" class="webcam-overlay">
    <div class="webcam-window" onclick="event.stopPropagation()">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-camera-fill text-primary me-2"></i>ถ่ายรูปโปรไฟล์</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-none" id="flip-camera-btn" onclick="toggleCamera()" style="width: 32px; height: 32px; padding: 0;">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
                <button type="button" class="btn-close" onclick="closeWebcam()"></button>
            </div>
        </div>
        
        <div class="webcam-preview-container">
            <video id="webcam-video" autoplay playsinline muted></video>
            <canvas id="webcam-canvas"></canvas>
        </div>
        
        <div class="d-flex gap-2 justify-content-center" id="camera-controls">
            <button type="button" class="btn btn-danger btn-lg rounded-pill px-4 py-2 fw-bold shadow" onclick="takeSnapshot()">
                <i class="bi bi-record-circle-fill me-2"></i> ถ่ายภาพ
            </button>
        </div>
        
        <div class="d-flex gap-3 justify-content-center d-none" id="result-controls">
            <button type="button" class="btn btn-light border rounded-pill px-3 fw-bold" onclick="retakePhoto()">
                <i class="bi bi-arrow-counterclockwise me-1"></i> ถ่ายใหม่
            </button>
            <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow" onclick="savePhoto()">
                <i class="bi bi-check-circle-fill me-1"></i> ยืนยันรูปนี้
            </button>
        </div>
    </div>
</div>

<!-- Premium Form Validation Alert Toast -->
<div id="premiumAlertToast" class="premium-alert-toast d-flex align-items-start gap-3">
    <div class="text-danger flex-shrink-0" style="font-size: 1.5rem; line-height: 1;">
        <i class="bi bi-exclamation-triangle-fill"></i>
    </div>
    <div class="flex-grow-1">
        <h6 class="fw-bold text-danger mb-1" id="alertToastTitle">กรอกข้อมูลไม่ครบถ้วน</h6>
        <div class="text-secondary small mb-0" id="alertToastContent">โปรดตรวจสอบช่องข้อมูลที่จำเป็น</div>
    </div>
    <button type="button" class="btn-close flex-shrink-0" onclick="closeAlertToast()" aria-label="Close" style="font-size: 0.75rem; margin-top: 0.2rem; border: none; background: transparent;"></button>
</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap bundle is already in footer.php -->
<script>
    /* ================================================================
     * Company Live-Search & Auto-Fill
     * ================================================================ */
    (function() {
        const BASE = '<?= BASE_URL ?>';
        const CLASSROOM_ID = <?= (int)($curr['classroom_id'] ?? 0) ?>;

        const inp        = document.getElementById('company_name_input');
        const dropdown   = document.getElementById('company-dropdown');
        const cid_field  = document.getElementById('company_id_field');
        const bid_field  = document.getElementById('branch_id_field');
        const hint       = document.getElementById('company-selected-hint');
        const hint_label = document.getElementById('company-selected-label');

        if (!inp) return; // guard

        let debounceTimer = null;
        let lastXhr       = null;

        /* --- helpers --- */
        function getField(name) { return document.querySelector('[name="' + name + '"]'); }

        function autoFill(data) {
            if (!data) return;
            // Company name (already in input from selection)
            if (data.company_address) {
                const addr = getField('company_address');
                if (addr && !addr.value) addr.value = data.company_address;
            }
            if (data.address) {
                const addr = getField('company_address');
                if (addr) addr.value = data.address;
            }
            if (data.contact_name) {
                const tn = getField('trainer_name');
                if (tn && !tn.value) tn.value = data.contact_name;
            }
            if (data.contact_phone) {
                const tp = getField('trainer_phone');
                if (tp && !tp.value) tp.value = data.contact_phone;
            }
            if (data.manager_name) {
                const mg = getField('company_manager');
                if (mg && !mg.value) mg.value = data.manager_name;
            }
        }

        function showHint(label) {
            if (!hint || !hint_label) return;
            hint_label.textContent = label;
            hint.style.display = '';
        }

        function closeDropdown() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

        /* --- clear selection --- */
        window.clearCompanySelection = function() {
            inp.value        = '';
            cid_field.value  = '0';
            bid_field.value  = '';
            if (hint) hint.style.display = 'none';
            closeDropdown();
            inp.focus();
        };

        /* --- render dropdown items --- */
        function renderResults(items) {
            dropdown.innerHTML = '';
            if (!items || items.length === 0) {
                dropdown.innerHTML =
                    '<div style="padding:1rem 1.25rem; color:#94a3b8; font-size:0.9rem;">' +
                    '<i class="bi bi-search me-2"></i>ไม่พบสถานประกอบการที่ตรงกัน</div>';
                dropdown.style.display = 'block';
                return;
            }

            /* Group by company_id for nicer display */
            let html = '';
            let lastCid = null;
            items.forEach(function(item) {
                const isBranch = item.branch_id !== null && item.branch_id !== undefined;
                const cid      = item.company_id;

                if (cid !== lastCid) {
                    if (lastCid !== null) html += '<div style="height:1px; background:#f1f5f9; margin:0.25rem 0;"></div>';
                    lastCid = cid;
                }

                /* Badge: match student's classroom? */
                const matchClass = item.classroom_id && CLASSROOM_ID && parseInt(item.classroom_id) === CLASSROOM_ID;
                const badge = matchClass
                    ? '<span style="background:#dcfce7;color:#16a34a;border-radius:6px;padding:2px 8px;font-size:0.72rem;font-weight:700;margin-left:6px;">แผนกของคุณ</span>'
                    : '';

                const branchBadge = isBranch && item.branch_label
                    ? '<span style="background:#ede9fe;color:#7c3aed;border-radius:6px;padding:2px 8px;font-size:0.72rem;font-weight:600;margin-left:6px;">สาขา: ' + escHtml(item.branch_label) + '</span>'
                    : '';

                const classText = item.class_name
                    ? '<div style="font-size:0.8rem;color:#64748b;margin-top:2px;"><i class="bi bi-mortarboard-fill" style="color:#6366f1"></i> ' + escHtml(item.class_name) + '</div>'
                    : '';

                const addrText = item.company_address
                    ? '<div style="font-size:0.8rem;color:#94a3b8;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:90%;"><i class="bi bi-geo-alt"></i> ' + escHtml(item.company_address.substring(0, 60) + (item.company_address.length > 60 ? '…' : '')) + '</div>'
                    : '';

                html +=
                    '<div class="company-result-item" ' +
                    'data-cid="' + cid + '" data-bid="' + (item.branch_id || '') + '" ' +
                    'data-label="' + escAttr(item.company_name + (item.branch_label ? ' – ' + item.branch_label : '')) + '" ' +
                    'style="padding:0.85rem 1.25rem; cursor:pointer; transition:background 0.15s; border-radius:0.75rem; margin:4px 6px;" ' +
                    'onmouseenter="this.style.background=\'#f8faff\'" ' +
                    'onmouseleave="this.style.background=\'\'" ' +
                    'onclick="selectCompany(this)">' +
                        '<div style="font-weight:700;color:#1e293b;">' + escHtml(item.company_name) + badge + branchBadge + '</div>' +
                        classText + addrText +
                    '</div>';
            });

            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
        }

        /* --- select a result --- */
        window.selectCompany = function(el) {
            const cid   = el.getAttribute('data-cid');
            const bid   = el.getAttribute('data-bid');
            const label = el.getAttribute('data-label');

            inp.value       = el.querySelector('div').textContent.replace(/\s*(แผนกของคุณ|สาขา:.*)/g,'').trim();
            cid_field.value = cid || '0';
            bid_field.value = bid  || '';
            showHint(label);
            closeDropdown();

            // Fetch full detail for auto-fill
            let url = BASE + '/companies/ajax_search_companies.php?company_id=' + cid;
            if (bid) url += '&branch_id=' + bid;

            fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) { autoFill(data); })
                .catch(function() {});
        };

        /* --- key input with debounce --- */
        inp.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const q = inp.value.trim();
            if (q.length < 1) { closeDropdown(); return; }

            debounceTimer = setTimeout(function() {
                if (lastXhr) { lastXhr.abort && lastXhr.abort(); }

                let url = BASE + '/companies/ajax_search_companies.php?q=' + encodeURIComponent(q);
                if (CLASSROOM_ID) url += '&classroom_id=' + CLASSROOM_ID;

                dropdown.innerHTML = '<div style="padding:1rem 1.25rem; color:#6366f1; font-size:0.9rem;"><i class="bi bi-hourglass-split me-2"></i>กำลังค้นหา...</div>';
                dropdown.style.display = 'block';

                fetch(url, { credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) { renderResults(data); })
                    .catch(function() { closeDropdown(); });
            }, 280);
        });

        /* --- close dropdown on outside click --- */
        document.addEventListener('click', function(e) {
            if (!document.getElementById('company-search-wrapper').contains(e.target)) {
                closeDropdown();
            }
        });

        /* --- ESC key --- */
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDropdown();
        });

        /* --- Utilities --- */
        function escHtml(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function escAttr(str) {
            return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    })();
    /* ================================================================ */

    function switchTab(paneId, btn) {
        // Hide all panes
        document.querySelectorAll('.profile-tab-pane').forEach(function(pane) {
            pane.style.display = 'none';
        });
        // Show selected pane
        document.getElementById(paneId).style.display = 'block';
        
        // Update active class on buttons
        document.querySelectorAll('.premium-tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        btn.classList.add('active');
    }

    function previewImage(input) {
        const preview = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');
        
        // หากผู้ใช้เลือกไฟล์ด้วยตัวเองผ่านเบราว์เซอร์ ให้ล้างค่า Base64 ที่มาจากการถ่ายรูปล่าสุดออกด้วย
        const hiddenInput = document.getElementById('captured-image-base64');
        if (hiddenInput && input.id === 'profile-image-input') {
            hiddenInput.value = "";
        }

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openImageModal(imgElement) {
        if (!imgElement || !imgElement.src || imgElement.style.display === 'none') return;
        const modal = document.getElementById('premiumImageModal');
        const modalImg = document.getElementById('modalImgTarget');
        modalImg.src = imgElement.src;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent scrolling background
    }

    function closeImageModal() {
        const modal = document.getElementById('premiumImageModal');
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
    }

    let webcamStream = null;
    let currentFacingMode = "user"; 

    // เพิ่มระบบสลับเลนส์กล้อง
    async function toggleCamera() {
        currentFacingMode = (currentFacingMode === "user") ? "environment" : "user";
        
        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
        }
        
        const video = document.getElementById('webcam-video');
        const canvas = document.getElementById('webcam-canvas');
        
        // ปรับการสะท้อน (Mirroring) ตามโหมดที่ใช้
        if (currentFacingMode === "user") {
            video.style.transform = "scaleX(-1)";
            canvas.style.transform = "scaleX(-1)";
        } else {
            video.style.transform = "scaleX(1)";
            canvas.style.transform = "scaleX(1)";
        }

        try {
            webcamStream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: currentFacingMode,
                    width: { ideal: 1024 }, 
                    height: { ideal: 1024 }
                }, 
                audio: false 
            });
            video.srcObject = webcamStream;
        } catch (err) {
            console.error("สลับกล้องล้มเหลว:", err);
        }
    }

    function openNativeMobileCameraFallback() {
        let input = document.getElementById('native-camera-fallback');
        if (!input) {
            input = document.createElement('input');
            input.id = 'native-camera-fallback';
            input.type = 'file';
            input.accept = 'image/*';
            input.capture = 'user';
            input.style.display = 'none';
            document.body.appendChild(input);
            
            input.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    // อ่านไฟล์ที่ได้จากกล้องและแปลงเป็น Base64 เพื่อความเสถียรสูงสุด
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const base64Data = event.target.result;
                        
                        // บันทึกลง Hidden Input เพื่อเตรียม Submit form
                        const hiddenInput = document.getElementById('captured-image-base64');
                        if (hiddenInput) hiddenInput.value = base64Data;
                        
                        // ล้าง input file ปกติ (เพื่อเลี่ยงความซ้ำซ้อน)
                        const fileInput = document.getElementById('profile-image-input');
                        if (fileInput) fileInput.value = "";

                        // อัปเดตการแสดงผล Preview ทันที
                        const preview = document.getElementById('avatar-preview');
                        const placeholder = document.getElementById('avatar-placeholder');
                        if (preview) {
                            preview.src = base64Data;
                            preview.style.display = 'block';
                            if (placeholder) placeholder.style.display = 'none';
                        }
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'รับรูปจากกล้องเรียบร้อย!',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        input.click();
    }

    async function openWebcam() {
        // ตรวจสอบการรองรับ Camera API (มักติดปัญหาในโหมด HTTP บนอุปกรณ์อื่น)
        const canUseCameraAPI = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        
        if (!canUseCameraAPI) {
            // กรณีไม่รองรับ WebRTC เช่น HTTP ให้เรียกใช้กล้องจากระบบมือถือโดยตรงทันที
            openNativeMobileCameraFallback();
            return;
        }

        const modal = document.getElementById('webcamModal');
        const video = document.getElementById('webcam-video');
        const canvas = document.getElementById('webcam-canvas');
        
        // Reset ค่าเริ่มต้นและทำ Mirror effect
        currentFacingMode = "user";
        video.style.transform = "scaleX(-1)";
        canvas.style.transform = "scaleX(-1)";

        document.getElementById('camera-controls').classList.remove('d-none');
        document.getElementById('result-controls').classList.add('d-none');
        canvas.style.display = 'none';
        video.style.display = 'block';

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        try {
            webcamStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "user", width: { ideal: 800 }, height: { ideal: 1066 } }, 
                audio: false 
            });
            video.srcObject = webcamStream;

            // เช็คจำนวนกล้องบนเครื่อง เพื่อซ่อน/แสดงปุ่มสลับกล้อง (Flip Button)
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            const flipBtn = document.getElementById('flip-camera-btn');
            if (flipBtn) {
                if (videoDevices.length > 1) {
                    flipBtn.classList.remove('d-none'); // แสดงถ้ามีมากกว่า 1 เลนส์
                } else {
                    flipBtn.classList.add('d-none');
                }
            }

        } catch (err) {
            closeWebcam();
            // หากติด Permission หรือข้อผิดพลาดอื่นบน Android ให้สลับไปใช้ Native Camera
            Swal.fire({
                title: 'ติดปัญหาการเข้าถึงกล้อง',
                text: 'ระบบไม่สามารถเปิดกล้องในแอปเบราว์เซอร์ได้โดยตรง ต้องการสลับไปใช้ "แอปกล้อง" ของโทรศัพท์แทนหรือไม่? (วิธีนี้สลับกล้องหน้า-หลังได้ชัวร์ที่สุด)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, เปิดแอปกล้อง',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    openNativeMobileCameraFallback();
                }
            });
        }
    }

    function closeWebcam() {
        const modal = document.getElementById('webcamModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
            webcamStream = null;
        }
    }

    function takeSnapshot() {
        const video = document.getElementById('webcam-video');
        const canvas = document.getElementById('webcam-canvas');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        
        // จัดการ Mirror effect ของ Canvas เฉพาะตอนถ่ายเซลฟี่ (กล้องหน้า) เท่านั้น
        if (currentFacingMode === "user") {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        }
        
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        video.style.display = 'none';
        canvas.style.display = 'block';
        
        document.getElementById('camera-controls').classList.add('d-none');
        document.getElementById('result-controls').classList.remove('d-none');
    }

    function retakePhoto() {
        const video = document.getElementById('webcam-video');
        const canvas = document.getElementById('webcam-canvas');
        canvas.style.display = 'none';
        video.style.display = 'block';
        document.getElementById('camera-controls').classList.remove('d-none');
        document.getElementById('result-controls').classList.add('d-none');
    }

    function savePhoto() {
        const canvas = document.getElementById('webcam-canvas');
        
        // ดึงภาพจาก Canvas โดยตรงเป็น Base64 ซึ่งเสถียรที่สุด 100% บนเบราว์เซอร์โทรศัพท์
        const base64Data = canvas.toDataURL('image/jpeg', 0.85);
        
        // ใส่ข้อมูลลงใน Hidden input ที่เตรียมไว้สำหรับ PHP
        const hiddenInput = document.getElementById('captured-image-base64');
        if (hiddenInput) {
            hiddenInput.value = base64Data;
        }

        // ล้าง input file ปกติออกเพื่อไม่ให้ PHP สับสน
        const fileInput = document.getElementById('profile-image-input');
        if (fileInput) {
            fileInput.value = "";
        }

        // อัปเดตหน้า Preview ทันที
        const preview = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');
        if (preview) {
            preview.src = base64Data;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        }
        
        closeWebcam();
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'บันทึกภาพถ่ายเรียบร้อย! กดบันทึกการเปลี่ยนแปลงด้านล่างเพื่ออัปเดตระบบ',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Auto-switch tab from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'notifications') {
            const tabBtn = document.querySelector('[onclick*="notification-settings-pane"]');
            if (tabBtn) {
                switchTab('notification-settings-pane', tabBtn);
            }
        }

        const form = document.getElementById('profileForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Define form fields with thier labels and the tabs they live in
                const fieldsToCheck = [
                    { name: 'fullname', label: 'ชื่อ-นามสกุล', element: form.fullname, tabId: 'personal-info-pane' },
                    { name: 'phone', label: 'เบอร์โทรศัพท์ติดต่อ', element: form.phone, tabId: 'personal-info-pane' },
                    { name: 'classroom_id', label: 'ห้องเรียน / สาขาวิชา', element: form.classroom_id, tabId: 'personal-info-pane' },
                    { name: 'mentor_id', label: 'อาจารย์นิเทศก์', element: form.mentor_id, tabId: 'personal-info-pane' },
                    { name: 'company_name', label: 'ชื่อสถานประกอบการ / สถานที่ฝึกงาน', element: form.company_name, tabId: 'company-info-pane' },
                    { name: 'company_manager', label: 'ผู้มีอำนาจลงนามสูงสุด', element: form.company_manager, tabId: 'company-info-pane' },
                    { name: 'trainer_name', label: 'ชื่อครูฝึก/ผู้ควบคุม', element: form.trainer_name, tabId: 'company-info-pane' },
                    { name: 'trainer_phone', label: 'เบอร์โทรครูฝึก', element: form.trainer_phone, tabId: 'company-info-pane' },
                    { name: 'company_address', label: 'ที่อยู่สถานประกอบการ', element: form.company_address, tabId: 'company-info-pane' }
                ];

                // If LINE Notify is enabled, make token field mandatory
                const lineSwitch = document.getElementById('enable_line_switch');
                if (lineSwitch && lineSwitch.checked) {
                    fieldsToCheck.push({
                        name: 'line_token',
                        label: 'LINE Notify Access Token',
                        element: document.getElementById('line_token_input'),
                        tabId: 'notification-settings-pane'
                    });
                }

                // Remove previous validation errors
                fieldsToCheck.forEach(item => {
                    if (item.element && item.element.classList) {
                        item.element.classList.remove('input-error-pulse');
                    }
                });

                let missing = [];
                let firstMissingItem = null;

                fieldsToCheck.forEach(item => {
                    if (item.element) {
                        const val = item.element.value ? item.element.value.trim() : '';
                        if (val === '') {
                            missing.push(item.label);
                            if (item.element.classList) item.element.classList.add('input-error-pulse');
                            if (!firstMissingItem) {
                                firstMissingItem = item;
                            }
                        }
                    }
                });

                if (missing.length > 0) {
                    e.preventDefault(); // Intercept submit!

                    // Auto switch to correct tab for first empty input
                    if (firstMissingItem) {
                        const tabBtn = document.querySelector(`[onclick*="${firstMissingItem.tabId}"]`);
                        if (tabBtn) {
                            switchTab(firstMissingItem.tabId, tabBtn);
                        }
                        // Focus the first empty field
                        setTimeout(() => { firstMissingItem.element.focus(); }, 100);
                    }

                    // Trigger the beautiful warning toast alert
                    showEmptyFieldsAlert(missing);
                }
            });
        }
    });

    let alertTimeout = null;
    function showEmptyFieldsAlert(missingList) {
        const toast = document.getElementById('premiumAlertToast');
        const content = document.getElementById('alertToastContent');
        if (toast && content) {
            content.innerHTML = 'กรุณากรอกข้อมูลในช่องต่อไปนี้:<br><ul class="mb-0 mt-2 ps-3 text-start text-danger fw-semibold">' + 
                missingList.map(lbl => `<li>${lbl}</li>`).join('') + 
                '</ul>';
            toast.classList.add('show');
            
            if (alertTimeout) clearTimeout(alertTimeout);
            
            // Auto close after 7 seconds
            alertTimeout = setTimeout(() => {
                closeAlertToast();
            }, 7000);
        }
    }

    function closeAlertToast() {
        const toast = document.getElementById('premiumAlertToast');
        if (toast) {
            toast.classList.remove('show');
        }
    }

    /* Notification helpers */
    function toggleLineSettingsSection() {
        const lineSwitch = document.getElementById('enable_line_switch');
        const section = document.getElementById('line-notify-token-section');
        if (lineSwitch && section) {
            if (lineSwitch.checked) {
                section.style.display = 'block';
                section.classList.add('animate-fade-in');
            } else {
                section.style.display = 'none';
            }
        }
    }

    function togglePasswordVisibility(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const icon = btn.querySelector('i');
        if (input && icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-fill';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-slash-fill';
            }
        }
    }

    async function testLineConnection() {
        const tokenInput = document.getElementById('line_token_input');
        const token = tokenInput ? tokenInput.value.trim() : '';
        const testBtn = document.getElementById('test-line-btn');

        if (!token) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบ Access Token',
                text: 'กรุณากรอก LINE Notify Access Token ก่อนทำการทดสอบค่ะ',
                confirmButtonColor: '#3085d6'
            });
            if (tokenInput) {
                tokenInput.classList.add('input-error-pulse');
                setTimeout(() => tokenInput.classList.remove('input-error-pulse'), 1000);
                tokenInput.focus();
            }
            return;
        }

        // Disable button while sending
        if (testBtn) {
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> กำลังส่งทดสอบ...';
        }

        try {
            const response = await fetch('<?= BASE_URL ?>/student/api_test_line.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ line_token: token })
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'เชื่อมต่อสำเร็จ!',
                    text: data.message,
                    confirmButtonColor: '#10b981'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ส่งไม่สำเร็จ',
                    text: data.message,
                    confirmButtonColor: '#ef4444'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ โปรดตรวจสอบการเชื่อมต่ออินเทอร์เน็ต',
                confirmButtonColor: '#ef4444'
            });
        } finally {
            if (testBtn) {
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="bi bi-send-fill"></i> ทดสอบส่งข้อความ';
            }
        }
    }
</script>

<?php if (isset($show_success_alert) && $show_success_alert): ?>
<script>
    window.addEventListener('load', function() {
        Swal.fire({
            title: '<div class="premium-swal-title"><i class="bi bi-check-circle-fill"></i> บันทึกข้อมูลสำเร็จ</div>',
            html: '<div class="premium-swal-text">ข้อมูลโปรไฟล์และการฝึกงานของคุณถูกอัปเดตเรียบร้อยแล้ว</div>',
            background: 'rgba(255, 255, 255, 0.65)',
            backdrop: 'rgba(15, 23, 42, 0.35)',
            showConfirmButton: true,
            confirmButtonText: 'ตกลง',
            buttonsStyling: false,
            customClass: {
                popup: 'premium-swal-popup',
                confirmButton: 'premium-swal-confirm-btn shadow-sm'
            }
        }).then(function() {
            window.location = 'profile.php';
        });
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>