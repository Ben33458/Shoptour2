<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimeurImportRun extends Model
{
    protected $table = 'primeur_import_runs';

    protected $fillable = [
        'source', 'phase', 'status',
        'records_imported', 'records_skipped', 'notes',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function sourceFiles(): HasMany
    {
        return $this->hasMany(PrimeurSourceFile::class, 'import_run_id');
    }
}
