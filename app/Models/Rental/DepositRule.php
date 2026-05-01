<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;

/**
 * Kautionsregel.
 * Standard: keine Kaution (rule_type = none).
 * amount_net_milli in Milli-Cent.
 */
class DepositRule extends Model
{
    protected $fillable = [
        'company_id','name','rule_type','amount_net_milli','private_only','min_risk_class','active','notes',
    ];

    protected $casts = [
        'amount_net_milli' => 'integer',
        'private_only' => 'boolean',
        'active' => 'boolean',
    ];
}
