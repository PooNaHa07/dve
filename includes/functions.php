<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer Autoloader (PSR-4)
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

require_once __DIR__ . '/configdb.php';

// ---- [L-4] Centralise timezone ----
date_default_timezone_set('Asia/Bangkok');

// Base URL detection
if (!defined('BASE_URL')) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = str_replace('\\', '/', dirname($script_name));
    $path = preg_replace('/\/(includes|roles|student|teacher|admin|staff|director|supervisor|mentors|companies|calendar|documents|reports|db|libs|src|tests|cron)$/i', '', $path);
    $path = rtrim($path, '/');
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . '://' . $host . ($path ?: ''));
}

// ---- [L-1] Supervision status constants ----
if (!defined('SUP_STATUS_PENDING_STAFF'))    define('SUP_STATUS_PENDING_STAFF',    0);
if (!defined('SUP_STATUS_PENDING_DIRECTOR')) define('SUP_STATUS_PENDING_DIRECTOR', 1);
if (!defined('SUP_STATUS_SIGNED'))           define('SUP_STATUS_SIGNED',           2);

// Escape output
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Login check
function is_logged_in(){ return isset($_SESSION['user_id']); }

// Current user
function current_user($refresh = false){
    global $conn;
    if (!is_logged_in()) return null;
    static $user = null;
    if ($refresh) $user = null;
    if ($user === null){
        // Using SELECT * to prevent fatal errors during schema transitions/migrations
        $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    return $user;
}

// Current role
function get_current_role(){
    $user = current_user();
   return isset($user['role']) ? $user['role'] : null;
}

// Require login (ถ้ายังไม่ล็อกอิน จะส่งไปหน้า login พร้อม return_url เพื่อกลับมาหน้าเดิมหลังล็อกอิน)
function require_login(){
    if (!is_logged_in()){
        $here = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (defined('BASE_URL') ? BASE_URL : '/');
        $return_url = urlencode($here);
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/login.php?return_url=' . $return_url);
        exit;
    }
}

// Require role
function require_role($roles = []){
    require_login(); // ตรวจสอบ login ก่อน
    $user = current_user();
    $user_role = isset($user['role']) ? $user['role'] : '';
    if (!in_array($user_role, $roles)){
        // [C-4] ใช้ redirect แทน die() เพื่อให้ UX ดีขึ้น และบันทึก Log
        $requested = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        error_log('[ACCESS DENIED] role=' . $user_role . ' tried to access: ' . $requested);
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/login.php?error=unauthorized');
        exit;
    }
}

// Random string
function random_string($length = 12){
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    } else {
        return substr(md5(uniqid(mt_rand(), true)), 0, $length*2);
    }
}

// Upload file
function upload_file($file, $allowed_ext = ['pdf'], $dest_dir = __DIR__ . '/../uploads/pdfs', $max_bytes = 5*1024*1024){
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return null;
    if ($file['size'] > $max_bytes) return null;
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    $name = time().'_'.random_string(6).'.'.$ext;
    $target = $dest_dir.'/'.$name;
    if (move_uploaded_file($file['tmp_name'], $target)) return $name;
    return null;
}

/**
 * บันทึกรูปภาพรายงานประจำวันแบบ Multi-Fallback (ไฟล์อัปโหลด หรือ Base64 จากเบราว์เซอร์มือถือ)
 * พร้อมซิงค์ไฟล์ไปยังทั้ง uploads/images/ และ uploads/reports/ ป้องกันปัญหาภาพหาย
 */
function save_report_image($file, string $base64_str = '', string $prefix = 'report_'): ?string {
    $filename = null;
    $images_dir  = __DIR__ . '/../uploads/images';
    $reports_dir = __DIR__ . '/../uploads/reports';

    if (!is_dir($images_dir))  @mkdir($images_dir, 0755, true);
    if (!is_dir($reports_dir)) @mkdir($reports_dir, 0755, true);

    // 1. วิธีหลัก: อัปโหลดไฟล์แบบ multipart/form-data
    if (isset($file) && is_array($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp','gif','bmp','heic','heif'];
        $uploaded = upload_file($file, $allowed, $images_dir, 25 * 1024 * 1024);
        if ($uploaded !== null) {
            $filename = $uploaded;
        }
    }

    // 2. สำรอง: หากไฟล์หลุด (เช่น Safari/In-App Browser ล้างไฟล์) ให้ดึงจาก Base64 string
    if ($filename === null && !empty($base64_str)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_str, $type)) {
            $data = base64_decode(substr($base64_str, strpos($base64_str, ',') + 1));
            if ($data !== false && strlen($data) <= 25 * 1024 * 1024) {
                $img_info = @getimagesizefromstring($data);
                if ($img_info !== false) {
                    $ext = 'jpg';
                    if ($img_info[2] === IMAGETYPE_PNG)  $ext = 'png';
                    if ($img_info[2] === IMAGETYPE_WEBP) $ext = 'webp';
                    if ($img_info[2] === IMAGETYPE_GIF)  $ext = 'gif';

                    $filename = time() . '_' . random_string(6) . '.' . $ext;
                    @file_put_contents($images_dir . '/' . $filename, $data);
                }
            }
        }
    }

    // 3. ซิงค์คัดลอกไฟล์ไปยัง uploads/reports/ ด้วยเสมอ
    if ($filename !== null) {
        $src_file  = $images_dir . '/' . $filename;
        $dest_file = $reports_dir . '/' . $filename;
        if (file_exists($src_file) && !file_exists($dest_file)) {
            @copy($src_file, $dest_file);
        }
    }

    return $filename;
}

/**
 * ดึง URL ของรูปภาพรายงานประจำวันแบบสอดคล้องทั้งระบบ
 * ตรวจสอบไฟล์ใน uploads/reports/ และ uploads/images/ ป้องกันปัญหารูปภาพไม่แสดง
 */
function get_report_image_url(?string $filename): string {
    if (empty($filename)) return '';
    $clean_name = basename($filename);

    $reports_path = __DIR__ . '/../uploads/reports/' . $clean_name;
    $images_path  = __DIR__ . '/../uploads/images/' . $clean_name;

    $base = defined('BASE_URL') ? BASE_URL : '';

    if (file_exists($reports_path)) {
        return $base . '/uploads/reports/' . rawurlencode($clean_name);
    }
    if (file_exists($images_path)) {
        return $base . '/uploads/images/' . rawurlencode($clean_name);
    }

    return $base . '/uploads/reports/' . rawurlencode($clean_name);
}

// ------------------- Safe Count -------------------
function safe_count($table, $where = '1=1') {
    global $conn;
    if (!isset($conn)) return 0;
    try {
        $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE $where";
        $res = $conn->query($sql);
        if (!$res) return 0;
        return (int)$res->fetch_assoc()['total'];
    } catch (Exception $e) {
        return 0;
    }
}
// ------------------- Get Setting Value -------------------
function get_setting(string $key, $default = '') {
    global $conn;
    static $settings_cache = [];
    if (isset($settings_cache[$key])) return $settings_cache[$key];
    
    if (!isset($conn) || $conn->connect_error) return $default;

    $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $settings_cache[$key] = $row['setting_value'];
        $stmt->close();
        return $row['setting_value'];
    }
    $stmt->close();
    return $default;
}

function upload_image($file) {
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $target_dir = __DIR__ . "/../uploads/reports/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . "_" . time() . "." . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    }
    return null;
}

// ------------------- เพิ่มเติมสำหรับระบบผู้บริหาร (Director) -------------------

/**
 * ตรวจสอบว่าเป็นผู้บริหารหรือไม่
 * @return boolean
 */
function is_director() {
    $role = get_current_role();
    return ($role === 'director' || $role === 'admin');
}

/**
 * ตรวจสอบว่าเป็น supervisor (ผู้ดูแลการฝึกงาน) หรือไม่
 * @return boolean
 */
function is_supervisor(): bool {
    return get_current_role() === 'supervisor';
}

/**
 * คืน array ของ student_id ทั้งหมดที่ supervisor คนนี้ดูแล
 * @param int $supervisor_id — users.id ของ supervisor
 * @return int[]
 */
function get_supervised_student_ids(int $supervisor_id): array {
    global $conn;
    if (!isset($conn) || $supervisor_id <= 0) return [];
    $stmt = $conn->prepare(
        "SELECT student_id FROM supervisor_assignments WHERE supervisor_id = ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param("i", $supervisor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['student_id'];
    }
    $stmt->close();
    return $ids;
}

/**
 * Get all active companies
 * [Refactor] Uses App\Helpers\Database
 */
function get_all_companies(): array {
    return \App\Helpers\Database::fetchAll(
        "SELECT id, name AS company_name FROM companies ORDER BY name ASC"
    );
}

/**
 * Get system statistics for daily reports
 * [Refactor] Uses App\Helpers\Database and App\Enums\ReportStatus
 */
function get_global_report_stats(): array {
    $stats = \App\Helpers\Database::fetchOne("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected
        FROM daily_reports
    ", [
        \App\Enums\ReportStatus::Approved->value,
        \App\Enums\ReportStatus::Pending->value,
        \App\Enums\ReportStatus::Rejected->value
    ]);

    return $stats ?: ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
}

/**
 * ฟังก์ชันจัดฟอร์แมตวันที่แบบไทย (สำหรับแสดงในรายงานผู้บริหาร)
 */
function date_thai($strDate) {
    if (!$strDate || $strDate == '0000-00-00') return "-";
    $strYear = date("Y", strtotime($strDate)) + 543;
    $strMonth = date("n", strtotime($strDate));
    $strDay = date("j", strtotime($strDate));
    $strMonthCut = Array("", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค.");
    $strMonthThai = $strMonthCut[$strMonth];
    return "$strDay $strMonthThai $strYear";
}

/**
 * Send message to LINE Notify
 */
function send_line_notify(string $token, string $message): bool {
    if (empty($token)) return false;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . urlencode($message));
    $headers = [
        'Content-type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . $token
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return !$error;
}

/**
 * Send email notification
 */
function send_email_notification(string $to_email, string $title, string $message): bool {
    if (empty($to_email)) return false;
    try {
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->setFrom('noreply@dve-system.local', 'DVE System');
            $mail->addAddress($to_email);
            $mail->Subject = $title;
            $mail->Body = $message;
            return $mail->send();
        }
    } catch (\Exception $e) {
        // Fallback to native php mail()
    }
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: <noreply@dve-system.local>" . "\r\n";
    return mail($to_email, $title, $message, $headers);
}

/**
 * Add a system notification for a specific user (Multi-channel)
 */
function add_notification(int $user_id, string $title, string $message, string $type = 'info', ?string $action_url = null): bool {
    global $conn;
    if (!isset($conn)) return false;
    
    // Auto-create notifications table if not exists for stability
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        type VARCHAR(50) DEFAULT 'info',
        action_url VARCHAR(255) DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check and alter table to add columns if they don't exist
    $check_type = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
    if ($check_type && $check_type->num_rows == 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) DEFAULT 'info'");
    }
    $check_url = $conn->query("SHOW COLUMNS FROM notifications LIKE 'action_url'");
    if ($check_url && $check_url->num_rows == 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL");
    }
    $check_expires = $conn->query("SHOW COLUMNS FROM notifications LIKE 'expires_at'");
    if ($check_expires && $check_expires->num_rows == 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN expires_at DATETIME DEFAULT NULL");
    }

    // Dynamic Indexes Check & Optimization
    $check_index1 = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notif_user_read'");
    if ($check_index1 && $check_index1->num_rows == 0) {
        $conn->query("CREATE INDEX idx_notif_user_read ON notifications (user_id, is_read)");
    }
    $check_index2 = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notif_user_id_created'");
    if ($check_index2 && $check_index2->num_rows == 0) {
        $conn->query("CREATE INDEX idx_notif_user_id_created ON notifications (user_id, created_at)");
    }

    // Auto-cleanup expired notifications (older than expires_at OR older than 90 days as fallback)
    $conn->query("DELETE FROM notifications WHERE (expires_at IS NOT NULL AND expires_at < NOW()) OR (created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))");

    // Auto-create user_notification_settings table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS user_notification_settings (
        user_id INT NOT NULL,
        enable_web TINYINT(1) DEFAULT 1,
        enable_line TINYINT(1) DEFAULT 0,
        enable_email TINYINT(1) DEFAULT 0,
        line_token VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (user_id)
    )");

    // Get user preferences
    $settings = null;
    $stmt_set = $conn->prepare("SELECT * FROM user_notification_settings WHERE user_id = ? LIMIT 1");
    if ($stmt_set) {
        $stmt_set->bind_param("i", $user_id);
        $stmt_set->execute();
        $settings = $stmt_set->get_result()->fetch_assoc();
        $stmt_set->close();
    }

    // If no settings exist yet, create default
    if (!$settings) {
        $stmt_ins = $conn->prepare("INSERT IGNORE INTO user_notification_settings (user_id, enable_web, enable_line, enable_email) VALUES (?, 1, 0, 0)");
        if ($stmt_ins) {
            $stmt_ins->bind_param("i", $user_id);
            $stmt_ins->execute();
            $stmt_ins->close();
        }
        $settings = ['enable_web' => 1, 'enable_line' => 0, 'enable_email' => 0, 'line_token' => null];
    }

    // Save to Database / Web Notification (if web enabled)
    $res = false;
    if ($settings['enable_web']) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+90 days'));
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssss", $user_id, $title, $message, $type, $action_url, $expires_at);
            $res = $stmt->execute();
            $stmt->close();
        }
    } else {
        $res = true;
    }

    // Get user email
    $user_email = '';
    $stmt_user = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        if ($u_row = $stmt_user->get_result()->fetch_assoc()) {
            $user_email = $u_row['email'];
        }
        $stmt_user->close();
    }

    // Send LINE Notify if enabled
    if ($settings['enable_line'] && !empty($settings['line_token'])) {
        $line_message = "\n🔔 " . $title . "\n📝 " . $message;
        if (!empty($action_url)) {
            $line_message .= "\n🔗 ตรวจสอบ: " . $action_url;
        }
        send_line_notify((string)$settings['line_token'], $line_message);
    }

    // Send Email if enabled
    if ($settings['enable_email'] && !empty($user_email)) {
        $email_message = "<h3>🔔 แจ้งเตือนจากระบบ DVE System</h3><p><b>" . htmlspecialchars($title) . "</b></p><p>" . htmlspecialchars($message) . "</p>";
        if (!empty($action_url)) {
            $email_message .= "<p><a href='" . htmlspecialchars($action_url) . "' style='display:inline-block;background:#4f46e5;color:white;padding:8px 16px;text-decoration:none;border-radius:6px;font-weight:bold;'>ดูรายละเอียดบนหน้าเว็บ</a></p>";
        }
        send_email_notification($user_email, "🔔 DVE System: " . $title, $email_message);
    }

    return $res;
}

/**
 * Send notification to all users with a specific role
 */
function add_notification_to_role(string $role, string $title, string $message, string $type = 'info', ?string $action_url = null): bool {
    global $conn;
    if (!isset($conn)) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = ?");
    if (!$stmt) return false;
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $res = $stmt->get_result();
    $success = true;
    while ($row = $res->fetch_assoc()) {
        $success = $success && add_notification((int)$row['id'], $title, $message, $type, $action_url);
    }
    $stmt->close();
    return $success;
}

/**
 * Send notification to all students in a classroom
 */
function add_notification_to_classroom(int $classroom_id, string $title, string $message, string $type = 'info', ?string $action_url = null): bool {
    global $conn;
    if (!isset($conn)) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE classroom_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $classroom_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $success = true;
    while ($row = $res->fetch_assoc()) {
        $success = $success && add_notification((int)$row['id'], $title, $message, $type, $action_url);
    }
    $stmt->close();
    return $success;
}

/**
 * บันทึกประวัติกิจกรรมสำคัญลงในฐานข้อมูล (Audit Logs)
 */
function log_audit($action_type, $details) {
    global $conn;
    if (!$conn) return false;
    
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Just in case table check wasn't run manually by installer
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, details, ip_address) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    
    $stmt->bind_param("isss", $user_id, $action_type, $details, $ip);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Alias for log_audit
 */
function log_audit_action($action_type, $details) {
    return log_audit($action_type, $details);
}


/**
 * ปรับปรุงค่าการตั้งค่า
 */
function update_setting(string $key, $value) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ── Storage Management Helpers ─────────────────────────────────────

/**
 * ลบไฟล์รูปภาพรายงานประจำวันออกจากดิสก์ก่อนลบ Record
 */
function delete_report_images(int $report_id): bool {
    global $conn;
    if (!isset($conn) || $report_id <= 0) return false;

    $stmt = $conn->prepare("SELECT image1, image2 FROM daily_reports WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $img_dir1 = __DIR__ . '/../uploads/images/';
        $img_dir2 = __DIR__ . '/../uploads/reports/';

        foreach (['image1', 'image2'] as $field) {
            if (!empty($row[$field])) {
                $filename = basename($row[$field]);
                if (file_exists($img_dir1 . $filename)) @unlink($img_dir1 . $filename);
                if (file_exists($img_dir2 . $filename)) @unlink($img_dir2 . $filename);
            }
        }
    }
    $stmt->close();
    return true;
}

/**
 * ลบไฟล์รูปโปรไฟล์เก่าออกจากดิสก์เมื่อผู้ใช้เปลี่ยนรูปใหม่
 */
function replace_old_avatar(?string $old_filename): void {
    if (empty($old_filename)) return;
    $filename = basename($old_filename);
    $avatar_path = __DIR__ . '/../uploads/avatars/' . $filename;
    if (file_exists($avatar_path) && is_file($avatar_path)) {
        @unlink($avatar_path);
    }
}

// ── CSRF Helper Wrappers ──────────────────────────────────────────
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return \App\Helpers\CsrfHelper::getToken();
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): void {
        \App\Helpers\CsrfHelper::echoInput();
    }
}
if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        return \App\Helpers\CsrfHelper::validate($token);
    }
}

/**
 * ดึงข้อมูลปีการศึกษาที่เปิดใช้งานอยู่ในปัจจุบัน (พร้อมสร้างตารางอัตโนมัติหากยังไม่มี)
 */
function get_active_academic_year(): array {
    global $conn;
    static $active_year = null;
    if ($active_year !== null) return $active_year;

    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'academic_years'");
        if ($table_check && $table_check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS `academic_years` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `year` VARCHAR(10) NOT NULL,
                `term` VARCHAR(10) NOT NULL,
                `title` VARCHAR(100) NOT NULL,
                `start_date` DATE DEFAULT NULL,
                `end_date` DATE DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $conn->query($create_sql);

            // Insert default active academic year
            $curr_buddhist_year = (string)((int)date('Y') + 543);
            $default_title = "ปีการศึกษา {$curr_buddhist_year} ภาคเรียนที่ 1";
            $conn->query("INSERT INTO `academic_years` (`year`, `term`, `title`, `start_date`, `end_date`, `is_active`) VALUES ('{$curr_buddhist_year}', '1', '{$default_title}', '" . date('Y-05-15') . "', '" . date('Y-10-15') . "', 1)");
        }

        $res = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $active_year = $row;
        }
    } catch (Exception $e) {
        error_log("Academic Year Error: " . $e->getMessage());
    }

    if (!$active_year) {
        $curr_buddhist_year = (string)((int)date('Y') + 543);
        $active_year = [
            'id' => 0,
            'year' => $curr_buddhist_year,
            'term' => '1',
            'title' => "ปีการศึกษา {$curr_buddhist_year} ภาคเรียนที่ 1",
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-12-31'),
            'is_active' => 1
        ];
    }

    return $active_year;
}
?>
