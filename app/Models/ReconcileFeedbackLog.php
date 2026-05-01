<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconcileFeedbackLog extends Model
{
    protected $table = 'reconcile_feedback_log';

    protected $fillable = [
        'entity_type',
        'source',
        'source_id',
        'action',
        'user_id',
        'source_name',
        'source_artnr',
        'source_ean',
        'target_id',
        'target_name',
        'confidence',
        'match_method',
        'was_auto_match',
    ];

    protected $casts = [
        'confidence'     => 'integer',
        'was_auto_match' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
