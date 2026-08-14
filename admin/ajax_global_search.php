<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];
$term = "%" . $q . "%";

// 1. ค้นหาผู้ใช้ (นักเรียน/ครู)
$stmt = $conn->prepare("SELECT id, fullname, role, username FROM users WHERE (fullname LIKE ? OR username LIKE ?) LIMIT 5");
$stmt->bind_param("ss", $term, $term);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $typeLabel = $row['role'] == 'student' ? '👨‍🎓 นักเรียน' : ($row['role'] == 'teacher' ? '👨‍🏫 ครู' : '👤 ผู้ใช้');
    $link = "../admin/manage_users.php?role=" . $row['role'] . "&search=" . urlencode($row['fullname']);
    $results[] = [
        'label' => $row['fullname'] . " (" . $row['username'] . ")",
        'category' => $typeLabel,
        'link' => $link,
        'icon' => $row['role'] == 'student' ? 'bi-person' : 'bi-person-badge'
    ];
}
$stmt->close();

// 2. ค้นหาสถานประกอบการ
$stmt = $conn->prepare("SELECT id, name FROM companies WHERE name LIKE ? LIMIT 3");
$stmt->bind_param("s", $term);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $results[] = [
        'label' => $row['name'],
        'category' => '🏢 สถานประกอบการ',
        'link' => '../admin/companies.php?search=' . urlencode($row['name']),
        'icon' => 'bi-building'
    ];
}
$stmt->close();

// 3. ค้นหาห้องเรียน
$stmt = $conn->prepare("SELECT id, class_name FROM classrooms WHERE class_name LIKE ? LIMIT 3");
$stmt->bind_param("s", $term);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $results[] = [
        'label' => $row['class_name'],
        'category' => '🏫 ห้องเรียน',
        'link' => '../admin/manage_classrooms.php?search=' . urlencode($row['class_name']),
        'icon' => 'bi-door-open'
    ];
}
$stmt->close();

echo json_encode($results);
exit;
