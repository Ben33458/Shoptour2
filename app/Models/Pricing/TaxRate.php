<?php

declare(strict_types=1);

namespace App\Models\Pricing;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a VAT / tax rate used throughout the Kolabri pricing system.
 *
 * rate_basis_points stores the rate as integer basis points where
 * 10_000 bp = 100 %. Examples:
 *   1_900 bp = 19 % German standard VAT
 *     700 bp =  7 % German reduced VAT
 *
 * @property int    $id
 * @property string $name
 * @property int    $rate_basis_points
 * @property bool   $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TaxRate extends Model
{
    protected $fillable = [
        'name',
        'rate_basis_points',
        'active',
    ];

    protected $casts = [
        'rate_basis_points' => 'integer',
        'active'            => 'boolean',
    ];
}
