<?php

declare(strict_types=1);

namespace App\Models\Ninox;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NinoxImportRun extends Model
{
    protected $fillable = [
        'db_id', 'created_by', 'status', 'tables_count', 'records_imported',
        'records_skipped', 'error_message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(NinoxImportTable::class, 'run_id');
    }

    public function duration(): ?string
    {
        if (! $this->finished_at || ! $this->started_at) {
            return null;
        }
        $secs = $this->started_at->diffInSeconds($this->finished_at);
        return $secs < 60 ? "{$secs}s" : round($secs / 60, 1) . ' min';
    }
}
