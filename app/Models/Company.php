<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A legal entity / business unit that owns customers, warehouses, products etc.
 *
 * Multi-company isolation works as follows:
 *   1. CompanyMiddleware resolves the active company from the session.
 *   2. Services / repositories scope their queries by company_id.
 *   3. company_id is nullable on all tables so that rows without a company
 *      are accessible to all companies (shared catalogue entries etc.).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $vat_id
 * @property string|null $address
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'vat_id',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return HasMany<\App\Models\Supplier\Supplier> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(\App\Models\Supplier\Supplier::class);
    }
}
