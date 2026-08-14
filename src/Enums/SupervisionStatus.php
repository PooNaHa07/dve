<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * [L-1] Constants for Supervision Document Status
 */
enum SupervisionStatus: int
{
    case PendingStaff = 0;
    case PendingDirector = 1;
    case Signed = 2;

    public function label(): string
    {
        return match($this) {
            self::PendingStaff    => 'รอเจ้าหน้าที่ตรวจสอบ',
            self::PendingDirector => 'รอผู้บริหารลงนาม',
            self::Signed          => 'ลงนามเสร็จสิ้น',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PendingStaff    => 'warning',
            self::PendingDirector => 'primary',
            self::Signed          => 'success',
        };
    }
}
