<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Pricing\Customer;

class CustomerObserver
{
    public function saved(Customer $customer): void
    {
        // Skip if this save was triggered by the Ninox pull (would cause a loop)
        if (request()->attributes->get('ninox_pull_active')) {
            return;
        }

        // Never push NINOX-stubs back — they have no real customer number yet
        if (str_starts_with($customer->customer_number ?? '', 'NINOX-')) {
            return;
        }

        DeferredTask::create([
            'type'         => 'ninox.push_customer',
            'payload_json' => json_encode(['customer_id' => $customer->id]),
            'status'       => DeferredTask::STATUS_PENDING,
        ]);
    }
}
