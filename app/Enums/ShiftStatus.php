<?php
namespace App\Enums;
enum ShiftStatus: string {
    case Planned   = 'planned';
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
