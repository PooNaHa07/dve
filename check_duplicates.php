<?php
require_once __DIR__ . '/includes/configdb.php';

// Check if some names from the CSV are in the database
$sample_names = [
    'นางสาวกชกร  ชูเนียม',
    'นางสาวกนกพร  เนียมศิริ',
    'นางสาวกนกวรรณ  ประทีป',
    'นางสาวกมลชนก  ขำสวัสดิ์'
];

foreach ($sample_names as $name) {
    // Exact match
    $stmt = $conn->prepare("SELECT id, username, fullname, student_code FROM users WHERE fullname = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    echo "Searching for '$name' (Exact):\n";
    while ($row = $res->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Username: {$row['username']}, Code: {$row['student_code']}\n";
    }
    
    // Normalized check
    $norm_name = str_replace(' ', '', $name);
    $stmt2 = $conn->prepare("SELECT id, username, fullname, student_code FROM users WHERE REPLACE(fullname, ' ', '') LIKE ?");
    $search = "%" . $norm_name . "%";
    $stmt2->bind_param("s", $search);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    echo "Searching for '$norm_name' (Normalized/Like):\n";
    while ($row = $res2->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Username: {$row['username']}, Code: {$row['student_code']}, Fullname: {$row['fullname']}\n";
    }
    echo "\n";
}
