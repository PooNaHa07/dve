<?php
// calendar/get_event.php - Fetch event data for editing
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_role(['staff']);

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, title, description, event_date, image_filename FROM calendar_events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $event = $result->fetch_assoc();
    // Format date for input[type=date]
    $event['event_date_formatted'] = date('Y-m-d', strtotime($event['event_date']));
    echo json_encode(['success' => true, 'data' => $event]);
} else {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
}

$stmt->close();
