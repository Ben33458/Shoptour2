<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a product group (Warengruppe) for categorization in the shop.
 *
 * Warengruppen are orthogonal to categories: a product belongs to exactly one
 * category (e.g. "Bier > Pils") and optionally to one Warengruppe
 * (e.g. "Alkoholfreie Getränke").
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string      $name
 * @property string|null $description
 * @property bool        $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Collection<int, Product> $products
 */
class Warengruppe extends Model
{
    protected $table = 'warengruppen';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All products assigned to this Warengruppe.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'warengruppe_id');
    }
}
