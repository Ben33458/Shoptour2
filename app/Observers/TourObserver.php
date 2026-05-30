<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\DeferredTask;
use App\Models\Delivery\Tour;

class TourObserver
{
    public function updated(Tour $tour): void
    {
        if ($tour->wasChanged('status') && $tour->ninox_id) {
            DeferredTask::create([
                'type'         => 'ninox.sync_tour',
                'payload_json' => json_encode(['tour_id' => $tour->id]),
                'status'       => DeferredTask::STATUS_PENDING,
                'max_attempts' => 3,
            ]);
        }
    }
}
