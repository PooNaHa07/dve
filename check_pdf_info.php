<?php
require_once __DIR__ . '/libs/TCPDF-main/tcpdf.php';
require_once __DIR__ . '/libs/FPDI-master/src/autoload.php';
use setasign\Fpdi\Tcpdf\Fpdi;

$files = [
    'template/pgfa.pdf',
    'template/pgga.pdf',
    'template/twifa.pdf',
    'template/twipa.pdf'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "$file not found\n";
        continue;
    }
    $pdf = new Fpdi();
    $pageCount = $pdf->setSourceFile($file);
    echo "=== File: $file (Pages: $pageCount) ===\n";
    $tpl = $pdf->importPage(1);
    $size = $pdf->getTemplateSize($tpl);
    echo "Size: {$size['width']} x {$size['height']}\n";
    
    // Let's read some raw text to find landmarks if possible
    $content = file_get_contents($file);
    // Find occurrences of common landmarks
    $landmarks = ['ทวิภาคี', 'ปกติ', 'ปะหน้า', 'นิเทศ', 'สถานประกอบการ', 'วิทยาลัย'];
    foreach ($landmarks as $lm) {
        // Convert landmark to hex or search direct if uncompressed
        if (stripos($content, $lm) !== false) {
            echo "Direct match for: $lm\n";
        }
    }
}
