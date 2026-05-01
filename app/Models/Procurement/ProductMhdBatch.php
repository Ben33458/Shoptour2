<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use App\Models\Employee\Employee;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MHD-Charge: Bestand eines Artikels mit einem bestimmten MHD an einem Lagerort.
 *
 * Kernprinzip:
 * - Pro Produkt + Lager + MHD gibt es einen Batch
 * - `menge` ist der aktuelle Restbestand dieser Charge (Flaschen)
 * - Über `goods_receipt_item_id` ist die Herkunft nachvollziehbar
 * - Über `eingeraeumt_by_employee_id` ist der verantwortliche Mitarbeiter bekannt
 * - Alle Lagerbewegungen referenzieren diesen Batch via stock_movements.mhd_batch_id
 *
 * FIFO: Beim Abverkauf soll immer der Batch mit dem ältesten MHD zuerst reduziert werden.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property \Carbon\Carbon $mhd
 * @property float       $menge
 * @property int|null    $goods_receipt_item_id
 * @property int|null    $eingeraeumt_by_employee_id
 * @property \Carbon\Carbon|null $eingeraeumt_at
 * @property string|null $lagerplatz
 * @property string      $segment               markt|lager|sonstig
 * @property string      $status                aktiv|abverkauft|ausgebucht
 * @property bool        $mhd_warnung_aktiv
 * @property string      $mhd_risiko            ok|bald_ablaufend|abgelaufen|kritisch
 * @property string|null $notiz
 */
class ProductMhdBatch extends Model
{
    public const STATUS_AKTIV      = 'aktiv';
    public const STATUS_ABVERKAUFT = 'abverkauft';
    public const STATUS_AUSGEBUCHT = 'ausgebucht';

    public const RISIKO_OK             = 'ok';
    public const RISIKO_BALD_ABLAUFEND = 'bald_ablaufend';
    public const RISIKO_ABGELAUFEN     = 'abgelaufen';
    public const RISIKO_KRITISCH       = 'kritisch';

    protected $fillable = [
        'company_id',
        'product_id', 'warehouse_id',
        'mhd', 'menge',
        'goods_receipt_item_id',
        'eingeraeumt_by_employee_id', 'eingeraeumt_at',
        'lagerplatz', 'segment',
        'status', 'mhd_warnung_aktiv', 'mhd_risiko',
        'notiz',
    ];

    protected $casts = [
        'mhd'              => 'date',
        'menge'            => 'float',
        'eingeraeumt_at'   => 'datetime',
        'mhd_warnung_aktiv' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function eingeraeumt(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'eingeraeumt_by_employee_id');
    }

    /** @return HasMany<ProductWriteOff> */
    public function writeOffs(): HasMany
    {
        return $this->hasMany(ProductWriteOff::class, 'mhd_batch_id');
    }

    public function isAbgelaufen(): bool
    {
        return $this->mhd->isPast();
    }

    public function tagesBisAblauf(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->mhd->startOfDay(), false);
    }
}
