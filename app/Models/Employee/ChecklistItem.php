<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = ['checklist_template_id', 'label', 'sort_order', 'is_required'];
    protected $casts = ['is_required' => 'boolean'];

    public function template(): BelongsTo { return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id'); }
}
