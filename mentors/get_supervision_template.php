<?php
/**
 * Serve PDF template from uploads/supervision_docs/templates/ or templates/{set}/
 * GET: f=filename [&set=ชุดโฟลเดอร์]
 * Requires mentor/teacher/staff/admin login.
 */
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['mentor', 'teacher', 'staff', 'admin']);

$base_path = __DIR__ . '/../template';
$base_dir = realpath($base_path);
if (!$base_dir || !is_dir($base_dir)) {
    http_response_code(404);
    exit('Template folder not found');
}

$file = isset($_GET['f']) ? trim($_GET['f']) : '';
// Map old names to new ones
$filename_map = [
    'ใบปะหน้าปกติตย.pdf' => 'pgga.pdf',
    'ใบนิเทศ.pdf' => 'pgfa.pdf',
    'ใบปะหน้าทวิภาคี.pdf' => 'twipa.pdf',
    'แบบนิเทศทวิ 1-68 ใหม่.pdf' => 'twifa.pdf',
];
if (isset($filename_map[$file])) {
    $file = $filename_map[$file];
} else {
    $file = basename($file);
}

if ($file === '' || preg_match('/\\.\\.|\\/|\\\\/', $file)) {
    http_response_code(400);
    exit('Invalid file');
}
if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(400);
    exit('Only PDF allowed');
}

$path = $base_dir . DIRECTORY_SEPARATOR . $file;
$path = realpath($path);
if (!$path || !is_file($path) || dirname($path) !== $base_dir) {
    http_response_code(404);
    exit('File not found');
}

if (isset($_GET['student_id']) && (int)$_GET['student_id'] > 0) {
    $student_id = (int)$_GET['student_id'];
    require_once __DIR__ . '/../libs/TCPDF-main/tcpdf.php';
    require_once __DIR__ . '/../libs/FPDI-master/src/autoload.php';

    $stmt = $conn->prepare("SELECT usr.fullname, COALESCE(comp.name, usr.company_name) as company_name 
                            FROM users usr 
                            LEFT JOIN companies comp ON usr.company_id = comp.id 
                            WHERE usr.id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stu = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($stu) {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        try {
            $pageCount = $pdf->setSourceFile($path);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'compression technique') !== false) {
                $pdftkPath = 'C:\\Program Files (x86)\\PDFtk Server\\bin\\pdftk.exe';
                if (file_exists($pdftkPath)) {
                    $fixedTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixed_' . time() . '_' . basename($path);
                    $cmd = escapeshellarg($pdftkPath) . ' ' . escapeshellarg($path) . ' output ' . escapeshellarg($fixedTmp) . ' uncompress';
                    exec($cmd, $out, $ret);
                    if ($ret === 0 && file_exists($fixedTmp) && filesize($fixedTmp) > 0) {
                        $path = $fixedTmp;
                        $pageCount = $pdf->setSourceFile($path);
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }
        
        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            $tpl = $pdf->importPage($pageNum);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
            
            if ($pageNum === 1) {
                // FreeSerif supports Thai characters
                $pdf->SetFont('freeserif', '', 14);
                $pdf->SetTextColor(0, 0, 200); // Slight blue to distinguish auto-fill
                
                $filenameLower = mb_strtolower(basename($path), 'UTF-8');
                if (strpos($filenameLower, 'นิเทศ') !== false || strpos($filenameLower, 'fa.pdf') !== false) {
                    // Coordinates for ใบนิเทศ / pgfa / twifa
                    $pdf->SetXY(200, 160); 
                    $pdf->Write(0, $stu['company_name'] ?? '-');
                    
                    // Student name in table
                    $pdf->SetXY(140, 310);
                    $pdf->Write(0, $stu['fullname'] ?? '-');
                } else if (strpos($filenameLower, 'ปะหน้า') !== false || strpos($filenameLower, 'pa.pdf') !== false || strpos($filenameLower, 'ga.pdf') !== false) {
                    // Coordinates for ใบปะหน้า / pgga / twipa
                    $pdf->SetXY(170, 680); 
                    $pdf->Write(0, $stu['company_name'] ?? '-');
                    
                    // Student name list
                    $pdf->SetXY(170, 395);
                    $pdf->Write(0, $stu['fullname'] ?? '-');
                } else {
                    // Generic default (Top area)
                    $pdf->SetXY(50, 50);
                    $pdf->Write(0, "ชื่อ: " . ($stu['fullname'] ?? '-') . " | สถานประกอบการ: " . ($stu['company_name'] ?? '-'));
                }
            }
        }
        
        $pdf->Output(basename($path), 'I');
        exit;
    }
}

// Fallback to regular file stream if no student_id or student not found
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
