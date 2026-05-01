<?php

declare(strict_types=1);

namespace App\Models\Import;

use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $rohzeile_id
 * @property int|null    $product_id
 * @property string      $konflikt_typ
 * @property string|null $feld
 * @property string|null $ods_wert
 * @property string|null $db_wert
 * @property string|null $hinweis
 * @property string      $aktion    offen|uebernehmen|verwerfen|manuell|referenz
 */
class ImportBestandsaufnahmeKonflikt extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'import_bestandsaufnahme_konflikte';

    protected $fillable = [
        'company_id',
        'rohzeile_id',
        'product_id',
        'konflikt_typ',
        'feld',
        'ods_wert',
        'db_wert',
        'hinweis',
        'aktion',
        'bearbeitet_von',
        'bearbeitet_am',
    ];

    protected $casts = [
        'bearbeitet_am' => 'datetime',
    ];

    public function rohzeile(): BelongsTo
    {
        return $this->belongsTo(ImportBestandsaufnahmeRohzeile::class, 'rohzeile_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bearbeitetVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bearbeitet_von');
    }
}
