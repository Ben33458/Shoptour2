<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftArea extends Model
{
    protected $fillable = ['name', 'color', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function shifts(): HasMany { return $this->hasMany(Shift::class); }
    public function checklistTemplates(): HasMany { return $this->hasMany(ChecklistTemplate::class); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
