<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Verpackungseinheit (VPE) eines Basisartikels mit Umrechnungsfaktor.
 *
 * @property int    $id
 * @property int    $product_id
 * @property string $bezeichnung
 * @property float  $faktor_basiseinheit
 * @property bool   $ist_bestellbar
 * @property bool   $ist_zaehlbar
 * @property bool   $aktiv
 * @property int    $sortierung
 */
class ArtikelVerpackungseinheit extends Model
{
    protected $table = 'artikel_verpackungseinheiten';

    protected $fillable = [
        'company_id',
        'product_id',
        'bezeichnung',
        'faktor_basiseinheit',
        'ist_bestellbar',
        'ist_zaehlbar',
        'aktiv',
        'sortierung',
    ];

    protected $casts = [
        'faktor_basiseinheit' => 'float',
        'ist_bestellbar'      => 'boolean',
        'ist_zaehlbar'        => 'boolean',
        'aktiv'               => 'boolean',
        'sortierung'          => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function positionEingaben(): HasMany
    {
        return $this->hasMany(BestandsaufnahmePositionEingabe::class, 'verpackungseinheit_id');
    }

    /** Menge in VPE in Basiseinheit umrechnen */
    public function toBasiseinheit(float $mengeVpe): float
    {
        return $mengeVpe * $this->faktor_basiseinheit;
    }

    /** Basiseinheit-Menge in VPE-Anzahl umrechnen (abgerundet) */
    public function fromBasiseinheit(float $mengeBasis): float
    {
        if ($this->faktor_basiseinheit == 0) {
            return 0;
        }
        return $mengeBasis / $this->faktor_basiseinheit;
    }
}
