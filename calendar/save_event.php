<?php
// calendar/save_event.php - Handle Add/Edit event via AJAX
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['staff']);

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$event_date_input = trim($_POST['event_date'] ?? '');
$upload_dir = __DIR__ . '/../uploads/event_images/';

if (empty($title) || empty($event_date_input)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

$event_date = date('Y-m-d H:i:s', strtotime($event_date_input . ' 00:00:00'));
$created_at = date('Y-m-d H:i:s');
$image_filename = null;

// Handle file upload
if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
    $file_ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($file_ext, $allowed)) {
        if ($_FILES['event_image']['size'] <= 5 * 1024 * 1024) {
            $image_filename = uniqid('event_', true) . '.' . $file_ext;
            if (!move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_dir . $image_filename)) {
                $image_filename = null;
            }
        }
    }
}

if ($id > 0) {
    // Edit Mode
    if ($image_filename) {
        // Get old image to delete
        $old_stmt = $conn->prepare("SELECT image_filename FROM calendar_events WHERE id = ?");
        $old_stmt->bind_param("i", $id);
        $old_stmt->execute();
        $old_res = $old_stmt->get_result()->fetch_assoc();
        if ($old_res && $old_res['image_filename'] && file_exists($upload_dir . $old_res['image_filename'])) {
            @unlink($upload_dir . $old_res['image_filename']);
        }
        
        $sql = "UPDATE calendar_events SET title = ?, description = ?, event_date = ?, image_filename = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $title, $description, $event_date, $image_filename, $id);
    } else {
        $sql = "UPDATE calendar_events SET title = ?, description = ?, event_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $title, $description, $event_date, $id);
    }
} else {
    // Add Mode
    $sql = "INSERT INTO calendar_events (title, description, image_filename, event_date, created_at) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $title, $description, $image_filename, $event_date, $created_at);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
}

$stmt->close();
