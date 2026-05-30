<?php

declare(strict_types=1);

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line item within an EventOfferVersion.
 *
 * Monetary fields use milli-cents (1 EUR = 1_000_000).
 *
 * @property int         $id
 * @property int         $event_offer_version_id
 * @property int|null    $article_id
 * @property string|null $external_article_number
 * @property string      $name
 * @property string|null $description
 * @property float       $quantity
 * @property string      $unit
 * @property int|null    $unit_price_net_milli
 * @property int|null    $unit_price_gross_milli
 * @property float|null  $tax_rate
 * @property int|null    $deposit_amount_milli
 * @property int|null    $line_total_net_milli
 * @property int|null    $line_total_gross_milli
 * @property string      $item_type
 * @property bool        $is_optional
 * @property bool        $is_alternative
 * @property string|null $alternative_group
 * @property bool        $is_free_text
 * @property bool        $supplied_by_us
 * @property string|null $third_party_supply_note
 * @property int         $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventOfferItem extends Model
{
    protected $table = 'event_offer_items';

    protected $fillable = [
        'event_offer_version_id',
        'article_id',
        'external_article_number',
        'name',
        'description',
        'quantity',
        'unit',
        'unit_price_net_milli',
        'unit_price_gross_milli',
        'tax_rate',
        'deposit_amount_milli',
        'line_total_net_milli',
        'line_total_gross_milli',
        'item_type',
        'is_optional',
        'is_alternative',
        'alternative_group',
        'is_free_text',
        'supplied_by_us',
        'third_party_supply_note',
        'sort_order',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'tax_rate'      => 'decimal:2',
        'is_optional'   => 'boolean',
        'is_alternative'=> 'boolean',
        'is_free_text'  => 'boolean',
        'supplied_by_us'=> 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function offerVersion(): BelongsTo
    {
        return $this->belongsTo(EventOfferVersion::class, 'event_offer_version_id');
    }
}
