<?php
namespace App\Services\Employee;

use App\Models\Employee\SystemLog;
use Illuminate\Http\Request;

class SystemLogService
{
    public function log(
        string $action,
        ?int $userId     = null,
        ?int $employeeId = null,
        ?string $entityType = null,
        ?int $entityId    = null,
        array $payload    = [],
        ?string $ip       = null,
    ): void {
        SystemLog::create([
            'user_id'     => $userId,
            'employee_id' => $employeeId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'payload'     => $payload ?: null,
            'ip_address'  => $ip,
            'logged_at'   => now(),
        ]);
    }
}
