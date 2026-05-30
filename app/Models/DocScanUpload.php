<?php

namespace App\Models;

use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocScanUpload extends Model
{
    use SoftDeletes;

    protected $table = 'docscan_uploads';

    protected $fillable = [
        'employee_id',
        'original_filename',
        'storage_path',
        'page_count',
        'ai_suggestion',
        'ai_reason',
        'status',
        'destination',
        'routed_by',
        'routed_at',
        'error_message',
        'ai_metadata',
        'deleted_by',
        'intern_typ',
        'intern_id',
        'intern_zugeordnet_at',
        'intern_zugeordnet_by',
    ];

    protected $casts = [
        'routed_at'            => 'datetime',
        'intern_zugeordnet_at' => 'datetime',
        'ai_metadata'          => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function routedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'routed_by');
    }

    public function deletedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'deleted_by');
    }
}
