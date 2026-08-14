<?php
date_default_timezone_set('Asia/Bangkok');
require_once '../includes/configdb.php';
require_once '../includes/functions.php'; 
require_login(); 

$u = current_user();
$role = isset($u['role']) ? $u['role'] : '';

// ตรวจสอบสิทธิ์: ถ้าเป็นครู/เจ้าหน้าที่/แอดมิน/ผู้บริหาร ให้รับค่าจาก URL ถ้าเป็นนักเรียนให้ใช้ ID ตัวเอง
if ((in_array($role, ['teacher', 'admin', 'staff', 'director'], true)) && isset($_GET['id'])) {
    $target_id = intval($_GET['id']);
} else {
    $target_id = $u['id'];
}

function thai_num($num) {
    return str_replace(
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        array('๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'),
        $num
    );
}

function thai_date($strDate) {
    if(!$strDate) return "-";
    $thai_month_arr = array(
        "", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    );
    $time = strtotime($strDate);
    $d = thai_num(date("j", $time));
    $m = $thai_month_arr[date("n", $time)];
    $y = thai_num(date("Y", $time) + 543);
    return $d . " " . $m . " พ.ศ. " . $y;
}

// SQL ดึงข้อมูลเฉพาะนักเรียนที่ต้องการ (ชื่อสถานประกอบการจาก companies หรือจาก users.company_name)
$sql = "SELECT u.fullname, u.company_name AS user_company_name, u.company_manager, e.grade, e.created_at AS eval_date,
        COALESCE(c.name, u.company_name) AS company_name
        FROM users u
        JOIN evaluations e ON u.id = e.student_id
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.id = $target_id
        AND e.grade IS NOT NULL AND e.grade != ''";

$result = $conn->query($sql);
$data = $result->fetch_assoc(); 

// ดึงชื่อผู้อำนวยการและชื่อวิทยาลัยจากการตั้งค่าระบบ
$director_name = get_setting('cert_director_name', 'นางวรากร หิรัญมณีมาศ');
$cert_college_name = get_setting('cert_college_name', 'วิทยาลัยอาชีวศึกษาเพชรบุรี');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์เกียรติบัตร - <?php echo isset($data['fullname']) ? $data['fullname'] : 'Error'; ?></title>
    <link rel="stylesheet" href="https://fastly.jsdelivr.net/npm/thai-fonts@1.0.0/css/th-sarabun-new.min.css">
    <style>
        @page { size: landscape; margin: 0; }
        body { font-family: 'TH Sarabun New', sans-serif; background: #555; margin: 0; padding: 0; }
        .no-print { position: sticky; top: 0; z-index: 1000; text-align: center; padding: 15px; background: rgba(0,0,0,0.8); }
        .certificate-page {
            width: 297mm; height: 209mm; padding: 12mm 15mm; margin: 20px auto;
            background: white; border: 15px double #bc9c1a; box-sizing: border-box;
            display: flex; flex-direction: column; justify-content: flex-start;
            box-shadow: 0 0 20px rgba(0,0,0,0.3); overflow: hidden; page-break-after: always;
        }
        .content {
            text-align: center; flex: 0 1 auto; max-height: calc(209mm - 22mm - 35mm);
            display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
        }
        .content .cert-logo { width: 100px; height: auto; margin-bottom: 2mm; }
        .content h1 { font-size: 22pt; color: #6c1a1a; margin: 3mm 0; font-weight: bold; line-height: 1.2; }
        .student-name {
            font-size: 36pt; font-weight: bold; margin: 4mm 0; color: #000;
            display: inline-block; padding: 0 30px; border-bottom: 2px solid #000;
            line-height: 1.2;
        }
        .details { font-size: 18pt; line-height: 1.35; color: #333; font-weight: normal; margin: 2mm 0; max-width: 90%; }
        .company-name { font-size: 18pt; font-weight: bold; margin: 2mm 0 4mm; word-break: break-word; max-width: 85%; }
        .cert-date { font-size: 16pt; margin-top: 3mm; }
        .footer {
            flex-shrink: 0; display: flex; justify-content: space-around; align-items: flex-start;
            padding-top: 8mm; padding-bottom: 15mm; margin-top: auto; min-height: 32mm;
        }
        .signature {
            text-align: center; width: 320px; font-size: 16pt; font-weight: bold;
            display: flex; flex-direction: column; align-items: center;
        }
        /* ระยะจากบนเท่ากัน แล้วค่อยเส้น แล้วค่อยข้อความ → เส้นอยู่ระดับเดียวกัน */
        .signature .sign-line-wrap { height: 6mm; display: flex; align-items: flex-end; justify-content: center; width: 100%; }
        .signature .sign-line {
            border-top: 1.5px solid #000; width: 80%; min-width: 120px; margin: 0;
        }
        .signature .sign-caption { line-height: 1.3; margin-top: 4px; }
        .signature .sign-caption.one-line { white-space: nowrap; }
        @media print { .no-print { display: none; } body { background: none; } .certificate-page { margin: 0; box-shadow: none; border: 15px double #bc9c1a !important; } }
    </style>
</head>
<body>

    <div class="no-print">
        <?php if ($data): ?>
            <button onclick="window.print()" style="padding: 12px 25px; background: #28a745; color: white; cursor: pointer; border-radius: 5px; border:none; font-size: 16px; font-weight: bold;">
                🖨️ พิมพ์เกียรติบัตร
            </button>
        <?php endif; ?>
        <?php
        // กำหนดลิงก์ย้อนกลับตามบทบาท
        if ($role === 'student') {
            $backUrl = '../roles/student.php';
        } elseif ($role === 'teacher') {
            $backUrl = '../teacher/assessment_list.php';
        } elseif ($role === 'admin' || $role === 'staff') {
            $backUrl = 'certificates_list.php';
        } else {
            $backUrl = '../index.php';
        }
        ?>
        <a href="<?php echo htmlspecialchars($backUrl); ?>" 
           style="padding: 12px 25px; background: #fff; color: #333; text-decoration: none; border-radius: 5px; margin-left: 10px; font-size: 18px; border: 1px solid #ccc;">
           ย้อนกลับ
        </a>
    </div>

    <?php if ($data): ?>
        <div class="certificate-page">
            <div class="content">
                <img src="../images/logo.png" alt="" class="cert-logo">
                <h1>เกียรติบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า</h1>

                <div class="student-name"><?php echo htmlspecialchars($data['fullname']); ?></div>

                <div class="details">ได้ผ่านการฝึกประสบการณ์วิชาชีพในสถานประกอบการ</div>
                <div class="company-name">ณ <?php echo isset($data['company_name']) && $data['company_name'] !== '' ? htmlspecialchars($data['company_name']) : '................................................'; ?></div>
                <div class="details">ผลการประเมินอยู่ในระดับ <strong>"<?php echo thai_num(htmlspecialchars($data['grade'])); ?>"</strong></div>

                <div class="details cert-date">ให้ไว้ ณ วันที่ <?php echo thai_date(isset($data['eval_date']) ? $data['eval_date'] : date('Y-m-d')); ?></div>
            </div>

            <div class="footer">
                <div class="signature">
                    <div class="sign-line-wrap"><div class="sign-line"></div></div>
                    <div class="sign-caption">
                        (<?php echo isset($data['company_manager']) && $data['company_manager'] !== '' ? htmlspecialchars($data['company_manager']) : '...........................................'; ?>)<br>
                        ผู้มีอำนาจลงนามสูงสุด
                    </div>
                </div>
                <div class="signature">
                    <div class="sign-line-wrap"><div class="sign-line"></div></div>
                    <div class="sign-caption one-line">
                        (<?php echo htmlspecialchars($director_name); ?>)<br>
                        ผู้อำนวยการ<?php echo htmlspecialchars($cert_college_name); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; color: white; margin-top: 100px;">
            <h2>ไม่พบข้อมูลเกียรติบัตร</h2>
            <p>นักเรียนอาจยังไม่ได้รับการประเมิน หรือคุณไม่มีสิทธิ์ดูข้อมูลของผู้อื่น</p>
        </div>
    <?php endif; ?>

</body>
</html>