<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Procurement\GoodsReceipt;

class GoodsReceiptObserver
{
    public function created(GoodsReceipt $gr): void
    {
        $this->queuePush($gr);
    }

    public function updated(GoodsReceipt $gr): void
    {
        $this->queuePush($gr);
    }

    private function queuePush(GoodsReceipt $gr): void
    {
        if (!config('services.ninox.tables.bestellung')) {
            return;
        }

        DeferredTask::create([
            'type'         => 'ninox.push_goods_receipt',
            'payload_json' => json_encode(['goods_receipt_id' => $gr->id]),
            'status'       => DeferredTask::STATUS_PENDING,
            'attempts'     => 0,
            'max_attempts' => 3,
        ]);
    }
}
