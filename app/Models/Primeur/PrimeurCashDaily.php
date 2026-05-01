<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;

class PrimeurCashDaily extends Model
{
    protected $table = 'primeur_cash_daily';

    protected $fillable = [
        'datum', 'markt_id',
        'bankeinreichung', 'storno_ware', 'storno_pfand', 'wechselgeld',
        'bezahlt_bar', 'bezahlt_scheck', 'warenwert_gesamt',
        'pfand_einnahmen', 'pfand_ausgaben',
        'anz_abschoepf_bar', 'abschoepf_bar',
        'anz_ein_aus_zahlungen_bar', 'ein_aus_zahlungen_bar',
        'ertrag', 'anz_rabatt', 'rabattbetrag',
        'anz_karte', 'kartenbetrag', 'barbetrag',
        'anz_belege', 'belegbetrag',
        'storno_belege', 'storno_karte', 'storno_scheck',
        'uebername_in_fibu',
    ];

    protected $casts = [
        'datum' => 'date',
        'uebername_in_fibu' => 'boolean',
    ];

    /** Netto-Warenumsatz = Warenwert abzüglich Stornos */
    public function getNettoUmsatzAttribute(): float
    {
        return (float) $this->belegbetrag - (float) $this->storno_belege - (float) $this->storno_karte - (float) $this->storno_scheck;
    }
}
