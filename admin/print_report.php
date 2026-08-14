<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// 1. ดึงข้อมูลนักเรียน
$user_res = $conn->query("SELECT fullname, username FROM users WHERE id = $student_id");
$user = $user_res->fetch_assoc();

if (!$user) { die("ไม่พบข้อมูลนักเรียน"); }

// 2. ดึงข้อมูลบันทึกทั้งหมด
$sql = "SELECT * FROM daily_reports WHERE student_id = $student_id ORDER BY date_work ASC";
$reports = $conn->query($sql);

// 3. กำหนด Path สำหรับแสดงผลบนหน้าเว็บ (Relative Path)
// จาก admin/ ไปยัง uploads/images/
$web_path = "../uploads/images/";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงาน_<?= htmlspecialchars($user['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; font-size: 13px; color: #000; }
        @media print {
            .no-print { display: none !important; }
            @page { size: A4; margin: 1cm; }
            body { margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000 !important; padding: 5px !important; vertical-align: top; }
        }
        .report-header { text-align: center; margin-bottom: 20px; }
        .img-report { 
            width: 80px; height: 80px; 
            object-fit: cover; border: 1px solid #000; margin: 2px;
        }
        table th { background-color: #eee !important; text-align: center; }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="no-print text-end mb-3">
        <button onclick="window.print();" class="btn btn-primary btn-sm">พิมพ์เอกสาร</button>
        <button onclick="window.close();" class="btn btn-secondary btn-sm">ปิด</button>
    </div>

    <div class="report-header">
        <h4 class="fw-bold">สมุดบันทึกการปฏิบัติงานรายวัน</h4>
        <p>ชื่อ-นามสกุล: <b><?= htmlspecialchars($user['fullname']) ?></b> | รหัส: <b><?= htmlspecialchars($user['username']) ?></b></p>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th style="width: 12%;">วันที่</th>
                <th>รายละเอียดงาน</th>
                <th style="width: 20%;">รูปภาพประกอบ</th>
                <th style="width: 15%;">สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports && $reports->num_rows > 0): while($rp = $reports->fetch_assoc()): ?>
            <tr>
                <td class="text-center"><?= date('d/m/Y', strtotime($rp['date_work'])) ?></td>
                <td>
                    <b>งาน:</b> <?= nl2br(htmlspecialchars($rp['details'])) ?><br>
                    <small class="text-danger"><b>ปัญหา:</b> <?= htmlspecialchars($rp['problems'] ?: '-') ?></small>
                </td>
                <td class="text-center">
                    <?php 
                    // แสดงรูปที่ 1 ถ้ามีชื่อไฟล์ใน DB
                    if(!empty($rp['image1'])){
                        echo '<img src="'.$web_path . trim($rp['image1']).'" class="img-report">';
                    }
                    // แสดงรูปที่ 2 ถ้ามีชื่อไฟล์ใน DB
                    if(!empty($rp['image2'])){
                        echo '<img src="'.$web_path . trim($rp['image2']).'" class="img-report">';
                    }
                    if(empty($rp['image1']) && empty($rp['image2'])){
                        echo '<small class="text-muted">- ไม่มีรูป -</small>';
                    }
                    ?>
                </td>
                <td class="text-center">
                    <small><?= $rp['status'] == 'approved' ? 'อนุมัติแล้ว' : 'รอดำเนินการ' ?></small>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" class="text-center">ไม่พบข้อมูล</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>