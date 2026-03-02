<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on an invoice.
 *
 * @property int         $id
 * @property int         $invoice_id
 * @property int|null    $order_item_id
 * @property int|null    $adjustment_id
 * @property string      $line_type          product|adjustment|deposit|shipping
 * @property string      $description
 * @property float       $qty
 * @property int         $unit_price_net_milli
 * @property int         $unit_price_gross_milli
 * @property int         $tax_rate_basis_points
 * @property int         $line_total_net_milli
 * @property int         $line_total_gross_milli
 * @property int|null    $cost_milli             unit purchase price snapshot (WP-16)
 */
class InvoiceItem extends Model
{
    public const TYPE_PRODUCT    = 'product';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_DEPOSIT    = 'deposit';
    public const TYPE_SHIPPING   = 'shipping';

    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'adjustment_id',
        'line_type',
        'description',
        'qty',
        'unit_price_net_milli',
        'unit_price_gross_milli',
        'tax_rate_basis_points',
        'line_total_net_milli',
        'line_total_gross_milli',
        'cost_milli',
    ];

    protected $casts = [
        'qty'                     => 'float',
        'unit_price_net_milli'    => 'integer',
        'unit_price_gross_milli'  => 'integer',
        'tax_rate_basis_points'   => 'integer',
        'line_total_net_milli'    => 'integer',
        'line_total_gross_milli'  => 'integer',
        'cost_milli'              => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
