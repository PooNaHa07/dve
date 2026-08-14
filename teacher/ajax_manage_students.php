<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

header('Content-Type: application/json');

$u = current_user();
$teacher_id = $u['id'];

// --- ดึงรายการห้องเรียนที่ครูรับผิดชอบ ---
$my_room_ids = [];
$res_my_rooms = $conn->query("SELECT classroom_id FROM teacher_assignments WHERE teacher_id = '$teacher_id'");
while($r = $res_my_rooms->fetch_assoc()) {
    $my_room_ids[] = (int)$r['classroom_id'];
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

if ($action === 'search_students') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $search_term = "%$q%";
    $stmt = $conn->prepare("
        SELECT u.id, u.fullname, u.username, u.student_code, u.classroom_id, cl.class_name 
        FROM users u 
        LEFT JOIN classrooms cl ON u.classroom_id = cl.id 
        WHERE u.role = 'student' 
          AND (u.fullname LIKE ? OR u.username LIKE ? OR u.student_code LIKE ?)
        LIMIT 20
    ");
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while($row = $result->fetch_assoc()) {
        $students[] = [
            'id' => (int)$row['id'],
            'fullname' => $row['fullname'],
            'username' => $row['username'],
            'student_code' => $row['student_code'],
            'classroom_id' => $row['classroom_id'] ? (int)$row['classroom_id'] : null,
            'class_name' => $row['class_name'] ?: 'ไม่ระบุห้อง'
        ];
    }
    
    echo json_encode($students);
    exit;
}

if ($action === 'add_to_classroom') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $classroom_id = isset($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : 0;
    
    if ($student_id <= 0 || $classroom_id <= 0) {
        echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    
    // ตรวจสอบว่าครูคนนี้รับผิดชอบห้องเรียนนี้จริงหรือไม่
    if (!in_array($classroom_id, $my_room_ids)) {
        echo json_encode(['error' => 'คุณไม่มีสิทธิ์ในการจัดการห้องเรียนนี้']);
        exit;
    }
    
    // ตรวจสอบข้อมูลนักเรียนก่อน
    $stmt_check = $conn->prepare("SELECT fullname, classroom_id FROM users WHERE id = ? AND role = 'student'");
    $stmt_check->bind_param("i", $student_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $student = $res_check->fetch_assoc();
    $stmt_check->close();
    
    if (!$student) {
        echo json_encode(['error' => 'ไม่พบรายชื่อนักเรียนคนนี้ในระบบ']);
        exit;
    }
    
    // อัปเดตห้องเรียนของนักเรียน
    $stmt_update = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ? AND role = 'student'");
    $stmt_update->bind_param("ii", $classroom_id, $student_id);
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'เพิ่มนักเรียนเข้าห้องเรียนเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['error' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $conn->error]);
    }
    $stmt_update->close();
    exit;
}

if ($action === 'remove_from_classroom') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    
    if ($student_id <= 0) {
        echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    
    // ตรวจสอบข้อมูลนักเรียนและห้องปัจจุบันก่อน
    $stmt_check = $conn->prepare("SELECT fullname, classroom_id FROM users WHERE id = ? AND role = 'student'");
    $stmt_check->bind_param("i", $student_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $student = $res_check->fetch_assoc();
    $stmt_check->close();
    
    if (!$student) {
        echo json_encode(['error' => 'ไม่พบรายชื่อนักเรียนคนนี้ในระบบ']);
        exit;
    }
    
    $current_classroom_id = $student['classroom_id'] ? (int)$student['classroom_id'] : 0;
    
    // ตรวจสอบว่านักเรียนอยู่ในห้องที่ครูคนนี้รับผิดชอบจริงหรือไม่
    if (!in_array($current_classroom_id, $my_room_ids)) {
        echo json_encode(['error' => 'คุณไม่มีสิทธิ์ในการจัดการนักเรียนที่อยู่นอกห้องเรียนที่ดูแล']);
        exit;
    }
    
    // อัปเดตห้องเรียนเป็น NULL
    $stmt_update = $conn->prepare("UPDATE users SET classroom_id = NULL WHERE id = ? AND role = 'student'");
    $stmt_update->bind_param("i", $student_id);
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'นำนักเรียนออกจากห้องเรียนเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['error' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $conn->error]);
    }
    $stmt_update->close();
    exit;
}

if ($action === 'change_classroom') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $new_classroom_id = isset($_POST['new_classroom_id']) ? (int)$_POST['new_classroom_id'] : 0;
    
    if ($student_id <= 0) {
        echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    
    // ตรวจสอบข้อมูลนักเรียนและห้องปัจจุบันก่อน
    $stmt_check = $conn->prepare("SELECT fullname, classroom_id FROM users WHERE id = ? AND role = 'student'");
    $stmt_check->bind_param("i", $student_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $student = $res_check->fetch_assoc();
    $stmt_check->close();
    
    if (!$student) {
        echo json_encode(['error' => 'ไม่พบรายชื่อนักเรียนคนนี้ในระบบ']);
        exit;
    }
    
    $current_classroom_id = $student['classroom_id'] ? (int)$student['classroom_id'] : 0;
    
    // ตรวจสอบสิทธิ์: ครูคนนี้ต้องรับผิดชอบห้องปัจจุบัน หรือห้องใหม่ (หรือทั้งสองห้อง)
    $has_access = in_array($current_classroom_id, $my_room_ids) || in_array($new_classroom_id, $my_room_ids);
    
    if (!$has_access) {
        echo json_encode(['error' => 'คุณไม่มีสิทธิ์ในการจัดการห้องเรียนของนักเรียนคนนี้']);
        exit;
    }
    
    // อัปเดตห้องเรียนของนักเรียน (ถ้า new_classroom_id เป็น 0 ให้เป็น NULL)
    $update_val = ($new_classroom_id > 0) ? $new_classroom_id : null;
    if ($update_val === null) {
        $stmt_update = $conn->prepare("UPDATE users SET classroom_id = NULL WHERE id = ? AND role = 'student'");
        $stmt_update->bind_param("i", $student_id);
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ? AND role = 'student'");
        $stmt_update->bind_param("ii", $update_val, $student_id);
    }
    
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'ย้ายห้องเรียนของนักเรียนเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['error' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $conn->error]);
    }
    $stmt_update->close();
    exit;
}

echo json_encode(['error' => 'Invalid Action']);
exit;
