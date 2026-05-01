<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Delivery\Tour;
use App\Models\Employee\Employee;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Leergutrücknahme.
 *
 * Modernisierte Umsetzung der Logik aus ninoxalt_ (Kolabri-Ninox).
 * MHD-Erfassung gehört NICHT zur Leergutrücknahme.
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string|null $rueckgabe_von_typ
 * @property int|null    $rueckgabe_von_id
 * @property string|null $rueckgabe_von_bezeichnung
 * @property int|null    $tour_id
 * @property int|null    $warehouse_id
 * @property string      $status            erfasst|kontrolliert|gebucht|storniert
 * @property float       $paletten_anzahl
 * @property int         $kaesten_gesamt
 * @property string|null $foto_1_pfad
 * @property string|null $foto_2_pfad
 * @property bool        $kontrollzaehlung_durchgefuehrt
 * @property int|null    $kontrolliert_by_employee_id
 * @property \Carbon\Carbon|null $kontrolliert_at
 * @property int|null    $erfasst_by_employee_id
 * @property int|null    $erfasst_by_user_id
 * @property string|null $notiz
 */
class LeergutReturn extends Model
{
    public const STATUS_ERFASST      = 'erfasst';
    public const STATUS_KONTROLLIERT = 'kontrolliert';
    public const STATUS_GEBUCHT      = 'gebucht';
    public const STATUS_STORNIERT    = 'storniert';

    protected $fillable = [
        'company_id',
        'rueckgabe_von_typ', 'rueckgabe_von_id', 'rueckgabe_von_bezeichnung',
        'tour_id', 'warehouse_id',
        'status',
        'paletten_anzahl', 'kaesten_gesamt',
        'foto_1_pfad', 'foto_2_pfad',
        'kontrollzaehlung_durchgefuehrt',
        'kontrolliert_by_employee_id', 'kontrolliert_at',
        'erfasst_by_employee_id', 'erfasst_by_user_id',
        'notiz',
    ];

    protected $casts = [
        'paletten_anzahl'                  => 'float',
        'kontrollzaehlung_durchgefuehrt'   => 'boolean',
        'kontrolliert_at'                  => 'datetime',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function erfasstBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'erfasst_by_employee_id');
    }

    public function kontrolliertBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'kontrolliert_by_employee_id');
    }

    /** @return HasMany<LeergutReturnItem> */
    public function items(): HasMany
    {
        return $this->hasMany(LeergutReturnItem::class);
    }
}
