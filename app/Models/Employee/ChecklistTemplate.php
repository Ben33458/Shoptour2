<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    protected $fillable = ['name', 'type', 'shift_area_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function shiftArea(): BelongsTo { return $this->belongsTo(ShiftArea::class); }
    public function items(): HasMany { return $this->hasMany(ChecklistItem::class)->orderBy('sort_order'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
