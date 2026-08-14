<?php
require_once __DIR__ . '/../includes/functions.php';
\App\Helpers\Auth::guard(['staff', 'admin']);

// [Security] ตรวจสอบ CSRF Token
if (!\App\Helpers\CsrfHelper::validate($_POST['csrf_token'] ?? null)) {
    \App\Helpers\Flash::error("Security Token Invalid (CSRF)");
    header("Location: manage_supervision.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status_val = isset($_POST['status']) ? (int)$_POST['status'] : -1;
$status = \App\Enums\SupervisionStatus::tryFrom($status_val);

if ($id && $status !== null) {
    if ($status === \App\Enums\SupervisionStatus::PendingDirector) {
        $row = \App\Helpers\Database::fetchOne(
            "SELECT staff_signed_file FROM supervision_files WHERE id = ? AND status = ?",
            [$id, \App\Enums\SupervisionStatus::PendingStaff->value]
        );
        
        if (!$row || empty($row['staff_signed_file'])) {
            \App\Helpers\Flash::error("กรุณาลงนามบนเอกสารก่อนเสนอผู้บริหาร");
            header("Location: manage_supervision.php");
            exit;
        }
    }
    
    $affected = \App\Helpers\Database::query(
        "UPDATE supervision_files SET status = ? WHERE id = ?",
        [$status->value, $id]
    );

    if ($affected) {
        \App\Helpers\Flash::success("ดำเนินการเรียบร้อยแล้ว: " . $status->label());
    } else {
        \App\Helpers\Flash::error("ไม่สามารถบันทึกข้อมูลได้");
    }
    header("Location: manage_supervision.php");
    exit;
} else {
    \App\Helpers\Flash::error("ข้อมูลไม่ถูกต้อง กรุณาลองใหม่");
    header("Location: manage_supervision.php");
    exit;
}