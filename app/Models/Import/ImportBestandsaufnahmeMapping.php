<?php

declare(strict_types=1);

namespace App\Models\Import;

use App\Models\Supplier\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spaltenmapping je Tabellenblatt für den ODS-Import.
 *
 * @property int         $id
 * @property string      $tabellenblatt
 * @property int|null    $lieferant_id
 * @property int|null    $lager_id_standard
 * @property string|null $spalte_kolabri_artnr
 * @property string|null $spalte_lieferanten_artnr
 * @property string|null $spalte_produktname
 * @property string|null $spalte_mindestbestand
 * @property string|null $spalte_bestand
 * @property string|null $spalte_bestellmenge
 * @property string|null $spalte_mhd
 * @property string|null $spalte_vpe_hinweis
 * @property string|null $spalte_bestellhinweis
 * @property string      $blatt_typ   A|B|C|unbekannt
 * @property string|null $notiz
 * @property bool        $aktiv
 */
class ImportBestandsaufnahmeMapping extends Model
{
    protected $table = 'import_bestandsaufnahme_mappings';

    protected $fillable = [
        'company_id',
        'tabellenblatt',
        'lieferant_id',
        'lager_id_standard',
        'spalte_kolabri_artnr',
        'spalte_lieferanten_artnr',
        'spalte_produktname',
        'spalte_mindestbestand',
        'spalte_bestand',
        'spalte_bestellmenge',
        'spalte_mhd',
        'spalte_vpe_hinweis',
        'spalte_bestellhinweis',
        'blatt_typ',
        'notiz',
        'aktiv',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
    ];

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'lieferant_id');
    }

    public function lagerStandard(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'lager_id_standard');
    }
}
