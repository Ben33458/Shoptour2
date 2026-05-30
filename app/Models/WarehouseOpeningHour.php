<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $warehouse_id
 * @property int    $day_of_week   0=Sonntag … 6=Samstag
 * @property string $open_from     HH:MM
 * @property string $open_to       HH:MM
 */
class WarehouseOpeningHour extends Model
{
    protected $fillable = [
        'warehouse_id',
        'day_of_week',
        'open_from',
        'open_to',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
