<?php
declare(strict_types=1);
namespace App\Models\Vehicles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Betriebliche Fahrzeuge.
 * Vorbereitet für Einsatzhistorie: km Start/Ende, Fahrerzuordnung, Schäden.
 */
class Vehicle extends Model
{
    protected $fillable = [
        'company_id','internal_name','plate_number','manufacturer','model','vehicle_type',
        'vin','first_registration','year','active','location','notes',
        'gross_vehicle_weight','empty_weight','payload_weight','load_volume',
        'max_vpe_without_hand_truck','max_vpe_with_hand_truck',
        'load_length','load_width','load_height','seats',
        'trailer_hitch','max_trailer_load','cooling_unit','required_license_class',
        'tuev_due_date','inspection_due_date','oil_service_due_date',
        'next_service_km','current_mileage','sync_source','sync_source_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'trailer_hitch' => 'boolean',
        'cooling_unit' => 'boolean',
        'first_registration' => 'date',
        'tuev_due_date' => 'date',
        'inspection_due_date' => 'date',
        'oil_service_due_date' => 'date',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function assetIssues(): HasMany
    {
        return $this->hasMany(\App\Models\Assets\AssetIssue::class, 'asset_id')
            ->where('asset_type', 'vehicle');
    }

    public function isTuevDueSoon(int $days = 30): bool
    {
        return $this->tuev_due_date && $this->tuev_due_date->lte(now()->addDays($days));
    }
}
