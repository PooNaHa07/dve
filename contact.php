<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'ติดต่อเรา';

// Smart Header
if (is_logged_in() && in_array(get_current_role(), ['admin', 'staff', 'officer', 'teacher', 'director'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/header_public.php';
}

// Prefill user data if logged in
$default_name = '';
$default_contact = '';
if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        $default_name = $user['fullname'];
        $default_contact = $user['email'] ?: $user['phone'] ?: '';
    }
}

// Fetch user tickets if logged in
$user_id = is_logged_in() ? (int)$_SESSION['user_id'] : 0;
$messages = [];
if ($user_id > 0) {
    $msg_q = $conn->query("SELECT * FROM contact_messages WHERE user_id = $user_id ORDER BY created_at DESC");
    if ($msg_q) {
        while ($row = $msg_q->fetch_assoc()) {
            $messages[] = $row;
        }
    }
}

$thai_months = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
    7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];

function format_thai_date($date_str) {
    global $thai_months;
    if (!$date_str) return '';
    $time = strtotime($date_str);
    $d = date('j', $time);
    $m = (int)date('n', $time);
    $y = (int)date('Y', $time) + 543;
    $h = date('H:i', $time);
    return "$d {$thai_months[$m]} $y เวลา $h น.";
}
?>

<style>
    /* Animated Floating Blobs Background matching other pages */
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

    /* Page Container Spacing */
    .contact-container {
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    /* Left Side: Information Card Style */
    .contact-info-card {
        background: rgba(255, 255, 255, 0.4);
        border: 1px solid rgba(226, 232, 240, 0.6);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 28px;
        padding: 2.5rem;
    }

    .contact-info-title {
        font-size: 2.25rem;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.5px;
        margin-bottom: 1rem;
    }

    .contact-info-subtitle {
        font-size: 1rem;
        color: #64748b;
        font-weight: 500;
        line-height: 1.6;
        margin-bottom: 2.5rem;
    }

    /* Contact Details Sub-Blocks */
    .contact-block {
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(226, 232, 240, 0.8);
        padding: 1.5rem;
        border-radius: 20px;
        margin-bottom: 1.25rem;
        transition: all 0.25s;
    }

    .contact-block:hover {
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.04);
        border-color: rgba(99, 102, 241, 0.3);
    }

    .contact-icon-circle {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.35rem;
        box-shadow: 0 6px 12px rgba(99, 102, 241, 0.15);
        flex-shrink: 0;
    }

    .contact-label {
        font-size: 0.85rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.2rem;
    }

    .contact-value {
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.5;
    }

    /* Right Side: Glassmorphic Message Form Card */
    .glass-contact-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 30px;
        padding: 3rem;
        box-shadow: 0 25px 55px rgba(0,0,0,0.05);
    }

    .form-heading {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-heading i {
        color: #6366f1;
    }

    /* Form Fields Customisation */
    .form-label {
        font-weight: 700;
        color: #475569;
        font-size: 0.9rem;
        margin-bottom: 0.45rem;
    }

    .form-control, .form-select {
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: #1e293b;
        transition: all 0.3s;
        background-color: white;
    }

    .form-control:focus, .form-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        background-color: white;
    }

    textarea.form-control {
        resize: none;
    }

    /* Gradient Pill Action Button */
    .btn-send-message {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        border-radius: 16px;
        padding: 0.95rem 2rem;
        font-weight: 700;
        font-size: 1rem;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.3s;
        box-shadow: 0 6px 15px rgba(99, 102, 241, 0.2);
    }

    .btn-send-message:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 12px 25px rgba(79, 70, 229, 0.3);
        color: white;
    }

    /* Support Tickets / Reply History Styling */
    .ticket-history-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 28px;
        padding: 2.5rem;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.03);
    }

    .ticket-history-title {
        font-size: 1.5rem;
        font-weight: 850;
        color: #0f172a;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .ticket-item {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        transition: all 0.3s ease;
    }

    .ticket-item:hover {
        transform: translateY(-2px);
        background: #ffffff;
        border-color: rgba(99, 102, 241, 0.4);
        box-shadow: 0 12px 25px rgba(99, 102, 241, 0.06);
    }

    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1rem;
        border-bottom: 1px dashed rgba(226, 232, 240, 1);
        padding-bottom: 0.75rem;
    }

    .ticket-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .ticket-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .badge-pending {
        background-color: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .badge-processing {
        background-color: rgba(59, 130, 246, 0.1);
        color: #2563eb;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .badge-replied {
        background-color: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .ticket-body {
        color: #334155;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .ticket-message {
        font-weight: 500;
        background: rgba(248, 250, 252, 0.6);
        padding: 1rem 1.25rem;
        border-radius: 14px;
        border-left: 3px solid #cbd5e1;
        margin-bottom: 1rem;
    }

    .ticket-reply {
        background: linear-gradient(135deg, rgba(238, 242, 255, 0.7) 0%, rgba(224, 231, 255, 0.7) 100%);
        border: 1px solid rgba(99, 102, 241, 0.15);
        padding: 1.25rem 1.5rem;
        border-radius: 16px;
        position: relative;
        margin-top: 1rem;
    }

    .ticket-reply-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        color: #4f46e5;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .ticket-reply-body {
        color: #1e1b4b;
        font-weight: 600;
        font-size: 0.95rem;
        white-space: pre-line;
    }

    .no-tickets {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
    }

    .no-tickets i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #cbd5e1;
        display: block;
    }

    .text-indigo {
        color: #6366f1;
    }

    /* Desktop Viewport optimization - strictly removes scrolling */
    @media (min-width: 992px) {
        .contact-container {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .contact-info-card {
            padding: 1.75rem 2rem;
            border-radius: 24px;
        }
        .contact-info-title {
            font-size: 1.85rem;
            margin-bottom: 0.5rem;
        }
        .contact-info-subtitle {
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        .contact-block {
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            gap: 1rem;
            border-radius: 16px;
        }
        .contact-icon-circle {
            width: 44px;
            height: 44px;
            font-size: 1.15rem;
            border-radius: 10px;
        }
        .contact-value {
            font-size: 0.95rem;
        }
        .glass-contact-card {
            padding: 2rem 2.25rem;
            border-radius: 24px;
        }
        .form-heading {
            margin-bottom: 1.25rem;
            font-size: 1.2rem;
        }
        .form-label {
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
        }
        .form-control, .form-select {
            padding: 0.55rem 0.85rem;
            font-size: 0.9rem;
            border-radius: 10px;
        }
        .btn-send-message {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            border-radius: 12px;
        }
        .mb-3 {
            margin-bottom: 0.75rem !important;
        }
        .mb-4 {
            margin-bottom: 1rem !important;
        }
        .ticket-history-card {
            padding: 2rem 2.25rem;
            border-radius: 24px;
        }
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container contact-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="btn btn-light btn-sm rounded-pill px-3 shadow-sm border">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก
        </a>
    </div>
    <div class="row g-4 g-lg-5">
        <!-- Left Side: Information cards -->
        <div class="col-12 col-md-5">
            <div class="contact-info-card">
                <h1 class="contact-info-title">ติดต่อเรา</h1>
                <p class="contact-info-subtitle">หากคุณมีข้อสงสัยเกี่ยวกับระบบ หรือต้องการความช่วยเหลือด้านการฝึกงานวิชาชีพ สามารถติดต่อเราได้ตามช่องทางด้านล่างนี้</p>

                <div class="contact-block">
                    <div class="contact-icon-circle">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <div class="contact-label">ที่อยู่จัดตั้งวิทยาการ</div>
                        <div class="contact-value">300 ถ.ราชวิถี ต.คลองกระแชง อ.เมืองเพชรบุรี จ.เพชรบุรี 76000</div>
                    </div>
                </div>

                <div class="contact-block">
                    <div class="contact-icon-circle">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                    <div>
                        <div class="contact-label">เบอร์โทรศัพท์ติดต่อ</div>
                        <div class="contact-value">032-425-557</div>
                    </div>
                </div>

                <div class="contact-block">
                    <div class="contact-icon-circle">
                        <i class="bi bi-envelope-at-fill"></i>
                    </div>
                    <div>
                        <div class="contact-label">อีเมลกลางระบบ</div>
                        <div class="contact-value text-break">Dvt_pbpvc@hotmail.com</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Luxury Message Form -->
        <div class="col-12 col-md-7">
            <div class="glass-contact-card">
                <h4 class="form-heading">
                    <i class="bi bi-envelope-open-fill"></i>
                    <span>ส่งข้อความหาเรา</span>
                </h4>

                <form action="send_contact.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุลของคุณ <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="ระบุชื่อจริง-นามสกุลของคุณ" value="<?php echo e($default_name); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">อีเมล หรือ เบอร์โทรศัพท์ติดต่อกลับ <span class="text-danger">*</span></label>
                        <input type="text" name="contact" class="form-control" placeholder="ระบุข้อมูลที่ติดต่อกลับได้สะดวก" value="<?php echo e($default_contact); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">วัตถุประสงค์ในการติดต่อ</label>
                        <select name="subject" class="form-select">
                            <option value="ทั่วไป">📥 สอบถามข้อมูลทั่วไป</option>
                            <option value="แจ้งปัญหา">🛠️ แจ้งเรื่องขัดข้อง / ปัญหาระบบ</option>
                            <option value="สถานประกอบการ">🏢 เสนอข้อมูลสถานประกอบการคู่ค้า</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">รายละเอียดข้อมูลหรือข้อความ <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="4" placeholder="ระบุข้อความหรือคำถามที่ต้องการให้ช่วยเหลือที่นี่..." required></textarea>
                    </div>

                    <button type="submit" class="btn-send-message">
                        <span>ส่งข้อความติดต่อกลับ</span>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ticket / Reply History for Logged-in Users -->
    <?php if (is_logged_in()): ?>
        <div class="row mt-4 mt-lg-5">
            <div class="col-12">
                <div class="ticket-history-card">
                    <h2 class="ticket-history-title">
                        <i class="bi bi-clock-history text-indigo"></i>
                        <span>ประวัติการติดต่อกลับและคำตอบจากผู้ดูแล</span>
                    </h2>

                    <?php if (empty($messages)): ?>
                        <div class="no-tickets">
                            <i class="bi bi-envelope-x text-muted"></i>
                            <p class="mb-0">ยังไม่มีประวัติการส่งข้อความติดต่อกลับของคุณในระบบ</p>
                        </div>
                    <?php else: ?>
                        <div class="ticket-list">
                            <?php foreach ($messages as $msg): ?>
                                <div class="ticket-item">
                                    <div class="ticket-header">
                                        <div class="ticket-meta">
                                            <span class="fw-bold text-indigo">[<?php echo e($msg['subject']); ?>]</span>
                                            <span>•</span>
                                            <span><i class="bi bi-calendar3 me-1"></i><?php echo format_thai_date($msg['created_at']); ?></span>
                                        </div>
                                        <div>
                                            <?php if (!empty($msg['staff_note'])): ?>
                                                <span class="ticket-badge badge-replied">
                                                    <i class="bi bi-patch-check-fill"></i> ได้รับการตอบกลับ
                                                </span>
                                            <?php elseif ($msg['status'] === 'read'): ?>
                                                <span class="ticket-badge badge-processing">
                                                    <i class="bi bi-eye-fill"></i> อ่านแล้ว
                                                </span>
                                            <?php else: ?>
                                                <span class="ticket-badge badge-pending">
                                                    <i class="bi bi-hourglass-split"></i> รอดำเนินการ
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ticket-body">
                                        <div class="ticket-message">
                                            <div class="fw-bold text-secondary mb-1" style="font-size: 0.8rem; text-transform: uppercase;">ข้อความของคุณ:</div>
                                            <?php echo nl2br(e($msg['message'])); ?>
                                        </div>
                                        
                                        <?php if (!empty($msg['staff_note'])): ?>
                                            <div class="ticket-reply">
                                                <div class="ticket-reply-header">
                                                    <i class="bi bi-chat-left-text-fill"></i>
                                                    <span>การตอบกลับจากเจ้าหน้าที่:</span>
                                                </div>
                                                <div class="ticket-reply-body"><?php echo e($msg['staff_note']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>