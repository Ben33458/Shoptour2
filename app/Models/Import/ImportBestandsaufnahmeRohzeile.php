<?php

declare(strict_types=1);

namespace App\Models\Import;

use App\Models\Catalog\Product;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property int    $importlauf_id
 * @property string $tabellenblatt
 * @property int    $zeilennummer
 * @property array  $roh_payload_json
 * @property string $erkannt_status
 * @property string|null $mapping_hinweis
 * @property int|null $product_id
 * @property int|null $lieferant_id
 */
class ImportBestandsaufnahmeRohzeile extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'import_bestandsaufnahme_rohzeilen';

    protected $fillable = [
        'company_id',
        'importlauf_id',
        'tabellenblatt',
        'zeilennummer',
        'roh_payload_json',
        'erkannt_status',
        'mapping_hinweis',
        'product_id',
        'lieferant_id',
    ];

    protected $casts = [
        'roh_payload_json' => 'array',
        'zeilennummer'     => 'integer',
    ];

    public function importlauf(): BelongsTo
    {
        return $this->belongsTo(ImportBestandsaufnahmeLauf::class, 'importlauf_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'lieferant_id');
    }

    public function konflikte(): HasMany
    {
        return $this->hasMany(ImportBestandsaufnahmeKonflikt::class, 'rohzeile_id');
    }
}
