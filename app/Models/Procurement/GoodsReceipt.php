<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Inventory\Warehouse;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wareneingang — eigenständiger Lebenszyklus unabhängig von PurchaseOrder.
 *
 * Status: angekuendigt → in_kontrolle → gebucht | abgebrochen
 *
 * Kontrollstufen:
 *   nur_angekommen           → Direktes Buchen ohne weitere Prüfung
 *   summenkontrolle_vpe      → VPE-Gesamtzahl prüfen
 *   summenkontrolle_palette  → Palettenanzahl prüfen
 *   positionskontrolle       → Jede Position prüfen
 *   positionskontrolle_mit_mhd → Positionen + MHD bei pflichtigen Produkten
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int|null    $purchase_order_id
 * @property int         $supplier_id
 * @property int         $warehouse_id
 * @property int|null    $document_id
 * @property string|null $lieferschein_nr
 * @property string      $status
 * @property string      $kontrollstufe
 * @property \Carbon\Carbon|null $arrived_at
 * @property \Carbon\Carbon|null $kontrolle_beginn_at
 * @property \Carbon\Carbon|null $gebucht_at
 * @property int|null    $gebucht_by_user_id
 * @property int|null    $gebucht_by_employee_id
 * @property float|null  $paletten_anzahl_erwartet
 * @property float|null  $paletten_anzahl_geliefert
 * @property string|null $notiz
 */
class GoodsReceipt extends Model
{
    public const STATUS_ANGEKUENDIGT = 'angekuendigt';
    public const STATUS_IN_KONTROLLE = 'in_kontrolle';
    public const STATUS_GEBUCHT      = 'gebucht';
    public const STATUS_ABGEBROCHEN  = 'abgebrochen';

    public const KONTROLLSTUFE_NUR_ANGEKOMMEN       = 'nur_angekommen';
    public const KONTROLLSTUFE_SUMME_VPE            = 'summenkontrolle_vpe';
    public const KONTROLLSTUFE_SUMME_PALETTE        = 'summenkontrolle_palette';
    public const KONTROLLSTUFE_POSITION             = 'positionskontrolle';
    public const KONTROLLSTUFE_POSITION_MIT_MHD     = 'positionskontrolle_mit_mhd';

    protected $fillable = [
        'company_id',
        'purchase_order_id', 'supplier_id', 'warehouse_id', 'document_id',
        'lieferschein_nr',
        'status', 'kontrollstufe',
        'arrived_at', 'kontrolle_beginn_at', 'gebucht_at',
        'gebucht_by_user_id', 'gebucht_by_employee_id',
        'paletten_anzahl_erwartet', 'paletten_anzahl_geliefert',
        'notiz',
    ];

    protected $casts = [
        'arrived_at'          => 'datetime',
        'kontrolle_beginn_at' => 'datetime',
        'gebucht_at'          => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return HasMany<GoodsReceiptItem> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function isGebucht(): bool
    {
        return $this->status === self::STATUS_GEBUCHT;
    }

    public function canBook(): bool
    {
        return in_array($this->status, [self::STATUS_ANGEKUENDIGT, self::STATUS_IN_KONTROLLE]);
    }

    /** Effektive Kontrollstufe: Override > Lieferanten-Default > Fallback */
    public function effektiveKontrollstufe(): string
    {
        return $this->kontrollstufe ?? $this->supplier?->kontrollstufe_default ?? self::KONTROLLSTUFE_NUR_ANGEKOMMEN;
    }
}
