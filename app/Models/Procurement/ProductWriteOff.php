<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use App\Models\Employee\Employee;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aussortierung / Bruch / MHD-Ware.
 *
 * Mitarbeiter sortieren Ware mit Typ und Ursache aus.
 * Der Bezug zum echten Produkt bleibt IMMER erhalten.
 * Bei typ = mhd_rabatt: Ware ist intern in Shoptour2 als rabattiert geführt.
 * Das interne Modell wird NICHT an LS POS vereinfacht.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property int         $product_id
 * @property int         $warehouse_id
 * @property int|null    $mhd_batch_id
 * @property \Carbon\Carbon|null $mhd
 * @property float       $menge
 * @property string      $typ           bruch|abgelaufen|mhd_rabatt|sonstig
 * @property string      $status        aktiv|verbraucht
 * @property string      $ursache
 * @property int|null    $erfasst_by_employee_id
 * @property int|null    $erfasst_by_user_id
 * @property int|null    $stock_movement_id
 * @property string|null $notiz
 */
class ProductWriteOff extends Model
{
    public const TYP_BRUCH      = 'bruch';
    public const TYP_ABGELAUFEN = 'abgelaufen';
    public const TYP_MHD_RABATT = 'mhd_rabatt';
    public const TYP_SONSTIG    = 'sonstig';

    public const URSACHE_ZU_VIEL_BESTELLT             = 'zu_viel_bestellt';
    public const URSACHE_STAMMKUNDE_ABGESPRUNGEN      = 'stammkunde_abgesprungen';
    public const URSACHE_VERANSTALTUNG_AUSGEFALLEN    = 'veranstaltung_ausgefallen';
    public const URSACHE_FIFO_IGNORIERT               = 'fifo_ignoriert';
    public const URSACHE_WARE_VERGESSEN               = 'ware_vergessen_falsch_verraeumt';
    public const URSACHE_KOMMISSION_RUECKGABE         = 'zu_viel_kommissionsrueckgabe';
    public const URSACHE_UNBEKANNT                    = 'unbekannt';
    public const URSACHE_SONSTIG                      = 'sonstig';

    protected $fillable = [
        'company_id',
        'product_id', 'warehouse_id', 'mhd_batch_id', 'mhd',
        'menge', 'typ', 'status', 'ursache',
        'erfasst_by_employee_id', 'erfasst_by_user_id',
        'stock_movement_id', 'notiz',
    ];

    protected $casts = [
        'mhd'   => 'date',
        'menge' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function mhdBatch(): BelongsTo
    {
        return $this->belongsTo(ProductMhdBatch::class, 'mhd_batch_id');
    }

    public function erfasstBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'erfasst_by_employee_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
