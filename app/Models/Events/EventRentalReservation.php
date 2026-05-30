<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rental/inventory reservation for an EventOccurrence.
 *
 * @property int    $id
 * @property int    $event_occurrence_id
 * @property int    $article_id
 * @property float  $quantity
 * @property string $reservation_type
 * @property string $reservation_status
 * @property string $date_from
 * @property string $date_to
 * @property int|null $source_offer_item_id
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventRentalReservation extends Model
{
    protected $table = 'event_rental_reservations';

    protected $fillable = [
        'event_occurrence_id',
        'article_id',
        'quantity',
        'reservation_type',
        'reservation_status',
        'date_from',
        'date_to',
        'source_offer_item_id',
        'notes',
    ];

    protected $casts = [
        'quantity'  => 'decimal:3',
        'date_from' => 'date',
        'date_to'   => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}
