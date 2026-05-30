<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Catalog\Product;

class ProductObserver
{
    public function saved(Product $product): void
    {
        if (request()->attributes->get('ninox_pull_active')) {
            return;
        }

        DeferredTask::create([
            'type'         => 'ninox.push_product',
            'payload_json' => json_encode(['product_id' => $product->id]),
            'status'       => DeferredTask::STATUS_PENDING,
        ]);
    }
}
