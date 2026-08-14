<?php
session_start();
// เช็คสิทธิ์เจ้าหน้าที่
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: ../index.php"); exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ดึงข้อมูลรายงานและข้อมูลนักเรียนจาก daily_reports
$sql = "SELECT 
            d.id,
            d.student_id,
            u.fullname,
            u.student_code,
            d.details AS work_details,
            d.problems,
            d.status,
            d.created_at,
            WEEK(d.date_work, 1) AS week_no
        FROM daily_reports d
        JOIN users u ON d.student_id = u.id
        WHERE d.id = $id";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

if (!$row) { echo 'ไม่พบข้อมูล'; exit; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์รายงานสัปดาห์ที่ <?= $row['week_no'] ?> - <?= e($row['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; font-size: 14pt; }
            .container { width: 100%; max-width: 100%; }
        }
        body { font-family: 'Sarabun', sans-serif; background-color: white; }
        .report-header { text-align: center; margin-bottom: 30px; }
        .content-box { border: 1px solid #000; padding: 15px; min-height: 150px; margin-bottom: 20px; }
        .signature-section { margin-top: 50px; }
        .sig-line { border-bottom: 1px dotted #000; width: 200px; display: inline-block; margin-top: 30px; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="no-print mb-4 text-end">
        <button onclick="window.print();" class="btn btn-primary">🖨️ คลิกเพื่อพิมพ์เอกสาร</button>
        <button onclick="window.close();" class="btn btn-secondary">ปิดหน้านี้</button>
    </div>

    <div class="report-header">
        <h3>รายงานการปฏิบัติงานประจำสัปดาห์</h3>
        <h5>สัปดาห์ที่ <?= $row['week_no'] ?></h5>
    </div>

    <table class="table table-bordered">
        <tr>
            <th width="20%">ชื่อ-นามสกุล</th>
            <td><?= e($row['fullname']) ?></td>
            <th width="20%">รหัสนักเรียน</th>
            <td><?= e($row['student_code']) ?></td>
        </tr>
        <tr>
            <th>วันที่ส่งรายงาน</th>
            <td colspan="3"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
        </tr>
    </table>

    <div class="mt-4">
        <h6><strong>รายละเอียดการปฏิบัติงาน:</strong></h6>
        <div class="content-box"><?= nl2br(e($row['work_details'])) ?></div>
    </div>

    <div class="mt-4">
        <h6><strong>ปัญหาและอุปสรรค:</strong></h6>
        <div class="content-box"><?= nl2br(e($row['problems'] ? $row['problems'] : '-')) ?></div>
    </div>

    <div class="mt-4">
        <h6><strong>ความเห็นครูนิเทศก์:</strong></h6>
        <div class="content-box"><?= $row['supervisor_comment'] ? nl2br(e($row['supervisor_comment'])) : '-' ?></div>
    </div>

    <div class="row signature-section text-center">
        <div class="col-4">
            <span class="sig-line"></span><br>
            (<?= e($row['fullname']) ?>)<br>
            นักเรียน/นักศึกษา
        </div>
        <div class="col-4">
            <span class="sig-line"></span><br>
            (...........................................)<br>
            ครูนิเทศก์
        </div>
        <div class="col-4">
            <span class="sig-line"></span><br>
            (...........................................)<br>
            เจ้าหน้าที่งานทวิภาคี
        </div>
    </div>
</div>

</body>
</html>