<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['line_token']) ? trim($input['line_token']) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'โปรดระบุ LINE Notify Token สำหรับการทดสอบ']);
    exit;
}

// Prepare test message
$test_message = "\n🔔 DVE System Test\n\nการทดสอบการแจ้งเตือนเสร็จสมบูรณ์!\nระบบ LINE Notify ของคุณเชื่อมต่อถูกต้องแล้วค่ะ 🚀";

// Call send_line_notify helper
$result = send_line_notify($token, $test_message);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'ส่งข้อความทดสอบไปยัง LINE เรียบร้อยแล้ว โปรดตรวจสอบในแอป LINE ของคุณ']);
} else {
    echo json_encode(['success' => false, 'message' => 'การส่งล้มเหลว ตรวจสอบความถูกต้องของ Token หรือการเชื่อมต่ออินเทอร์เน็ต']);
}
