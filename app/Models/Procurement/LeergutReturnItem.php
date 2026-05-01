<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position einer Leergutrücknahme.
 *
 * Unterscheidet zwischen Standard-Kästen und seltenen/Sonderkästen.
 * Kontrollzählung: Abweichung zwischen `anzahl` und `anzahl_kontrollzaehlung`.
 *
 * @property int         $id
 * @property int         $leergut_return_id
 * @property int|null    $product_id
 * @property bool        $ist_sonderkasten
 * @property string|null $bezeichnung
 * @property string|null $kastentyp
 * @property int         $anzahl
 * @property int|null    $anzahl_kontrollzaehlung
 * @property string|null $abweichungs_notiz
 */
class LeergutReturnItem extends Model
{
    protected $fillable = [
        'leergut_return_id', 'product_id',
        'ist_sonderkasten', 'bezeichnung', 'kastentyp',
        'anzahl', 'anzahl_kontrollzaehlung', 'abweichungs_notiz',
    ];

    protected $casts = [
        'ist_sonderkasten'        => 'boolean',
        'anzahl'                  => 'integer',
        'anzahl_kontrollzaehlung' => 'integer',
    ];

    public function leergutReturn(): BelongsTo
    {
        return $this->belongsTo(LeergutReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hatAbweichung(): bool
    {
        if ($this->anzahl_kontrollzaehlung === null) {
            return false;
        }
        return $this->anzahl !== $this->anzahl_kontrollzaehlung;
    }
}
