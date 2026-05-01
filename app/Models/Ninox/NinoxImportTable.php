<?php

declare(strict_types=1);

namespace App\Models\Ninox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NinoxImportTable extends Model
{
    protected $fillable = [
        'run_id', 'db_id', 'table_id', 'table_name', 'status',
        'records_count', 'records_imported', 'error_message', 'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NinoxImportRun::class, 'run_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(NinoxRawRecord::class, 'import_table_id');
    }
}
