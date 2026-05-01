<?php
namespace App\Enums;
enum ComplianceStatus: string {
    case Ok      = 'ok';
    case Warning = 'warning';
    case Breach  = 'breach';
}
