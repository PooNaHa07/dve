<?php
/**
 * บันทึกลายเซ็นเจ้าหน้าที่บนเอกสาร (ระบบเดียวกับผู้บริหาร — ลงนามบน PDF)
 * บันทึก staff_signed_file, staff_signed_at, staff_signed_by ไม่เปลี่ยน status
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['staff', 'admin']);

/* สร้างคอลัมน์ลายเซ็นเจ้าหน้าที่ถ้ายังไม่มี (รันครั้งเดียว) */
$chk = $conn->query("SHOW COLUMNS FROM supervision_files LIKE 'staff_signed_file'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE supervision_files
        ADD COLUMN staff_signed_file VARCHAR(255) DEFAULT NULL AFTER file_path,
        ADD COLUMN staff_signed_at DATETIME DEFAULT NULL AFTER staff_signed_file,
        ADD COLUMN staff_signed_by INT(11) DEFAULT NULL AFTER staff_signed_at");
}

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$redirect_success = 'manage_supervision.php?success=1&signed=1';
$redirect_error = function($err) { return 'manage_supervision.php?error=' . $err; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        header('Location: sign_supervision_form.php?id=' . (int)$_GET['id']);
        exit;
    }
    header('Location: manage_supervision.php?error=1');
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Location: ' . $redirect_error('id'));
    exit;
}
$id = (int)$_POST['id'];

$stmt = $conn->prepare("SELECT file_path FROM supervision_files WHERE id = ? AND status = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: ' . $redirect_error('doc'));
    exit;
}
$data = $res->fetch_assoc();
$file_path = $data['file_path'];

$inputFile = $file_path ? (__DIR__ . '/../uploads/supervision_docs/original/' . $file_path) : '';
$outputDir = __DIR__ . '/../uploads/supervision_docs/signed/';
$tmpDir = __DIR__ . '/../uploads/temp/';

if ($file_path && !file_exists($inputFile)) {
    header('Location: ' . $redirect_error('file'));
    exit;
}
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

$ext = $file_path ? strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) : '';
$outputFile = '';

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

    function _staffSaveDrawAsPng($base64Data, $tmpDir) {
        if (empty($base64Data) || !preg_match('/^data:image\/png;base64,/', $base64Data)) return null;
        $img = base64_decode(preg_replace('#^data:image/png;base64,#i', '', $base64Data));
        if ($img === false) return null;
        $path = $tmpDir . 'draw_' . uniqid() . '.png';
        return file_put_contents($path, $img) !== false ? $path : null;
    }

    $outFilename = 'STAFF_SIGNED_' . pathinfo($file_path, PATHINFO_FILENAME) . '_' . time() . '.pdf';
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
                    $drawPath = _staffSaveDrawAsPng($pageDrawings[$pageNum], $tmpDir);
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
                $drawPath = _staffSaveDrawAsPng($pageDrawings[1], $tmpDir);
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
        $stmt = $conn->prepare("UPDATE supervision_files SET staff_signed_file = ?, staff_signed_at = NOW(), staff_signed_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $outputFile, $uid, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . $redirect_success);
        exit;
    }
}

header('Location: ' . $redirect_error('save'));
exit;
