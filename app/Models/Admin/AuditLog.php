<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only admin audit trail.
 *
 * @property int         $id
 * @property int|null    $user_id      Web user who performed the action (null = CLI/driver)
 * @property int|null    $company_id   Active company context
 * @property int|null    $token_id     Driver API token ID when triggered by driver
 * @property string      $action       e.g. "invoice.finalized"
 * @property string|null $subject_type e.g. "Invoice"
 * @property int|null    $subject_id
 * @property array|null  $meta_json
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    // Append-only: no updated_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'token_id',
        'action',
        'level',
        'subject_type',
        'subject_id',
        'meta_json',
        'created_at',
    ];

    protected $casts = [
        'meta_json'  => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }
}
