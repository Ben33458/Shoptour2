<?php
declare(strict_types=1);
namespace App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalItemCategory extends Model
{
    protected $fillable = ['company_id', 'name', 'slug', 'description', 'sort_order', 'active'];
    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class, 'category_id');
    }
}
