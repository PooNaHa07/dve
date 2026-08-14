<?php
/**
 * สร้าง PDF จากแบบฟอร์ม + ลายเซ็น (โหมด draw) แล้วบันทึกลงใบนิเทศ (supervision_files)
 * เรียกจาก mentors/upload_supervision.php — รับ POST: template, mode=draw, page_1, page_2, ..., student_id
 */
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['mentor', 'teacher', 'staff', 'admin']);

$u = current_user();
$teacher_id = (int) $u['id'];
$student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

$redirectBase = '../mentors/upload_supervision.php';

if ($student_id <= 0) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('กรุณาเลือกนักเรียน'));
    exit;
}

$allowed_templates = ['pgfa.pdf', 'pgga.pdf', 'twifa.pdf', 'twipa.pdf'];
$template = isset($_POST['template']) ? basename($_POST['template']) : '';
if (!in_array($template, $allowed_templates)) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('ไม่พบแบบฟอร์มที่เลือก'));
    exit;
}

$templatePath = __DIR__ . '/' . $template;
if (!file_exists($templatePath)) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('ไม่พบไฟล์แบบฟอร์ม'));
    exit;
}

$tmpDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

function saveDrawAsPng($base64Data, $tmpDir) {
    if (empty($base64Data) || !preg_match('/^data:image\/png;base64,/', $base64Data)) {
        return null;
    }
    $img = base64_decode(preg_replace('#^data:image/png;base64,#i', '', $base64Data));
    if ($img === false) {
        return null;
    }
    $path = $tmpDir . 'draw_' . uniqid() . '.png';
    if (file_put_contents($path, $img) === false) {
        return null;
    }
    return $path;
}

$modeDraw = isset($_POST['mode']) && $_POST['mode'] === 'draw';
$pageDrawings = [];
if ($modeDraw) {
    foreach ($_POST as $key => $value) {
        if (preg_match('/^page_(\d+)$/', $key, $m) && !empty($value)) {
            $pageNum = (int) $m[1];
            $pageDrawings[$pageNum] = $value;
        }
    }
}

if (empty($pageDrawings)) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('กรุณาลงนามบนเอกสารก่อนส่ง'));
    exit;
}

if (!extension_loaded('gd')) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('ระบบต้องการ PHP GD สำหรับ PNG โปร่งใส กรุณาเปิด extension=gd ใน php.ini'));
    exit;
}

require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';
use setasign\Fpdi\Tcpdf\Fpdi;

$pdf = new Fpdi('P', 'pt');
$pdf->SetAutoPageBreak(false);
$pageCount = $pdf->setSourceFile($templatePath);

for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
    $tpl = $pdf->importPage($pageNum);
    $size = $pdf->getTemplateSize($tpl);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

    $pageWidth = $size['width'];
    $pageHeight = $size['height'];

    if (isset($pageDrawings[$pageNum])) {
        $drawPath = saveDrawAsPng($pageDrawings[$pageNum], $tmpDir);
        if ($drawPath && file_exists($drawPath)) {
            $pdf->Image($drawPath, 0, 0, $pageWidth, $pageHeight);
            @unlink($drawPath);
        }
    }
}

$upload_dir = __DIR__ . '/../uploads/supervision_docs/original/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$filename = 'SUP_' . $student_id . '_' . time() . '.pdf';
$savePath = $upload_dir . $filename;

$pdf->Output($savePath, 'F');

if (!file_exists($savePath)) {
    header('Location: ' . $redirectBase . '?error=' . urlencode('บันทึก PDF ไม่สำเร็จ'));
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO supervision_files
    (teacher_id, student_id, file_path, status, uploaded_at)
    VALUES (?, ?, ?, 0, NOW())
");
if (!$stmt) {
    @unlink($savePath);
    header('Location: ' . $redirectBase . '?error=' . urlencode('เกิดข้อผิดพลาดของระบบ'));
    exit;
}
$stmt->bind_param("iis", $teacher_id, $student_id, $filename);
if (!$stmt->execute()) {
    @unlink($savePath);
    $stmt->close();
    header('Location: ' . $redirectBase . '?error=' . urlencode('บันทึกข้อมูลไม่สำเร็จ'));
    exit;
}
$stmt->close();

header('Location: ' . $redirectBase . '?success=1');
exit;
