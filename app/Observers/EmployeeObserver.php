<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Employee\Employee;

class EmployeeObserver
{
    public function saved(Employee $employee): void
    {
        if (request()->attributes->get('ninox_pull_active')) {
            return;
        }

        DeferredTask::create([
            'type'         => 'ninox.push_employee',
            'payload_json' => json_encode(['employee_id' => $employee->id]),
            'status'       => DeferredTask::STATUS_PENDING,
        ]);
    }
}
