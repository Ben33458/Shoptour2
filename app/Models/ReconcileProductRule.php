<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconcileProductRule extends Model
{
    public const TYPE_SYNONYM = 'synonym';
    public const TYPE_NOISE   = 'noise';

    protected $fillable = [
        'type',
        'source_token',
        'target_token',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
