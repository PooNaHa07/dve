<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

global $conn;

// รับค่าการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
if (!empty($search)) {
    $search_term = "%" . $conn->real_escape_string($search) . "%";
    $where_clause = " WHERE name LIKE '$search_term'
                      OR address LIKE '$search_term'
                      OR contact_name LIKE '$search_term'";
}

// Query
$sql = "SELECT id, name, address, contact_name, contact_phone, contact_email, created_at 
        FROM companies {$where_clause} ORDER BY id DESC";
$result = $conn->query($sql);
if (!$result) die("DB Error: " . $conn->error);

// ---------- CSV Header ----------
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="companies_export_' . date('Ymd_His') . '.csv"');

// ***** สำคัญที่สุด: ใส่ UTF-8 BOM ให้ Excel อ่านไทยได้ *****
echo "\xEF\xBB\xBF";

// เปิด output
$output = fopen('php://output', 'w');

// header ชื่อคอลัมน์
$header = [
    'ID',
    'ชื่อบริษัท',
    'ที่อยู่',
    'ชื่อผู้ติดต่อหลัก',
    'เบอร์โทรศัพท์',
    'อีเมลติดต่อ',
    'วันที่เพิ่มข้อมูล'
];

// เขียน header ลง CSV
fputcsv($output, $header);

// เขียนข้อมูลทีละแถว
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
