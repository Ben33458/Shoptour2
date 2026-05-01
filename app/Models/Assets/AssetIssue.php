<?php
declare(strict_types=1);
namespace App\Models\Assets;
use App\Models\User;
use App\Models\Vehicles\Vehicle;
use App\Models\Rental\RentalInventoryUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemeinsames Mängel-/Defektmodul für Fahrzeuge und Miet-Inventareinheiten.
 *
 * asset_type: vehicle | rental_inventory_unit
 * blocks_usage: Fahrzeug/Gerät nicht einsatzbereit
 * blocks_rental: Mietartikel nicht verleihbar
 *
 * estimated_cost_milli: Schätzkosten in Milli-Cent
 */
class AssetIssue extends Model
{
    protected $fillable = [
        'company_id','asset_type','asset_id','title','description',
        'priority','status','severity','blocks_usage','blocks_rental',
        'estimated_cost_milli','workshop_name','due_date',
        'created_by','assigned_to','resolution_notes','resolved_at',
    ];

    protected $casts = [
        'blocks_usage' => 'boolean',
        'blocks_rental' => 'boolean',
        'estimated_cost_milli' => 'integer',
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public const STATUS_OPEN        = 'open';
    public const STATUS_SCHEDULED   = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function asset(): Vehicle|RentalInventoryUnit|null
    {
        return match($this->asset_type) {
            'vehicle' => Vehicle::find($this->asset_id),
            'rental_inventory_unit' => RentalInventoryUnit::find($this->asset_id),
            default => null,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS]);
    }
}
