<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice for an order. One invoice per order (unique order_id).
 *
 * Lifecycle: draft -> finalized
 * - Draft may be regenerated at any time.
 * - Finalized invoices are locked; invoice_number assigned; PDF generated.
 *
 * @property int              $id
 * @property int              $order_id
 * @property int|null         $company_id           denormalized from order.company_id (WP-16)
 * @property string|null      $invoice_number       e.g. RE-2024-00042
 * @property string           $status               draft|finalized
 * @property int              $total_net_milli
 * @property int              $total_gross_milli
 * @property int              $total_tax_milli
 * @property int              $total_adjustments_milli
 * @property int              $total_deposit_milli
 * @property string|null      $pdf_path
 * @property string|null      $lexoffice_voucher_id   Lexoffice voucher UUID (WP-17)
 * @property \Carbon\Carbon|null $lexoffice_synced_at Last successful lexoffice sync
 * @property string|null      $lexoffice_sync_error   Last sync error message
 * @property \Carbon\Carbon|null $finalized_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read Order                            $order
 * @property-read Collection<int, InvoiceItem>     $items
 * @property-read Collection<int, Payment>         $payments
 */
class Invoice extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    /**
     * WP-18: Prevent modification of immutable fields on finalized invoices.
     *
     * Only lexoffice sync fields and pdf_path may be updated after finalization.
     * The draft→finalized transition itself is safe because getOriginal('status')
     * still equals 'draft' during that single save call.
     */
    protected static function booted(): void
    {
        static::saving(static function (Invoice $invoice): void {
            // New records and draft invoices are always writable
            if (! $invoice->exists) {
                return;
            }
            if ($invoice->getOriginal('status') !== self::STATUS_FINALIZED) {
                return;
            }

            $allowed   = ['lexoffice_voucher_id', 'lexoffice_synced_at', 'lexoffice_sync_error', 'pdf_path'];
            $dirty     = array_keys($invoice->getDirty());
            $forbidden = array_diff($dirty, $allowed);

            if (! empty($forbidden)) {
                throw new \LogicException(
                    'Cannot modify finalized invoice fields: ' . implode(', ', $forbidden)
                );
            }
        });
    }

    protected $fillable = [
        'order_id',
        'company_id',
        'invoice_number',
        'status',
        'total_net_milli',
        'total_gross_milli',
        'total_tax_milli',
        'total_adjustments_milli',
        'total_deposit_milli',
        'pdf_path',
        'lexoffice_voucher_id',
        'lexoffice_synced_at',
        'lexoffice_sync_error',
        'finalized_at',
    ];

    protected $casts = [
        'company_id'               => 'integer',
        'total_net_milli'          => 'integer',
        'total_gross_milli'        => 'integer',
        'total_tax_milli'          => 'integer',
        'total_adjustments_milli'  => 'integer',
        'total_deposit_milli'      => 'integer',
        'lexoffice_synced_at'      => 'datetime',
        'finalized_at'             => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<InvoiceItem> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    /** Total paid so far in milli-cents. */
    public function paidMilli(): int
    {
        return (int) $this->payments->sum('amount_milli');
    }

    /** Outstanding balance in milli-cents. */
    public function balanceMilli(): int
    {
        return $this->total_gross_milli - $this->paidMilli();
    }
}
