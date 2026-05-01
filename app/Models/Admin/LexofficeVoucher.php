<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LexofficeVoucher extends Model
{
    // ── Voucher types ────────────────────────────────────────────────────────
    public const TYPE_SALES_INVOICE       = 'salesinvoice';
    public const TYPE_CREDIT_NOTE         = 'salescreditnote';
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
        'raw_json',
        'synced_at',
        'payments_fetched_at',
        // Dunning fields
        'dunning_level',
        'is_dunning_blocked',
        'dunning_block_reason',
        'last_dunned_at',
        // PDF cache
        'pdf_path',
        'pdf_fetched_at',
        // Manual assignment fields
        'assignment_note',
        'manually_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date'        => 'date',
            'due_date'            => 'date',
            'synced_at'           => 'datetime',
            'payments_fetched_at' => 'datetime',
            'last_dunned_at'      => 'datetime',
            'pdf_fetched_at'      => 'datetime',
            'raw_json'                => 'array',
            'is_dunning_blocked'      => 'boolean',
            'manually_confirmed_at'   => 'datetime',
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

    /** Payments pulled from Lexoffice for this voucher. */
    public function payments(): HasMany
    {
        return $this->hasMany(LexofficePayment::class, 'lexoffice_voucher_id', 'lexoffice_voucher_id');
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

    public function isCreditNote(): bool
    {
        return $this->voucher_type === self::TYPE_CREDIT_NOTE
            || $this->voucher_type === self::TYPE_PURCHASE_CREDITNOTE;
    }

    /** Signed total: negative for credit notes (they reduce income). */
    public function signedTotal(): int
    {
        $v = (int) $this->total_gross_amount;
        return $this->isCreditNote() ? -$v : $v;
    }

    /** Signed open amount: negative for credit notes. */
    public function signedOpen(): int
    {
        $v = (int) $this->open_amount;
        return $this->isCreditNote() ? -$v : $v;
    }

    public function formattedTotal(): string
    {
        return $this->formatMilliCent($this->signedTotal());
    }

    public function formattedOpen(): string
    {
        return $this->formatMilliCent($this->signedOpen());
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /** Days overdue (negative = not yet due). */
    public function daysOverdue(): int
    {
        if (! $this->due_date) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now(), false);
    }

    /** True when this voucher can be included in a dunning run. */
    public function isDunnable(): bool
    {
        return $this->isOpen()
            && ! $this->is_dunning_blocked
            && $this->voucher_type === self::TYPE_SALES_INVOICE;
    }

    private function formatMilliCent(int $amount): string
    {
        return number_format($amount / 1_000_000, 2, ',', '.') . ' €';
    }
}
