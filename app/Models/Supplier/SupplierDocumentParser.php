<?php

declare(strict_types=1);

namespace App\Models\Supplier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfigurierbare Parser-Definition für eingehende Lieferantendokumente.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $supplier_id
 * @property string      $name
 * @property string      $dokument_typ      lieferschein|rechnung|bestellbestaetigung|sonstig
 * @property string      $parser_typ        csv|xml|pdf_text|pdf_layout|email_body
 * @property string|null $beispiel_datei_pfad
 * @property string|null $beispiel_datei_typ
 * @property array|null  $feld_mapping
 * @property array|null  $erkennungsregeln
 * @property string|null $trennzeichen
 * @property bool        $hat_kopfzeile
 * @property int         $daten_ab_zeile
 * @property float       $konfidenz_schwelle
 * @property bool        $aktiv
 */
class SupplierDocumentParser extends Model
{
    protected $fillable = [
        'company_id', 'supplier_id', 'name',
        'dokument_typ', 'parser_typ',
        'beispiel_datei_pfad', 'beispiel_datei_typ',
        'feld_mapping', 'erkennungsregeln',
        'trennzeichen', 'hat_kopfzeile', 'daten_ab_zeile',
        'konfidenz_schwelle', 'aktiv',
    ];

    protected $casts = [
        'feld_mapping'       => 'array',
        'erkennungsregeln'   => 'array',
        'hat_kopfzeile'      => 'boolean',
        'konfidenz_schwelle' => 'float',
        'aktiv'              => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
