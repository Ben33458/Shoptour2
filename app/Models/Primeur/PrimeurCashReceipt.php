<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimeurCashReceipt extends Model
{
    protected $table = 'primeur_cash_receipts';

    protected $fillable = [
        'source_file', 'source_record_id', 'datum', 'belegnummer',
        'sitzungs_id', 'kassen_nr', 'kunden_id',
        'preisgruppe', 'belegstatus', 'belegtext', 'kartenart',
        'ist_storno', 'gesamtbetrag', 'pfandeinnahmen', 'pfandausgaben',
        'bar_gegeben', 'scheckbetrag', 'gesamtertrag', 'belegrabatt',
        'kartenzahlung', 'barbetrag',
        'mwst_betrag_1', 'mwst_betrag_2', 'mwst_satz_1', 'mwst_satz_2',
    ];

    protected $casts = [
        'datum' => 'date',
        'ist_storno' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PrimeurCashReceiptItem::class, 'cash_receipt_id');
    }
}
