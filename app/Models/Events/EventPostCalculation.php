<?php

declare(strict_types=1);

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Post-event calculation (Nachkalkulation) for an EventOccurrence.
 * 1:1 with EventOccurrence.
 *
 * Monetary fields use milli-cents (1 EUR = 1_000_000).
 *
 * @property int         $id
 * @property int         $event_occurrence_id
 * @property int|null    $actual_guests
 * @property int|null    $planned_guests
 * @property int|null    $planned_gross_total_milli
 * @property int|null    $actual_gross_total_milli
 * @property int|null    $revenue_per_guest_milli
 * @property int|null    $delivered_value_milli
 * @property int|null    $returned_full_goods_value_milli
 * @property int|null    $deposit_difference_milli
 * @property string|null $empty_goods_notes
 * @property bool|null   $third_party_drinks_present
 * @property bool|null   $exclusive_supplier
 * @property array|null  $self_supplied_categories
 * @property string|null $special_events_notes
 * @property string|null $deviation_from_previous_year_notes
 * @property string|null $recommendation_next_year
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventPostCalculation extends Model
{
    protected $table = 'event_post_calculations';

    protected $fillable = [
        'event_occurrence_id',
        'actual_guests',
        'planned_guests',
        'planned_gross_total_milli',
        'actual_gross_total_milli',
        'revenue_per_guest_milli',
        'delivered_value_milli',
        'returned_full_goods_value_milli',
        'deposit_difference_milli',
        'empty_goods_notes',
        'third_party_drinks_present',
        'exclusive_supplier',
        'self_supplied_categories',
        'special_events_notes',
        'deviation_from_previous_year_notes',
        'recommendation_next_year',
        'created_by_user_id',
        'completed_at',
    ];

    protected $casts = [
        'third_party_drinks_present'  => 'boolean',
        'exclusive_supplier'          => 'boolean',
        'self_supplied_categories'    => 'array',
        'completed_at'                => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
