<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrimeurCashReceiptItem extends Model
{
    protected $table = 'primeur_cash_receipt_items';

    protected $fillable = [
        'cash_receipt_id', 'source_record_id', 'datum', 'belegnummer',
        'artikel_id', 'artikeleinheit', 'artikel_bezeichnung',
        'menge', 'vk_preis_regulaer', 'vk_preis_tatsaechlich',
        'vk_preis', 'vk_preis_aktion', 'vk_preis_rabatt',
        'pfandbetrag', 'mwst_satz',
        'sonderverkauf', 'zugabe', 'aktion',
    ];

    protected $casts = [
        'datum' => 'date',
        'sonderverkauf' => 'boolean',
        'zugabe' => 'boolean',
        'aktion' => 'boolean',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PrimeurCashReceipt::class, 'cash_receipt_id');
    }
}
