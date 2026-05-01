<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use App\Models\Catalog\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $session_id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property float|null  $letzter_bestand_basiseinheit
 * @property float|null  $gezaehlter_bestand_basiseinheit
 * @property float|null  $differenz_basiseinheit
 * @property string      $mhd_modus
 * @property int|null    $gezaehlt_von
 * @property \Carbon\Carbon|null $gezaehlt_am
 * @property string|null $korrekturgrund
 * @property string|null $kommentar
 */
class BestandsaufnahmePosition extends Model
{
    protected $table = 'bestandsaufnahme_positionen';

    protected $fillable = [
        'company_id',
        'session_id',
        'product_id',
        'warehouse_id',
        'letzter_bestand_basiseinheit',
        'gezaehlter_bestand_basiseinheit',
        'differenz_basiseinheit',
        'mhd_modus',
        'gezaehlt_von',
        'gezaehlt_am',
        'korrekturgrund',
        'kommentar',
    ];

    protected $casts = [
        'letzter_bestand_basiseinheit'   => 'float',
        'gezaehlter_bestand_basiseinheit' => 'float',
        'differenz_basiseinheit'          => 'float',
        'gezaehlt_am'                     => 'datetime',
    ];

    // Feste Korrekturgründe laut Spezifikation
    public const KORREKTURGRÜNDE = [
        'zählfehler'              => 'Zählfehler',
        'bruch'                   => 'Bruch',
        'schwund'                 => 'Schwund',
        'mhd_abschreibung'        => 'MHD-Abschreibung',
        'umlagerung'              => 'Umlagerung',
        'wareneingangsabweichung' => 'Wareneingangsabweichung',
        'bestandsbereinigung'     => 'Bestandsbereinigung',
        'sonstiges'               => 'Sonstiges',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BestandsaufnahmeSession::class, 'session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function gezaehltVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gezaehlt_von');
    }

    public function eingaben(): HasMany
    {
        return $this->hasMany(BestandsaufnahmePositionEingabe::class, 'position_id');
    }

    public function getKorrekturgrundLabelAttribute(): string
    {
        return self::KORREKTURGRÜNDE[$this->korrekturgrund] ?? ($this->korrekturgrund ?? '');
    }
}
