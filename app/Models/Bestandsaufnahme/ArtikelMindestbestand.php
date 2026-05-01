<?php

declare(strict_types=1);

namespace App\Models\Bestandsaufnahme;

use App\Models\Catalog\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lagerbezogener Mindestbestand je Artikel.
 * Immer in Basiseinheit gespeichert.
 *
 * @property int         $id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property float       $mindestbestand_basiseinheit
 * @property string      $quelle              manuell|import
 * @property string|null $quelle_datei
 * @property string|null $quelle_tabellenblatt
 * @property bool        $konflikt_flag
 * @property array|null  $konflikt_details
 */
class ArtikelMindestbestand extends Model
{
    protected $table = 'artikel_mindestbestaende';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'mindestbestand_basiseinheit',
        'quelle',
        'quelle_datei',
        'quelle_tabellenblatt',
        'konflikt_flag',
        'konflikt_details',
        'updated_by',
    ];

    protected $casts = [
        'mindestbestand_basiseinheit' => 'float',
        'konflikt_flag'               => 'boolean',
        'konflikt_details'            => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
