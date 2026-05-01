<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurierbare Ladenhüter-Schwellenwerte (Single-Row-Konfiguration).
 *
 * @property int  $id
 * @property int  $tage_ohne_verkauf
 * @property int  $max_lagerdauer_tage
 * @property int  $max_bestandsreichweite_tage
 * @property bool $aktiv
 */
class LadenhueterRegel extends Model
{
    protected $table = 'ladenhueter_regeln';

    protected $fillable = [
        'company_id',
        'tage_ohne_verkauf',
        'max_lagerdauer_tage',
        'max_bestandsreichweite_tage',
        'aktiv',
    ];

    protected $casts = [
        'tage_ohne_verkauf'           => 'integer',
        'max_lagerdauer_tage'         => 'integer',
        'max_bestandsreichweite_tage' => 'integer',
        'aktiv'                       => 'boolean',
    ];

    public static function active(): self
    {
        return static::where('aktiv', true)->firstOrFail();
    }
}
