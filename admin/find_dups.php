<?php
require_once __DIR__ . '/../includes/configdb.php';

$res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND (student_code IS NULL OR student_code = '')");
echo "Students with NULL or empty student_code: " . $res->fetch_assoc()['cnt'] . "\n";

$res = $conn->query("SELECT id, username, fullname, student_code FROM users WHERE role = 'student' AND (student_code IS NULL OR student_code = '') LIMIT 10");
echo "\nFirst 10 students with empty/NULL code:\n";
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']}, Username: {$row['username']}, Fullname: '{$row['fullname']}'\n";
}
?>
