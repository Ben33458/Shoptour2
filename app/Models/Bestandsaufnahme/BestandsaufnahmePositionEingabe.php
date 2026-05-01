<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail-Eingabe je VPE für eine Zählposition.
 *
 * @property int   $id
 * @property int   $position_id
 * @property int|null $verpackungseinheit_id
 * @property float $menge_vpe
 * @property float $faktor_basiseinheit
 * @property float $menge_basiseinheit
 */
class BestandsaufnahmePositionEingabe extends Model
{
    protected $table = 'bestandsaufnahme_position_eingaben';

    protected $fillable = [
        'company_id',
        'position_id',
        'verpackungseinheit_id',
        'menge_vpe',
        'faktor_basiseinheit',
        'menge_basiseinheit',
    ];

    protected $casts = [
        'menge_vpe'           => 'float',
        'faktor_basiseinheit' => 'float',
        'menge_basiseinheit'  => 'float',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(BestandsaufnahmePosition::class, 'position_id');
    }

    public function verpackungseinheit(): BelongsTo
    {
        return $this->belongsTo(ArtikelVerpackungseinheit::class, 'verpackungseinheit_id');
    }
}
