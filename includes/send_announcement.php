<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/configdb.php';

// 1. ตรวจสอบการเข้าสู่ระบบและสิทธิ์การใช้งานเฉพาะ Admin, Director, และ Staff
require_role(['admin', 'director', 'staff']);

$u = current_user();
$error_msg = '';
$success_msg = '';

// 2. จัดการเมื่อมีการ Submit ฟอร์มประกาศ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $target     = $_POST['target'] ?? '';
    $class_id   = isset($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : 0;
    $action_url = trim($_POST['action_url'] ?? '');
    
    // กำหนดค่าว่างให้เป็น null ถ้าไม่มีการกรอกลิงก์
    if (empty($action_url)) {
        $action_url = null;
    }

    if (empty($title) || empty($message)) {
        $error_msg = 'กรุณากรอกหัวข้อประกาศและเนื้อหารายละเอียดให้ครบถ้วน';
    } else {
        $success = false;
        
        switch ($target) {
            case 'all':
                // ส่งหาทุกคนในระบบ
                $stmt = $conn->prepare("SELECT id FROM users");
                if ($stmt) {
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $success = true;
                    while ($row = $res->fetch_assoc()) {
                        add_notification((int)$row['id'], $title, $message, 'announcement', $action_url);
                    }
                    $stmt->close();
                }
                break;
                
            case 'student':
                // ส่งเฉพาะนักศึกษา
                $success = add_notification_to_role('student', $title, $message, 'announcement', $action_url);
                break;
                
            case 'teacher_mentor':
                // ส่งเฉพาะครูและผู้ควบคุมการฝึกงาน (Mentors)
                $success1 = add_notification_to_role('teacher', $title, $message, 'announcement', $action_url);
                $success2 = add_notification_to_role('mentor', $title, $message, 'announcement', $action_url);
                $success = ($success1 || $success2);
                break;
                
            case 'staff':
                // ส่งเฉพาะเจ้าหน้าที่
                $success = add_notification_to_role('staff', $title, $message, 'announcement', $action_url);
                break;
                
            case 'classroom':
                // ส่งเฉพาะห้องเรียนที่เลือก
                if ($class_id > 0) {
                    $success = add_notification_to_classroom($class_id, $title, $message, 'announcement', $action_url);
                } else {
                    $error_msg = 'กรุณาเลือกห้องเรียนที่ต้องการส่งประกาศ';
                }
                break;
                
            default:
                $error_msg = 'ระบุกลุ่มเป้าหมายผู้รับไม่ถูกต้อง';
                break;
        }
        
        if (empty($error_msg)) {
            if ($success) {
                $_SESSION['success_message'] = 'ส่งประกาศระบบแจ้งเตือนไปยังผู้รับเป้าหมายเรียบร้อยแล้ว!';
                // ป้องกันการยิงซ้ำเมื่อกดรีเฟรชหน้าจอ
                header("Location: send_announcement.php");
                exit;
            } else {
                $error_msg = 'เกิดข้อผิดพลาดทางเทคนิคในการสร้างการแจ้งเตือน';
            }
        }
    }
}

// 3. ดึงรายการห้องเรียนทั้งหมดจากฐานข้อมูลสำหรับใช้งานใน dropdown ตัวเลือก
$classrooms = [];
$class_query = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
if ($class_query) {
    while ($row = $class_query->fetch_assoc()) {
        $classrooms[] = $row;
    }
}

$hide_welcome = true;
include __DIR__ . '/header.php';
?>
<style>
    .announce-page-wrap {
        max-width: 800px;
        margin: 0 auto;
        padding: 2.5rem 1rem 5rem;
    }
    .announce-card-glass {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(99, 102, 241, 0.12);
        border-radius: 1.75rem;
        padding: 2.5rem;
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.08);
    }
    .page-title {
        font-weight: 800;
        letter-spacing: -0.5px;
        color: #1e293b;
    }
    .form-group-custom {
        margin-bottom: 1.75rem;
    }
    .form-label-custom {
        font-weight: 700;
        font-size: 0.9rem;
        color: #475569;
        margin-bottom: 0.6rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-label-custom i {
        color: #6366f1;
    }
    .form-control-custom {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 1rem;
        padding: 0.75rem 1.25rem;
        color: #1e293b;
        font-size: 0.95rem;
        transition: all 0.25s ease;
    }
    .form-control-custom:focus {
        background: #ffffff;
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        outline: none;
    }
    .select-custom {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E");
        background-position: right 1rem center;
        background-repeat: no-repeat;
        background-size: 1.25rem;
        padding-right: 2.5rem;
    }
    .radio-card-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
    }
    .radio-card {
        border: 1.5px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f8fafc;
        transition: all 0.2s ease;
        position: relative;
    }
    .radio-card:hover {
        background: rgba(99, 102, 241, 0.04);
        border-color: rgba(99, 102, 241, 0.3);
    }
    .radio-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0; height: 0;
    }
    .radio-card-indicator {
        width: 20px;
        height: 20px;
        border: 2px solid #cbd5e1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .radio-card-indicator::after {
        content: '';
        width: 10px;
        height: 10px;
        background: white;
        border-radius: 50%;
        transform: scale(0);
        transition: all 0.2s ease;
    }
    .radio-card input[type="radio"]:checked + .radio-card-indicator {
        border-color: #6366f1;
        background: #6366f1;
    }
    .radio-card input[type="radio"]:checked + .radio-card-indicator::after {
        transform: scale(1);
    }
    .radio-card input[type="radio"]:checked ~ .radio-card-content {
        color: #1e293b;
    }
    .radio-card input[type="radio"]:checked {
        parent-border: 1px solid #6366f1; /* custom handle in JS */
    }
    .radio-card.selected {
        border-color: #6366f1;
        background: rgba(99, 102, 241, 0.05);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
    }
    .radio-card-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        background: white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        flex-shrink: 0;
    }
    .radio-card-title {
        font-weight: 700;
        font-size: 0.88rem;
        color: #475569;
        margin-bottom: 0.1rem;
    }
    .radio-card-desc {
        font-size: 0.72rem;
        color: #94a3b8;
    }
    .btn-send-announcement {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        font-weight: 700;
        border-radius: 1.25rem;
        padding: 0.9rem 2rem;
        font-size: 1rem;
        border: none;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }
    .btn-send-announcement:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35);
        color: white;
    }
    .btn-send-announcement:active {
        transform: translateY(0);
    }
    .back-btn {
        padding: 0.5rem 1.25rem;
        border-radius: 50rem;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.2s;
    }
</style>

<div class="announce-page-wrap animate-fade-in">
    <div class="announce-card-glass">
        
        <!-- Header Info -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2" style="border-bottom-color: rgba(99, 102, 241, 0.1) !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-indigo-600 bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(99,102,241,0.1);">
                    <i class="bi bi-megaphone-fill fs-4 text-primary" style="color: #6366f1 !important;"></i>
                </div>
                <div>
                    <h4 class="page-title mb-1">ส่งประกาศระบบแจ้งเตือน</h4>
                    <p class="text-muted mb-0 small">ส่งข่าวประกาศ คู่นัดหมาย หรือแจ้งข้อกำหนดถึงผู้ใช้แบบ Broadcast</p>
                </div>
            </div>
            
            <!-- Dashboard Redirection Link -->
            <?php 
                $back_link = '../roles/staff.php';
                if ($u['role'] === 'admin') $back_link = '../roles/admin.php';
                elseif ($u['role'] === 'director') $back_link = '../roles/director.php';
            ?>
            <a href="<?= $back_link ?>" class="btn btn-outline-secondary back-btn border-opacity-25 bg-white shadow-sm">
                <i class="bi bi-arrow-left me-1"></i>กลับหน้าหลัก
            </a>
        </div>

        <!-- Flash messages -->
        <?php 
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i>' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (!empty($error_msg)) {
            echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i>' . htmlspecialchars($error_msg) . '</div>';
        }
        ?>

        <!-- Announcement Form -->
        <form method="POST" id="announceForm">
            
            <!-- 1. Target Audience Selection -->
            <div class="form-group-custom">
                <label class="form-label-custom"><i class="bi bi-people-fill"></i>เลือกกลุ่มเป้าหมายผู้รับ <span class="text-danger">*</span></label>
                <div class="radio-card-group">
                    
                    <label class="radio-card selected" id="card_all">
                        <input type="radio" name="target" value="all" checked onclick="toggleTarget('all')">
                        <div class="radio-card-indicator"></div>
                        <div class="radio-card-icon text-primary"><i class="bi bi-globe"></i></div>
                        <div class="radio-card-content">
                            <div class="radio-card-title">ผู้ใช้งานทั้งหมด</div>
                            <div class="radio-card-desc">ทุกคนในระบบ</div>
                        </div>
                    </label>

                    <label class="radio-card" id="card_student">
                        <input type="radio" name="target" value="student" onclick="toggleTarget('student')">
                        <div class="radio-card-indicator"></div>
                        <div class="radio-card-icon text-indigo" style="color: #6366f1;"><i class="bi bi-mortarboard-fill"></i></div>
                        <div class="radio-card-content">
                            <div class="radio-card-title">นักศึกษาทุกคน</div>
                            <div class="radio-card-desc">ผู้เข้ารับการฝึกงาน</div>
                        </div>
                    </label>

                    <label class="radio-card" id="card_teacher">
                        <input type="radio" name="target" value="teacher_mentor" onclick="toggleTarget('teacher')">
                        <div class="radio-card-indicator"></div>
                        <div class="radio-card-icon text-success" style="color: #10b981;"><i class="bi bi-person-badge-fill"></i></div>
                        <div class="radio-card-content">
                            <div class="radio-card-title">ครู & ครูฝึก (Mentor)</div>
                            <div class="radio-card-desc">ผู้ควบคุม/นิเทศก์</div>
                        </div>
                    </label>

                    <label class="radio-card" id="card_staff">
                        <input type="radio" name="target" value="staff" onclick="toggleTarget('staff')">
                        <div class="radio-card-indicator"></div>
                        <div class="radio-card-icon text-secondary"><i class="bi bi-person-gear"></i></div>
                        <div class="radio-card-content">
                            <div class="radio-card-title">เจ้าหน้าที่ระบบ</div>
                            <div class="radio-card-desc">ผู้ดูแลข่าวสารส่วนกลาง</div>
                        </div>
                    </label>

                    <label class="radio-card" id="card_classroom">
                        <input type="radio" name="target" value="classroom" onclick="toggleTarget('classroom')">
                        <div class="radio-card-indicator"></div>
                        <div class="radio-card-icon text-warning" style="color: #f59e0b;"><i class="bi bi-door-open-fill"></i></div>
                        <div class="radio-card-content">
                            <div class="radio-card-title">เจาะจงกลุ่มเรียน</div>
                            <div class="radio-card-desc">แจ้งเตือนรายห้องเรียน</div>
                        </div>
                    </label>
                    
                </div>
            </div>

            <!-- Classroom Selector Dropdown (Hidden initially, shown only when classroom target selected) -->
            <div class="form-group-custom animate-fade-in" id="classroom_select_area" style="display: none;">
                <label class="form-label-custom" for="classroom_id"><i class="bi bi-door-open"></i>ระบุห้องเรียนเป้าหมาย <span class="text-danger">*</span></label>
                <select name="classroom_id" id="classroom_id" class="form-control form-control-custom select-custom">
                    <option value="">-- กรุณาเลือกกลุ่มห้องเรียน --</option>
                    <?php foreach ($classrooms as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Announcement Title -->
            <div class="form-group-custom">
                <label class="form-label-custom" for="title"><i class="bi bi-pencil-square"></i>หัวข้อประกาศแจ้งเตือน <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" class="form-control form-control-custom" 
                       placeholder="ตัวอย่าง: 📢 ขยายเวลาการส่งสมุดบันทึกรายงานประจำวัน..." required>
            </div>

            <!-- 3. Announcement Message -->
            <div class="form-group-custom">
                <label class="form-label-custom" for="message"><i class="bi bi-chat-text-fill"></i>รายละเอียดเนื้อหา <span class="text-danger">*</span></label>
                <textarea name="message" id="message" class="form-control form-control-custom" rows="6" 
                          placeholder="กรอกรายละเอียดข่าวสาร ข้อกำหนด หรือแนวทางการดำเนินงานที่ต้องการประชาสัมพันธ์..." required></textarea>
            </div>

            <!-- 4. Action URL Link (Optional) -->
            <div class="form-group-custom">
                <label class="form-label-custom" for="action_url"><i class="bi bi-link-45deg"></i>ลิงก์ปลายทางเพิ่มเติม (Action URL) <span class="text-muted">(ไม่บังคับ)</span></label>
                <input type="url" name="action_url" id="action_url" class="form-control form-control-custom" 
                       placeholder="เช่น: <?= BASE_URL ?>/student/submit_report.php หรือลิงก์ภายนอก">
                <small class="text-muted mt-2 d-block small" style="font-size: 0.78rem;">
                    <i class="bi bi-info-circle me-1"></i> เมื่อผู้ใช้งานคลิกแจ้งเตือนในระบบ จะเชื่อมไปยัง URL นี้โดยอัตโนมัติ
                </small>
            </div>

            <!-- Submit Button -->
            <div class="text-center pt-3">
                <button type="submit" class="btn btn-send-announcement">
                    <i class="bi bi-send-fill"></i>ส่งข่าวประกาศตอนนี้เลย
                </button>
            </div>
            
        </form>
    </div>
</div>

<script>
function toggleTarget(target) {
    // 1. จัดการการเลือกสีและเงาของ Radio Card
    document.querySelectorAll('.radio-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    let targetMap = {
        'all': 'card_all',
        'student': 'card_student',
        'teacher_mentor': 'card_teacher',
        'staff': 'card_staff',
        'classroom': 'card_classroom'
    };
    
    let activeCardId = targetMap[target] || 'card_all';
    let cardEl = document.getElementById(activeCardId);
    if (cardEl) {
        cardEl.classList.add('selected');
    }

    // 2. ซ่อน/แสดงตัวเลือกห้องเรียน
    let classSelectArea = document.getElementById('classroom_select_area');
    let classSelect = document.getElementById('classroom_id');
    
    if (target === 'classroom') {
        $(classSelectArea).slideDown(250);
        classSelect.setAttribute('required', 'required');
    } else {
        $(classSelectArea).slideUp(200);
        classSelect.removeAttribute('required');
    }
}

// ผูกฟังก์ชันเข้ากับ SweetAlert สำหรับความพรีเมียมสูงสุดเมื่อมีการส่ง
$(document).ready(function() {
    $('#announceForm').on('submit', function(e) {
        let title = $('#title').val().trim();
        let message = $('#message').val().trim();
        
        if (title === '' || message === '') {
            return; // ปล่อยให้ HTML5 validation ทำงาน
        }
        
        e.preventDefault();
        
        Swal.fire({
            title: 'ยืนยันการส่งประกาศ?',
            text: 'ระบบจะส่งข้อความแจ้งเตือนหาเป้าหมายทั้งหมดทันทีแบบ Real-time ต้องการดำเนินการต่อใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ส่งประกาศเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // แสดง Loading ระหว่างส่ง
                Swal.fire({
                    title: 'กำลังส่งข่าวประกาศ...',
                    html: 'ระบบกำลังดำเนินการกระจายข้อความแจ้งเตือนหาผู้ใช้เป้าหมาย กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                this.submit();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
