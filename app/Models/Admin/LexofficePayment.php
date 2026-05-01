<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexofficePayment extends Model
{
    protected $table = 'lexoffice_payments';

    protected $fillable = [
        'company_id',
        'lexoffice_voucher_id',
        'payment_id',
        'voucher_type',
        'contact_name',
        'payment_date',
        'amount',
        'currency',
        'payment_type',
        'open_item_description',
        'open_amount',
        'raw_json',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'synced_at'    => 'datetime',
            'raw_json'     => 'array',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LexofficeVoucher::class, 'lexoffice_voucher_id', 'lexoffice_voucher_id');
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount / 1_000_000, 2, ',', '.') . ' €';
    }
}
