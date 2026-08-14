<?php
// documents/save_document.php - Handle Add/Edit document via AJAX
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['admin', 'staff']);

header('Content-Type: application/json');

// 1. กำหนดค่าเริ่มต้นและตรวจสอบโฟลเดอร์
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = trim($_POST['title'] ?? '');
$upload_dir = __DIR__ . '/../uploads/documents/';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 2. ตรวจสอบข้อมูลพื้นฐาน
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อเอกสาร']);
    exit;
}

// 3. จัดการการอัปโหลดไฟล์
$filename = null;
if (isset($_FILES['doc_file']) && $_FILES['doc_file']['name'] !== '') {
    $file = $_FILES['doc_file'];
    
    // ตรวจสอบ Error Code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'ขนาดไฟล์เกินขีดจำกัดของเซิร์ฟเวอร์ (php.ini)',
            UPLOAD_ERR_FORM_SIZE  => 'ขนาดไฟล์เกินขีดจำกัดที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL    => 'ไฟล์ถูกอัปโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE    => 'ไม่มีไฟล์ถูกอัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับเก็บไฟล์',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
            UPLOAD_ERR_EXTENSION  => 'การอัปโหลดไฟล์ถูกระงับโดย Extension',
        ];
        echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาดในการอัปโหลด: ' . ($error_messages[$file['error']] ?? 'Unknown error')]);
        exit;
    }

    // ตรวจสอบนามสกุลไฟล์ที่อนุญาต
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
    $file_info = pathinfo($file['name']);
    $ext = strtolower($file_info['extension']);
    
    if (!in_array($ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'ไม่อนุญาตให้อัปโหลดไฟล์นามสกุลนี้ (. ' . implode(', .', $allowed_exts) . ')']);
        exit;
    }

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 20MB)
    $max_size = 20 * 1024 * 1024; // 20MB
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'ขนาดไฟล์ใหญ่เกินไป (ต้องไม่เกิน 20MB)']);
        exit;
    }

    // สร้างชื่อไฟล์ใหม่เพื่อป้องกันการซ้ำและอักขระพิเศษ
    $filename = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถย้ายไฟล์ไปยังโฟลเดอร์ปลายทางได้']);
        exit;
    }
}

// 4. บันทึกลงฐานข้อมูล
try {
    if ($id > 0) {
        // --- กรณีแก้ไข (Edit) ---
        if ($filename) {
            // ลบไฟล์เก่าออกก่อน
            $old_stmt = $conn->prepare("SELECT filename FROM documents WHERE id = ?");
            $old_stmt->bind_param("i", $id);
            $old_stmt->execute();
            $old_res = $old_stmt->get_result()->fetch_assoc();
            if ($old_res && $old_res['filename'] && file_exists($upload_dir . $old_res['filename'])) {
                @unlink($upload_dir . $old_res['filename']);
            }

            $stmt = $conn->prepare("UPDATE documents SET title = ?, filename = ? WHERE id = ?");
            $stmt->bind_param("ssi", $title, $filename, $id);
        } else {
            $stmt = $conn->prepare("UPDATE documents SET title = ? WHERE id = ?");
            $stmt->bind_param("si", $title, $id);
        }
    } else {
        // --- กรณีเพิ่มใหม่ (Add) ---
        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'กรุณาเลือกไฟล์เอกสารที่ต้องการอัปโหลด']);
            exit;
        }
        $created_at = date('Y-m-d H:i:s');
        $uploaded_by = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("INSERT INTO documents (title, filename, created_at, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $filename, $created_at, $uploaded_by);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    // ถ้าบันทึก DB ไม่สำเร็จ และมีการอัปโหลดไฟล์ใหม่ ให้ลบไฟล์ที่เพิ่งอัปโหลดทิ้งเพื่อไม่ให้รก
    if ($filename && file_exists($upload_dir . $filename)) {
        @unlink($upload_dir . $filename);
    }
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดทางฐานข้อมูล: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
}
