<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use Illuminate\Database\Eloquent\Model;

/**
 * MHD-Regel nach Bezugstyp mit Priorität.
 *
 * Auflösungsreihenfolge: artikel > lager > kategorie > warengruppe > default
 *
 * @property int         $id
 * @property string      $bezug_typ   artikel|lager|kategorie|warengruppe|default
 * @property int|null    $bezug_id
 * @property string      $modus       nie|optional|pflichtig
 * @property int         $warnung_tage
 * @property int         $kritisch_tage
 * @property int         $prioritaet
 * @property bool        $aktiv
 */
class MhdRegel extends Model
{
    protected $table = 'mhd_regeln';

    protected $fillable = [
        'company_id',
        'bezug_typ',
        'bezug_id',
        'modus',
        'warnung_tage',
        'kritisch_tage',
        'prioritaet',
        'aktiv',
    ];

    protected $casts = [
        'bezug_id'      => 'integer',
        'warnung_tage'  => 'integer',
        'kritisch_tage' => 'integer',
        'prioritaet'    => 'integer',
        'aktiv'         => 'boolean',
    ];

    public const BEZUG_TYPEN_PRIORITAET = [
        'artikel'     => 5,
        'lager'       => 4,
        'kategorie'   => 3,
        'warengruppe' => 2,
        'default'     => 1,
    ];

    public const MODI = [
        'nie'       => 'Nie',
        'optional'  => 'Optional',
        'pflichtig' => 'Pflichtig',
    ];
}
