<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use App\Models\Supplier\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Position eines Wareneingangs.
 *
 * MHD wird hier erfasst (nicht bei Leergutrücknahme!).
 * Mehrere MHD-Chargen für eine Position → product_mhd_batches.
 *
 * @property int         $id
 * @property int         $goods_receipt_id
 * @property int         $product_id
 * @property int|null    $purchase_order_item_id
 * @property float       $bestellte_menge
 * @property float       $gelieferte_menge
 * @property string      $abweichungs_grund
 * @property string|null $abweichungs_notiz
 * @property string      $mhd_erfassung_modus   nie|optional|empfohlen|pflicht
 * @property \Carbon\Carbon|null $mhd
 * @property bool        $eingebucht
 * @property \Carbon\Carbon|null $eingebucht_at
 */
class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'product_id', 'purchase_order_item_id',
        'bestellte_menge', 'gelieferte_menge',
        'abweichungs_grund', 'abweichungs_notiz',
        'mhd_erfassung_modus', 'mhd',
        'eingebucht', 'eingebucht_at',
    ];

    protected $casts = [
        'bestellte_menge'  => 'float',
        'gelieferte_menge' => 'float',
        'mhd'              => 'date',
        'eingebucht'       => 'boolean',
        'eingebucht_at'    => 'datetime',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return HasMany<ProductMhdBatch> */
    public function mhdBatches(): HasMany
    {
        return $this->hasMany(ProductMhdBatch::class);
    }

    public function hatAbweichung(): bool
    {
        return abs($this->gelieferte_menge - $this->bestellte_menge) > 0.001;
    }
}
