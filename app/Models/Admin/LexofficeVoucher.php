<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexofficeVoucher extends Model
{
    // ── Voucher types ────────────────────────────────────────────────────────
    public const TYPE_SALES_INVOICE       = 'salesinvoice';
    public const TYPE_CREDIT_NOTE         = 'creditnote';
    public const TYPE_PURCHASE_INVOICE    = 'purchaseinvoice';
    public const TYPE_PURCHASE_CREDITNOTE = 'purchasecreditnote';

    // ── Voucher statuses ─────────────────────────────────────────────────────
    public const STATUS_OPEN    = 'open';
    public const STATUS_PAID    = 'paid';
    public const STATUS_PAIDOFF = 'paidoff';
    public const STATUS_VOIDED  = 'voided';
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_OVERDUE = 'overdue';

    protected $table = 'lexoffice_vouchers';

    protected $fillable = [
        'company_id',
        'lexoffice_voucher_id',
        'voucher_type',
        'voucher_number',
        'voucher_date',
        'due_date',
        'voucher_status',
        'total_gross_amount',
        'open_amount',
        'currency',
        'lexoffice_contact_id',
        'contact_name',
        'customer_id',
        'supplier_id',
        'local_invoice_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'due_date'     => 'date',
            'synced_at'    => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function localInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'local_invoice_id');
    }

    // ── Helper methods ───────────────────────────────────────────────────────

    public function isIncome(): bool
    {
        return in_array($this->voucher_type, [self::TYPE_SALES_INVOICE, self::TYPE_CREDIT_NOTE], true);
    }

    public function isExpense(): bool
    {
        return in_array($this->voucher_type, [self::TYPE_PURCHASE_INVOICE, self::TYPE_PURCHASE_CREDITNOTE], true);
    }

    public function isPaid(): bool
    {
        return in_array($this->voucher_status, [self::STATUS_PAID, self::STATUS_PAIDOFF], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->voucher_status, [self::STATUS_OPEN, self::STATUS_OVERDUE], true);
    }

    public function formattedTotal(): string
    {
        return $this->formatMilliCent((int) $this->total_gross_amount);
    }

    public function formattedOpen(): string
    {
        return $this->formatMilliCent((int) $this->open_amount);
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function formatMilliCent(int $amount): string
    {
        return number_format($amount / 1_000_000, 2, ',', '.') . ' €';
    }
}
