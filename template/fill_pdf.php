<?php
/**
 * โหลดแบบฟอร์ม PDF จากโฟลเดอร์ template แล้ววางลายเซ็นตามตำแหน่งที่จับจากข้อความ "ลงชื่อ" และ "ลงนาม" ใน PDF
 * รองรับหลายจุด (สูงสุด 4 จุด) — ตำแหน่งมาจาก index.php ที่ใช้ PDF.js หาคำใน PDF
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

$allowed_templates = ['pgfa.pdf', 'pgga.pdf', 'twifa.pdf', 'twipa.pdf'];
$template = isset($_POST['template']) ? basename($_POST['template']) : '';
if (!in_array($template, $allowed_templates)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<script>alert("ไม่พบแบบฟอร์มที่เลือก"); window.close();</script>';
    exit;
}

$templatePath = __DIR__ . '/' . $template;
if (!file_exists($templatePath)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<script>alert("ไม่พบไฟล์แบบฟอร์ม"); window.close();</script>';
    exit;
}

$tmpDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

/**
 * แปลง base64 รูปลายเซ็นเป็นไฟล์ JPEG ใน temp (เลี่ยงปัญหา alpha/สี่เหลี่ยมดำใน TCPDF)
 */
function saveSignatureAsJpeg($base64Data, $tmpDir) {
    if (empty($base64Data) || !preg_match('/^data:image\/(\w+);base64,/', $base64Data, $m)) {
        return null;
    }
    $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Data));
    if ($img === false) {
        return null;
    }
    $ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
    $path = $tmpDir . 'sig_' . uniqid() . '.' . $ext;
    file_put_contents($path, $img);
    if ($ext === 'png' && extension_loaded('gd')) {
        $im = @imagecreatefromstring($img);
        if ($im !== false) {
            $w = imagesx($im);
            $h = imagesy($im);
            $jpegPath = $tmpDir . 'sig_' . uniqid() . '.jpg';
            $bg = imagecreatetruecolor($w, $h);
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagealphablending($bg, true);
            imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
            imagejpeg($bg, $jpegPath, 92);
            imagedestroy($im);
            imagedestroy($bg);
            @unlink($path);
            return $jpegPath;
        }
    }
    if ($ext === 'png' && !extension_loaded('gd')) {
        @unlink($path);
        return null;
    }
    return $path;
}

/**
 * บันทึกรูปวาด PNG (alpha) เป็นไฟล์ชั่วคราว — ใช้ overlay เต็มหน้าแบบโปร่งใส
 * ต้องมี PHP GD extension เพื่อให้ TCPDF รองรับ PNG ที่มี alpha channel
 */
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

// โหมด "เซ็นตรงบนเอกสาร" = ส่ง page_1, page_2, ... (รูปวาดเต็มหน้า)
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

// อ่านตำแหน่งจากฟอร์ม (โหมดจุดลงนาม: positions + signature_0, ...)
$positions = [];
if (!empty($_POST['positions'])) {
    $decoded = json_decode($_POST['positions'], true);
    if (is_array($decoded)) {
        $positions = $decoded;
    }
}

// รวบรวมลายเซ็นแต่ละจุด (signature_0, signature_1, ...) เป็นไฟล์ JPEG
$signPaths = [];
$maxIndex = 0;
foreach ($_POST as $key => $value) {
    if (preg_match('/^signature_(\d+)$/', $key, $m) && !empty($value)) {
        $idx = (int) $m[1];
        $path = saveSignatureAsJpeg($value, $tmpDir);
        if ($path !== null) {
            $signPaths[$idx] = $path;
            if ($idx > $maxIndex) {
                $maxIndex = $idx;
            }
        }
    }
}

// ถ้าไม่มี positions แต่ส่ง signature_data เดิม (ความเข้ากันได้กับของเก่า)
if (empty($positions) && !empty($_POST['signature_data'])) {
    $path = saveSignatureAsJpeg($_POST['signature_data'], $tmpDir);
    if ($path !== null) {
        $signPaths[0] = $path;
        $positions = [['page' => 999, 'x' => 100, 'y' => 100, 'pageHeight' => 842, 'label' => 'ลายเซ็น']];
    }
}

// ถ้ามี positions แต่ไม่มีลายเซ็นครบ ใช้ลายเซ็นตัวแรกซ้ำที่จุดที่ขาด (หรือข้าม)
$imgW = 40;
$imgH = 22;

require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';
use setasign\Fpdi\Tcpdf\Fpdi;

// ใช้หน่วย pt (points) ให้ตรงกับขนาดจาก PDF ต้นฉบับ — ถ้าใช้ mm ต้นฉบับจะไม่เต็มหน้า/เป็นเอกสารใหม่
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
    $pageIndex = $pageNum - 1;

    // โหมดเซ็นตรงบนเอกสาร: วาง PNG (alpha) เต็มหน้า — ลายเซ็นโปร่งใส ไม่มีพื้นหลังสีขาว (ต้องมี GD)
    if ($modeDraw && isset($pageDrawings[$pageNum])) {
        if (!extension_loaded('gd')) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<script>alert("ระบบต้องการ PHP GD extension สำหรับ PNG โปร่งใส กรุณาเปิด extension=gd ใน php.ini"); window.close();</script>';
            exit;
        }
        $drawPath = saveDrawAsPng($pageDrawings[$pageNum], $tmpDir);
        if ($drawPath && file_exists($drawPath)) {
            $pdf->Image($drawPath, 0, 0, $pageWidth, $pageHeight);
            @unlink($drawPath);
        }
    }

    // โหมดจุดลงนาม: วางลายเซ็นตาม positions
    foreach ($positions as $idx => $pos) {
        $posPage = isset($pos['page']) ? (int) $pos['page'] : 0;
        if ($posPage === 999) {
            $posPage = $pageCount - 1; // fallback: หน้าสุดท้าย
        }
        if ($posPage !== $pageIndex) {
            continue;
        }
        $sigPath = isset($signPaths[$idx]) ? $signPaths[$idx] : (isset($signPaths[0]) ? $signPaths[0] : null);
        if ($sigPath === null || !file_exists($sigPath)) {
            continue;
        }
        $x = isset($pos['x']) ? (float) $pos['x'] : 100;
        $y = isset($pos['y']) ? (float) $pos['y'] : 100;
        $ph = isset($pos['pageHeight']) ? (float) $pos['pageHeight'] : $pageHeight;
        // PDF coordinate: origin bottom-left. TCPDF: origin top-left.
        // ตำแหน่งจาก PDF.js คือ baseline ของข้อความ "ลงชื่อ"/"ลงนาม" — วางรูปใต้ข้อความเล็กน้อย
        $yTop = $ph - $y + 6; // 6pt ใต้ baseline
        if ($yTop < 0) {
            $yTop = 0;
        }
        if ($yTop + $imgH > $pageHeight) {
            $yTop = $pageHeight - $imgH;
        }
        if ($x + $imgW > $pageWidth) {
            $x = $pageWidth - $imgW;
        }
        if ($x < 0) {
            $x = 0;
        }
        $pdf->Image($sigPath, $x, $yTop, $imgW, $imgH);
    }
}

foreach ($signPaths as $p) {
    if (file_exists($p)) {
        @unlink($p);
    }
}

$outName = 'signed_' . pathinfo($template, PATHINFO_FILENAME) . '_' . date('YmdHis') . '.pdf';
$pdf->Output($outName, 'I');
