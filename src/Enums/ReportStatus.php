<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * [H-5] Constants for Daily Report Status (ENUM in DB)
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'รอตรวจ',
            self::Approved => 'อนุมัติแล้ว',
            self::Rejected => 'รอนักเรียนส่งใหม่',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Pending  => '<span class="badge bg-warning">รอตรวจ</span>',
            self::Approved => '<span class="badge bg-success">อนุมัติแล้ว</span>',
            self::Rejected => '<span class="badge bg-danger">รอนักเรียนส่งใหม่</span>',
        };
    }
}
