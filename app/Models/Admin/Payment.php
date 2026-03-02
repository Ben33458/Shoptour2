<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment record against an invoice.
 *
 * @property int         $id
 * @property int         $invoice_id
 * @property string      $payment_method   cash|transfer|card|other
 * @property string|null $provider         Payment provider slug (stripe | null = manual)
 * @property string|null $provider_ref     Provider-side reference for idempotency
 * @property string|null $raw_json         Raw webhook payload (audit)
 * @property string      $status           pending|paid|failed|refunded
 * @property int         $amount_milli
 * @property \Carbon\Carbon $paid_at
 * @property string|null $note
 * @property int|null    $created_by_user_id
 */
class Payment extends Model
{
    public const METHOD_CASH     = 'cash';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_CARD     = 'card';
    public const METHOD_OTHER    = 'other';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_TRANSFER,
        self::METHOD_CARD,
        self::METHOD_OTHER,
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_id',
        'payment_method',
        'provider',
        'provider_ref',
        'raw_json',
        'status',
        'amount_milli',
        'paid_at',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_milli' => 'integer',
        'paid_at'      => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
