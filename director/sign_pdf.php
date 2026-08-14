<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['director', 'admin']);

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : (isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0);
$return_to = isset($_POST['return_to']) && $_POST['return_to'] === 'view_wait_sign' ? 'view_wait_sign' : '';
$redirect_success = $return_to ? 'view_wait_sign.php?success=1' : ('view_students.php?class_id=' . $class_id . '&success=1');
$redirect_error = function($err) use ($return_to, $class_id) { return $return_to ? 'view_wait_sign.php?error=' . $err : ('view_students.php?class_id=' . $class_id . '&error=' . $err); };

/* ถ้าเป็น GET ให้ไปหน้าฟอร์มลงนาม */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        header('Location: sign_supervision_form.php?id=' . (int)$_GET['id'] . '&class_id=' . $class_id);
        exit;
    }
    header('Location: view_students.php?error=1');
    exit;
}

/* POST: รับ id และ signature_data หรือ sign_on_document + page_1, page_2 */
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Location: ' . $redirect_error('id'));
    exit;
}
$id = (int)$_POST['id'];

/* สร้างคอลัมน์ลายเซ็นเจ้าหน้าที่ถ้ายังไม่มี */
$chk = $conn->query("SHOW COLUMNS FROM supervision_files LIKE 'staff_signed_file'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE supervision_files
        ADD COLUMN staff_signed_file VARCHAR(255) DEFAULT NULL AFTER file_path,
        ADD COLUMN staff_signed_at DATETIME DEFAULT NULL AFTER staff_signed_file,
        ADD COLUMN staff_signed_by INT(11) DEFAULT NULL AFTER staff_signed_at");
}

$stmt = $conn->prepare("SELECT file_path, staff_signed_file FROM supervision_files WHERE id = ? AND status = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: ' . $redirect_error('doc'));
    exit;
}
$data = $res->fetch_assoc();
$file_path = $data['file_path'];
$staff_signed = isset($data['staff_signed_file']) ? trim((string)$data['staff_signed_file']) : '';
$outputDir = __DIR__ . '/../uploads/supervision_docs/signed/';
$sigDir = __DIR__ . '/../uploads/supervision_docs/signatures/';
$tmpDir = __DIR__ . '/../uploads/temp/';

if ($staff_signed !== '' && is_file($outputDir . $staff_signed)) {
    $inputFile = $outputDir . $staff_signed;
    $ext = 'pdf';
} else {
    $inputFile = $file_path ? (__DIR__ . '/../uploads/supervision_docs/original/' . $file_path) : '';
    $ext = $file_path ? strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) : '';
}

if ($inputFile !== '' && !file_exists($inputFile)) {
    header('Location: ' . $redirect_error('file'));
    exit;
}
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
if (!is_dir($sigDir)) mkdir($sigDir, 0777, true);
if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
$signPath = null;
$outputFile = '';

/* โหมดเซ็นบนเอกสาร: รวมไฟล์ + ลายเซ็นเป็น PDF เดียว */
$sign_on_document = !empty($_POST['sign_on_document']);
$pageDrawings = [];
if ($sign_on_document) {
    foreach ($_POST as $key => $value) {
        if (preg_match('/^page_(\d+)$/', $key, $m) && !empty($value)) {
            $pageDrawings[(int)$m[1]] = $value;
        }
    }
}

if ($sign_on_document && !empty($pageDrawings) && $file_path && extension_loaded('gd')) {
    require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
    require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';

    function _dirSaveDrawAsPng($base64Data, $tmpDir) {
        if (empty($base64Data) || !preg_match('/^data:image\/png;base64,/', $base64Data)) return null;
        $img = base64_decode(preg_replace('#^data:image/png;base64,#i', '', $base64Data));
        if ($img === false) return null;
        $path = $tmpDir . 'draw_' . uniqid() . '.png';
        return file_put_contents($path, $img) !== false ? $path : null;
    }

    $outFilename = 'SIGNED_' . pathinfo($file_path, PATHINFO_FILENAME) . '_' . time() . '.pdf';
    $outPath = $outputDir . $outFilename;
    $ok = false;

    if ($ext === 'pdf') {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt');
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($inputFile);
            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tpl = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];
                if (isset($pageDrawings[$pageNum])) {
                    $drawPath = _dirSaveDrawAsPng($pageDrawings[$pageNum], $tmpDir);
                    if ($drawPath && file_exists($drawPath)) {
                        $pdf->Image($drawPath, 0, 0, $pageWidth, $pageHeight);
                        @unlink($drawPath);
                    }
                }
            }
            $pdf->Output($outPath, 'F');
            $ok = file_exists($outPath);
        } catch (Exception $e) {
            header('Location: ' . $redirect_error('merge'));
            exit;
        }
    } else {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt');
            $pdf->SetAutoPageBreak(false);
            $pw = 595;
            $ph = 842;
            $info = @getimagesize($inputFile);
            if ($info && $info[0] > 0 && $info[1] > 0) {
                $pw = $info[0];
                $ph = $info[1];
                $r = min(595 / $pw, 842 / $ph);
                $pw = (int)($pw * $r);
                $ph = (int)($ph * $r);
            }
            $pdf->AddPage('P', [$pw, $ph]);
            $pdf->Image($inputFile, 0, 0, $pw, $ph);
            if (isset($pageDrawings[1])) {
                $drawPath = _dirSaveDrawAsPng($pageDrawings[1], $tmpDir);
                if ($drawPath && file_exists($drawPath)) {
                    $pdf->Image($drawPath, 0, 0, $pw, $ph);
                    @unlink($drawPath);
                }
            }
            $pdf->Output($outPath, 'F');
            $ok = file_exists($outPath);
        } catch (Exception $e) {
            header('Location: ' . $redirect_error('merge'));
            exit;
        }
    }

    if ($ok) {
        $outputFile = $outFilename;
        $stmt = $conn->prepare("UPDATE supervision_files SET signed_file = ?, director_signed_at = NOW(), signed_by = ?, status = 2 WHERE id = ?");
        $stmt->bind_param("sii", $outputFile, $uid, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . $redirect_success);
        exit;
    }
}

/* บันทึกลายเซ็นจากฟอร์ม (โหมด pad เดิม) */
if (!empty($_POST['signature_data'])) {
    $base64 = $_POST['signature_data'];
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        if ($img !== false) {
            $signFilename = 'director_' . $id . '_' . time() . '.png';
            $signPath = $sigDir . $signFilename;
            file_put_contents($signPath, $img);
        }
    }
}
$signImg = $signPath && file_exists($signPath) ? $signPath : (file_exists(__DIR__ . '/../assets/signature/director.png') ? __DIR__ . '/../assets/signature/director.png' : null);

$outputFile = '';

if (empty($file_path)) {
    /* ครูส่งเฉพาะลายเซ็น — บันทึกเฉพาะลายเซ็นผู้บริหาร */
    if ($signPath) {
        $outputFile = 'director_signed_' . $id . '_' . time() . '.png';
        copy($signPath, $outputDir . $outputFile);
    }
} elseif ($ext === 'pdf') {
    require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
    require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';

    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt');
    $pdf->SetAutoPageBreak(false);
    $pageCount = $pdf->setSourceFile($inputFile);

    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

        if ($i === $pageCount && $signImg) {
            $pdf->Image($signImg, 130, 230, 40);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetXY(130, 255);
            $pdf->Cell(0, 6, '(ผู้บริหาร)', 0, 1);
            $pdf->SetX(130);
            $pdf->Cell(0, 6, 'ลงนามวันที่ ' . date('d/m/Y'), 0, 1);
        }
    }
    $outputFile = 'SIGNED_' . $file_path;
    $pdf->Output($outputDir . $outputFile, 'F');
} else {
    /* ต้นฉบับเป็นรูป - บันทึกไฟล์ลายเซ็นในโฟลเดอร์ signed (ครูดาวน์โหลดได้) */
    if ($signPath) {
        $outputFile = 'director_signed_' . $id . '_' . time() . '.png';
        copy($signPath, $outputDir . $outputFile);
    }
}

$stmt = $conn->prepare("UPDATE supervision_files SET signed_file = ?, director_signed_at = NOW(), signed_by = ?, status = 2 WHERE id = ?");
$stmt->bind_param("sii", $outputFile, $uid, $id);
$stmt->execute();
$stmt->close();

header('Location: ' . $redirect_success);
exit;
