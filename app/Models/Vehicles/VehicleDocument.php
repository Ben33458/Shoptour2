<?php
declare(strict_types=1);
namespace App\Models\Vehicles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fahrzeugdokumente: Fahrzeugschein, Prüfberichte, Versicherung, etc.
 */
class VehicleDocument extends Model
{
    protected $fillable = [
        'vehicle_id','document_type','title','file_path','valid_until','notes',
    ];

    protected $casts = ['valid_until' => 'date'];

    public const TYPES = [
        'fahrzeugschein',
        'pruefbericht',
        'versicherung',
        'hauptuntersuchung',
        'sonstiges',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}
