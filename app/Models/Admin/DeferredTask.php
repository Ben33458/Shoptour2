<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A DB-backed deferred task (queue fallback for shared hosting).
 *
 * Lifecycle: pending → running → done
 *            running → pending  (transient failure, retry)
 *            running → failed   (max_attempts exhausted)
 *
 * @property int                  $id
 * @property int|null             $company_id     BUG-15: tenant scope; null = system-level task
 * @property string               $type           e.g. 'lexoffice.sync_invoice'
 * @property string               $payload_json   JSON-encoded task data
 * @property string               $status         pending|running|done|failed
 * @property int                  $attempts
 * @property int                  $max_attempts
 * @property string|null          $last_error
 * @property \Carbon\Carbon|null  $run_after
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 */
class DeferredTask extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'company_id',
        'type',
        'payload_json',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'run_after',
    ];

    protected $casts = [
        'attempts'     => 'integer',
        'max_attempts' => 'integer',
        'run_after'    => 'datetime',
    ];

    /**
     * Decode the JSON payload into an associative array.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return json_decode($this->payload_json, true) ?? [];
    }

    /**
     * Scope: tasks that are ready to be processed right now.
     *
     * @param  Builder<DeferredTask>  $query
     * @return Builder<DeferredTask>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(static function (Builder $q): void {
                $q->whereNull('run_after')
                    ->orWhere('run_after', '<=', now());
            });
    }
}
