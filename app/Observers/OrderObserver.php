<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Orders\Order;

class OrderObserver
{
    public function saved(Order $order): void
    {
        if (request()->attributes->get('ninox_pull_active')) {
            return;
        }

        $alreadyPending = DeferredTask::where('type', 'ninox.push_order')
            ->where('status', DeferredTask::STATUS_PENDING)
            ->whereJsonContains('payload_json', ['order_id' => $order->id])
            ->exists();

        if ($alreadyPending) {
            return;
        }

        DeferredTask::create([
            'type'         => 'ninox.push_order',
            'payload_json' => json_encode(['order_id' => $order->id]),
            'status'       => DeferredTask::STATUS_PENDING,
        ]);
    }
}
