<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;

/**
 * Schadenstarif für Mietartikel.
 * amount_net_milli: Schadensbetrag in Milli-Cent
 *
 * Admin und Fahrer können vorgeschlagenen Betrag manuell anpassen.
 */
class DamageTariff extends Model
{
    protected $fillable = [
        'company_id','applies_to_type','applies_to_id','name','amount_net_milli','active','notes',
    ];

    protected $casts = [
        'amount_net_milli' => 'integer',
        'active' => 'boolean',
    ];

    public function getAmountEurAttribute(): float
    {
        return $this->amount_net_milli / 1_000_000;
    }
}
