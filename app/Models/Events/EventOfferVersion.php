<?php

declare(strict_types=1);

namespace App\Models\Events;

use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned offer attached to an EventOccurrence.
 *
 * Monetary fields use milli-cents (1 EUR = 1_000_000).
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $event_occurrence_id
 * @property string|null $offer_number       ST-A-2026-000001 (null for imported)
 * @property string|null $external_offer_number
 * @property int         $version_number
 * @property string      $source_system
 * @property string      $status
 * @property string|null $valid_until
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $accepted_at
 * @property \Carbon\Carbon|null $rejected_at
 * @property int|null    $converted_order_id
 * @property int|null    $net_total_milli
 * @property int|null    $gross_total_milli
 * @property int|null    $deposit_total_milli
 * @property int|null    $rental_total_milli
 * @property int|null    $delivery_total_milli
 * @property string|null $pdf_file_path
 * @property string|null $external_pdf_reference
 * @property string|null $email_message_id
 * @property string|null $conversion_status
 * @property array|null  $conversion_error
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventOfferVersion extends Model
{
    protected $table = 'event_offer_versions';

    protected $fillable = [
        'company_id',
        'event_occurrence_id',
        'offer_number',
        'external_offer_number',
        'version_number',
        'source_system',
        'status',
        'valid_until',
        'created_by_user_id',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'converted_order_id',
        'net_total_milli',
        'gross_total_milli',
        'deposit_total_milli',
        'rental_total_milli',
        'delivery_total_milli',
        'pdf_file_path',
        'external_pdf_reference',
        'email_message_id',
        'conversion_status',
        'conversion_error',
    ];

    protected $casts = [
        'sent_at'          => 'datetime',
        'accepted_at'      => 'datetime',
        'rejected_at'      => 'datetime',
        'valid_until'      => 'date',
        'conversion_error' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventOfferItem::class, 'event_offer_version_id')->orderBy('sort_order');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
