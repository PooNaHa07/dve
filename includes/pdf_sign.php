<?php
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

function signPDF($input, $output, $directorName) {
    $pdf = new FPDI();
    $pageCount = $pdf->setSourceFile($input);

    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        if ($i == $pageCount) {
            $pdf->SetFont('THSarabun', '', 14);
            $pdf->SetXY(120, $size['height'] - 40);
            $pdf->MultiCell(0, 8,
                "ลงนามโดย: $directorName\nวันที่: ".date('d/m/Y H:i')
            );
        }
    }

    $pdf->Output($output, 'F');
}
