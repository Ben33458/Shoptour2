<?php

declare(strict_types=1);

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_DEPOSIT    = 'deposit';

    public const CATEGORY_TOUR_COLLECTION    = 'tour_collection';
    public const CATEGORY_CUSTOMER_PAYMENT   = 'customer_payment';
    public const CATEGORY_SUPPLIER_PAYMENT   = 'supplier_payment';
    public const CATEGORY_SAFE_DEPOSIT       = 'safe_deposit';
    public const CATEGORY_CASH_COUNT         = 'cash_count';
    public const CATEGORY_ADJUSTMENT         = 'adjustment';

    protected $fillable = [
        'cash_register_id',
        'employee_id',
        'tour_id',
        'type',
        'category',
        'amount_cents',
        'note',
        'transfer_target_register_id',
    ];

    protected $casts = ['amount_cents' => 'integer'];

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function targetRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'transfer_target_register_id');
    }
}
