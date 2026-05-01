<?php
namespace App\Models\Employee;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaskComment extends Model
{
    protected $fillable = [
        'task_id', 'author_type', 'author_id', 'body', 'images', 'is_liveblog',
    ];

    protected $casts = [
        'images'      => 'array',
        'is_liveblog' => 'boolean',
    ];

    public function task(): BelongsTo { return $this->belongsTo(EmployeeTask::class, 'task_id'); }

    public function author(): Model|null
    {
        if ($this->author_type === 'user') {
            return User::find($this->author_id);
        }
        return Employee::find($this->author_id);
    }

    public function getAuthorNameAttribute(): string
    {
        $author = $this->author();
        if (!$author) return 'Unbekannt';
        if ($this->author_type === 'user') {
            return $author->name ?? 'Admin';
        }
        return $author->full_name ?? 'Mitarbeiter';
    }
}
