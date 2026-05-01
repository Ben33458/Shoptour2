<?php

declare(strict_types=1);

namespace App\Models\Ninox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NinoxRawRecord extends Model
{
    protected $fillable = [
        'run_id', 'import_table_id', 'db_id', 'table_id', 'ninox_id',
        'record_data', 'is_latest', 'ninox_created_at', 'ninox_updated_at',
    ];

    protected $casts = [
        'record_data'       => 'array',
        'is_latest'         => 'boolean',
        'ninox_created_at'  => 'datetime',
        'ninox_updated_at'  => 'datetime',
    ];

    public function importTable(): BelongsTo
    {
        return $this->belongsTo(NinoxImportTable::class, 'import_table_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(NinoxImportRun::class, 'run_id');
    }
}
