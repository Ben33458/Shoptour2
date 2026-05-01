<?php

declare(strict_types=1);

namespace App\Models\Debtor;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single dunning notice within a DunningRun — one per customer per run.
 *
 * @property int         $id
 * @property int         $dunning_run_id
 * @property int         $customer_id
 * @property string      $channel          email|post
 * @property int         $dunning_level
 * @property int         $total_open_milli
 * @property int         $interest_milli
 * @property int         $flat_fee_milli
 * @property array|null  $voucher_ids
 * @property string|null $recipient_email
 * @property string|null $recipient_name
 * @property string      $status           pending|sent|failed|skipped
 * @property \Carbon\Carbon|null $sent_at
 * @property string|null $error_message
 * @property string|null $pdf_path
 */
class DunningRunItem extends Model
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_POST  = 'post';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'dunning_run_items';

    protected $fillable = [
        'dunning_run_id',
        'customer_id',
        'channel',
        'dunning_level',
        'total_open_milli',
        'interest_milli',
        'flat_fee_milli',
        'voucher_ids',
        'interest_breakdown',
        'recipient_email',
        'recipient_name',
        'status',
        'sent_at',
        'error_message',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'voucher_ids'        => 'array',
            'interest_breakdown' => 'array',
            'sent_at'            => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function run(): BelongsTo
    {
        return $this->belongsTo(DunningRun::class, 'dunning_run_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function totalDueMilli(): int
    {
        return $this->total_open_milli + $this->interest_milli + $this->flat_fee_milli;
    }

    public function formattedTotal(): string
    {
        return number_format($this->total_open_milli / 1_000_000, 2, ',', '.') . ' €';
    }

    public function channelLabel(): string
    {
        return $this->channel === self::CHANNEL_POST ? 'Briefpost' : 'E-Mail';
    }
}
