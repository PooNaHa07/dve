<?php
// admin/save_user.php - Handle user create/update via AJAX
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $classroom_id = !empty($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : null;
    $student_level = $_POST['student_level'];
    $mentor_id = !empty($_POST['mentor_id']) ? (int)$_POST['mentor_id'] : null;
    $mentor_id_2 = !empty($_POST['mentor_id_2']) ? (int)$_POST['mentor_id_2'] : null;
    $mentor_id_3 = !empty($_POST['mentor_id_3']) ? (int)$_POST['mentor_id_3'] : null;
    $mentor_id_4 = !empty($_POST['mentor_id_4']) ? (int)$_POST['mentor_id_4'] : null;
    $mentor_id_5 = !empty($_POST['mentor_id_5']) ? (int)$_POST['mentor_id_5'] : null;
    $student_code = !empty($_POST['student_code']) ? trim($_POST['student_code']) : null;
    $company_manager = isset($_POST['company_manager']) ? trim($_POST['company_manager']) : null;

    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
    $affiliation = !empty($_POST['affiliation']) ? trim($_POST['affiliation']) : null;

    if (empty($username) || empty($fullname) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (Username, Fullname, Role)']);
        exit;
    }

    if ($id === 0) {
        // Create new user
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'กรุณากำหนดรหัสผ่านสำหรับผู้ใช้ใหม่']);
            exit;
        }
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username นี้มีอยู่ในระบบแล้ว']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // Check if email exists (only if not empty, to prevent unique key violation)
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานโดยผู้ใช้อื่นในระบบแล้ว']);
                $stmt->close();
                exit;
            }
            $stmt->close();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, phone, affiliation, role, classroom_id, student_level, mentor_id, mentor_id_2, mentor_id_3, mentor_id_4, mentor_id_5, student_code, company_manager) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssisiiiiiss", $username, $hashed_password, $fullname, $email, $phone, $affiliation, $role, $classroom_id, $student_level, $mentor_id, $mentor_id_2, $mentor_id_3, $mentor_id_4, $mentor_id_5, $student_code, $company_manager);
    } else {
        // Check if username exists on other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username นี้มีอยู่ในระบบแล้ว']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // Check if email exists on other users
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานโดยผู้ใช้อื่นในระบบแล้ว']);
                $stmt->close();
                exit;
            }
            $stmt->close();
        }

        // Update existing user
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, fullname=?, email=?, phone=?, affiliation=?, role=?, classroom_id=?, student_level=?, mentor_id=?, mentor_id_2=?, mentor_id_3=?, mentor_id_4=?, mentor_id_5=?, student_code=?, company_manager=? WHERE id=?");
            $stmt->bind_param("sssssssisiiiiissi", $username, $hashed_password, $fullname, $email, $phone, $affiliation, $role, $classroom_id, $student_level, $mentor_id, $mentor_id_2, $mentor_id_3, $mentor_id_4, $mentor_id_5, $student_code, $company_manager, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, fullname=?, email=?, phone=?, affiliation=?, role=?, classroom_id=?, student_level=?, mentor_id=?, mentor_id_2=?, mentor_id_3=?, mentor_id_4=?, mentor_id_5=?, student_code=?, company_manager=? WHERE id=?");
            $stmt->bind_param("ssssssisiiiiissi", $username, $fullname, $email, $phone, $affiliation, $role, $classroom_id, $student_level, $mentor_id, $mentor_id_2, $mentor_id_3, $mentor_id_4, $mentor_id_5, $student_code, $company_manager, $id);
        }
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
    }
    
    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลซ้ำ: มีข้อมูลชื่อผู้ใช้ (Username) หรืออีเมลนี้อยู่ในระบบแล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาดทางฐานข้อมูล: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
