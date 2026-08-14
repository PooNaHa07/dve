<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['student']);

$student_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$u_res = $conn->query("SELECT u.*, c.class_name FROM users u LEFT JOIN classrooms c ON u.classroom_id = c.id WHERE u.id = $student_id");
$user = $u_res->fetch_assoc();

// ดึงข้อมูลรายงานตามฟิลเตอร์
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "student_id = $student_id";

if ($filter === 'rejected') {
    $where_clause .= " AND status = 'rejected'";
} elseif ($filter === 'approved') {
    $where_clause .= " AND status = 'approved'";
} elseif ($filter === 'pending') {
    $where_clause .= " AND status = 'pending'";
}

$sql = "SELECT * FROM daily_reports WHERE $where_clause ORDER BY date_work ASC";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมุดบันทึกการปฏิบัติงาน - <?= htmlspecialchars($user['fullname']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #1e3a8a; /* Deep blue for official look */
            --border-color: #94a3b8;
            --bg-color: #f1f5f9;
            --text-main: #0f172a;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #cbd5e1;
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            font-size: 11pt; /* Professional font size */
            line-height: 1.5;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
        }
        
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px double var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header-logo {
            width: 70px;
            height: 70px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            box-shadow: inset 0 0 0 3px white, inset 0 0 0 5px var(--primary-color);
        }
        
        .header-text {
            text-align: center;
            flex-grow: 1;
        }
        
        .header-text h1 {
            font-size: 20pt;
            color: var(--primary-color);
            margin: 0 0 5px 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .header-text p {
            font-size: 13pt;
            margin: 0;
            color: #334155;
            font-weight: 500;
        }
        
        .student-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 30px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .info-row {
            display: flex;
            align-items: baseline;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            width: 140px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #1e293b;
            border-bottom: 1px dotted #cbd5e1;
            flex-grow: 1;
            padding-bottom: 2px;
        }
        
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            table-layout: fixed;
        }
        
        table.report-table th, table.report-table td {
            border: 1px solid var(--border-color);
            padding: 10px;
            vertical-align: top;
            word-wrap: break-word;
        }
        
        table.report-table th {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            font-weight: 600;
            font-size: 12pt;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        table.report-table tbody tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        table.report-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .col-date { width: 14%; text-align: center; }
        .col-details { width: 26%; }
        .col-images { width: 20%; text-align: center; }
        .col-problems { width: 20%; }
        .col-comment { width: 12%; }
        .col-sign { width: 8%; text-align: center; }
        
        .report-img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            padding: 2px;
            background: white;
            border-radius: 4px;
            margin-bottom: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
            margin-top: 5px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .status-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
        
        .signatures-container {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
            padding: 0 30px;
        }

        .signatures-bottom {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        
        .sign-block {
            text-align: center;
            width: 40%;
        }
        
        .sign-line {
            border-bottom: 1px dashed var(--border-color);
            margin: 30px auto 10px;
            width: 80%;
            height: 20px;
        }
        
        .sign-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 12pt;
        }
        
        .sign-role {
            color: #475569;
            font-size: 11pt;
            margin-top: 3px;
        }
        
        .no-print {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            position: sticky;
            top: 10px;
            z-index: 100;
        }
        
        .btn-print {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 14pt;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print:hover {
            background: #1e40af;
            transform: translateY(-2px);
        }

        .btn-close {
            background: #64748b;
        }
        
        .btn-close:hover {
            background: #475569;
        }
        
        @page {
            size: A4;
            margin: 10mm;
        }
        
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .page {
                width: 100%;
                padding: 0;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                min-height: auto;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            พิมพ์เอกสาร (Print)
        </button>
        <button class="btn-print btn-close" onclick="window.close()">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
            ปิดหน้าต่าง
        </button>
    </div>
    
    <div class="page">
        <div class="header-container">
            <div class="header-logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                </svg>
            </div>
            <div class="header-text">
                <h1>สมุดบันทึกการปฏิบัติงาน</h1>
                <p>วิทยาลัยอาชีวศึกษาเพชรบุรี (Phetchaburi Vocational College)</p>
            </div>
            <div style="width: 70px;"></div> <!-- Spacer for perfect centering -->
        </div>
        
        <div class="student-card">
            <div class="info-row">
                <span class="info-label">ชื่อ-สกุลนักศึกษา:</span>
                <span class="info-value"><?= htmlspecialchars($user['fullname']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานประกอบการ:</span>
                <span class="info-value"><?= htmlspecialchars($user['company_name'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">รหัสนักศึกษา:</span>
                <span class="info-value"><?= htmlspecialchars($user['student_code'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ผู้ควบคุมการฝึก:</span>
                <span class="info-value"><?= htmlspecialchars($user['trainer_name'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ห้องเรียน/สาขา:</span>
                <span class="info-value"><?= htmlspecialchars($user['class_name'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">เงื่อนไขรายงาน:</span>
                <span class="info-value">
                    <?php 
                        if ($filter === 'all') echo 'รายการทั้งหมด';
                        elseif ($filter === 'pending') echo 'เฉพาะรอนิเทศก์ตรวจ';
                        elseif ($filter === 'approved') echo 'เฉพาะที่อนุมัติแล้ว';
                        else echo 'เฉพาะที่ต้องแก้ไข';
                    ?>
                </span>
            </div>
        </div>
        
        <table class="report-table">
            <thead>
                <tr>
                    <th class="col-date">วัน/เดือน/ปี</th>
                    <th class="col-details">รายละเอียดการปฏิบัติงาน</th>
                    <th class="col-images">ภาพประกอบ</th>
                    <th class="col-problems">ปัญหาและการแก้ไข</th>
                    <th class="col-comment">ความเห็นผู้ควบคุม</th>
                    <th class="col-sign">ลงชื่อ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td class="col-date">
                                <strong><?= date_thai($row['date_work']) ?></strong><br>
                                <?php
                                    if($row['status'] == 'approved') echo '<span class="status-badge status-approved">อนุมัติแล้ว</span>';
                                    elseif($row['status'] == 'rejected') echo '<span class="status-badge status-rejected">ต้องแก้ไข</span>';
                                    else echo '<span class="status-badge status-pending">รอตรวจ</span>';
                                ?>
                            </td>
                            <td class="col-details">
                                <?= nl2br(htmlspecialchars($row['details'])) ?>
                            </td>
                            <td class="col-images">
                                <?php if($row['image1']): ?>
                                    <img src="../uploads/images/<?= htmlspecialchars($row['image1']) ?>" class="report-img" alt="ภาพ 1">
                                <?php endif; ?>
                                <?php if($row['image2']): ?>
                                    <img src="../uploads/images/<?= htmlspecialchars($row['image2']) ?>" class="report-img" alt="ภาพ 2">
                                <?php endif; ?>
                                <?php if(!$row['image1'] && !$row['image2']): ?>
                                    <span style="color:#94a3b8; font-style:italic; font-size: 10pt;">- ไม่มีภาพประกอบ -</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-problems">
                                <?php if($row['problems']): ?>
                                    <strong>ปัญหา:</strong><br>
                                    <?= nl2br(htmlspecialchars($row['problems'])) ?>
                                    <?php if($row['solutions']): ?>
                                        <br><br><strong>การแก้ไข:</strong><br>
                                        <?= nl2br(htmlspecialchars($row['solutions'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-comment">
                                <?php if($row['teacher_comment']): ?>
                                    <?= nl2br(htmlspecialchars($row['teacher_comment'])) ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-sign">
                                <!-- เว้นว่างไว้สำหรับเซ็นชื่อจริง -->
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color:#64748b;">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:10px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"></path>
                            </svg><br>
                            ไม่มีข้อมูลรายการบันทึกตามเงื่อนไขที่เลือก
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="signatures-container">
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($user['fullname']) ?>)</div>
                <div class="sign-role">นักศึกษา/ผู้จัดทำรายงาน</div>
            </div>
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($user['trainer_name'] ?? '................................................') ?>)</div>
                <div class="sign-role">ครูฝึก/ผู้ควบคุมการฝึกงาน</div>
            </div>
        </div>

        <div class="signatures-bottom">
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars(get_setting('academic_deputy_name', '................................................')) ?>)</div>
                <div class="sign-role">รองผู้อำนวยการฝ่ายวิชาการ</div>
            </div>
        </div>
    </div>
</body>
</html>
