<?php

declare(strict_types=1);

namespace App\Models\Debtor;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note, task, payment promise, dispute flag, or warning attached to a customer
 * (and optionally a specific invoice) in the debt management workflow.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $customer_id
 * @property int|null    $lexoffice_voucher_id
 * @property string      $type          note|task|payment_promise|dispute|warning
 * @property string      $body
 * @property string      $status        open|done
 * @property string|null $promised_date Date (for payment_promise)
 * @property \Carbon\Carbon|null $due_at   Wiedervorlage
 * @property int|null    $assigned_to_user_id
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DebtorNote extends Model
{
    public const TYPE_NOTE            = 'note';
    public const TYPE_TASK            = 'task';
    public const TYPE_PAYMENT_PROMISE = 'payment_promise';
    public const TYPE_DISPUTE         = 'dispute';
    public const TYPE_WARNING         = 'warning';

    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    public static array $types = [
        self::TYPE_NOTE            => 'Notiz',
        self::TYPE_TASK            => 'Aufgabe',
        self::TYPE_PAYMENT_PROMISE => 'Zahlungszusage',
        self::TYPE_DISPUTE         => 'Klärfall',
        self::TYPE_WARNING         => 'Interne Warnung',
    ];

    protected $table = 'debtor_notes';

    protected $fillable = [
        'company_id',
        'customer_id',
        'lexoffice_voucher_id',
        'type',
        'body',
        'status',
        'promised_date',
        'due_at',
        'assigned_to_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'promised_date' => 'date',
            'due_at'        => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LexofficeVoucher::class, 'lexoffice_voucher_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function typeName(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    /** True when this note blocks automatic dunning (dispute or active payment_promise). */
    public function blocksDunning(): bool
    {
        return $this->isOpen() && in_array($this->type, [self::TYPE_DISPUTE, self::TYPE_PAYMENT_PROMISE], true);
    }
}
