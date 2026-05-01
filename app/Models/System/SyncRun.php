<?php

declare(strict_types=1);

namespace App\Models\System;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Einheitliches Log aller Synchronisierungsläufe (WAWI, Ninox, etc.)
 *
 * @property int         $id
 * @property string      $source            wawi|ninox|manual
 * @property string|null $entity            z.B. 'artikel', 'kunden' (bei WAWI pro Entity-Call)
 * @property string      $status            running|completed|failed|partial
 * @property int         $records_processed
 * @property int         $records_skipped
 * @property string|null $error_message
 * @property string|null $triggered_by      IP-Adresse oder 'cli'
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $finished_at
 * @property \Carbon\Carbon|null $created_at
 */
class SyncRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'source',
        'entity',
        'status',
        'records_processed',
        'records_skipped',
        'error_message',
        'triggered_by',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'records_processed' => 'integer',
        'records_skipped'   => 'integer',
        'started_at'        => 'datetime',
        'finished_at'       => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    /**
     * Letzter erfolgreicher Sync-Lauf für eine Quelle.
     */
    public static function lastSuccessfulFor(string $source): ?self
    {
        return static::where('source', $source)
            ->where('status', 'completed')
            ->latest('started_at')
            ->first();
    }

    /**
     * Ist der Sync überfällig? (kein completed-Lauf in den letzten $hours Stunden)
     */
    public static function isOverdue(string $source, int $hours = 12): bool
    {
        $last = static::lastSuccessfulFor($source);

        if ($last === null) {
            return true;
        }

        return $last->started_at->lt(now()->subHours($hours));
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    /**
     * Laufzeit in lesbarem Format, z.B. "2 Sek." oder "1 Min. 23 Sek."
     */
    public function durationLabel(): string
    {
        if ($this->finished_at === null) {
            return '—';
        }

        $seconds = (int) $this->started_at->diffInSeconds($this->finished_at);

        if ($seconds < 60) {
            return "{$seconds} Sek.";
        }

        $minutes = (int) floor($seconds / 60);
        $rest    = $seconds % 60;

        return "{$minutes} Min. {$rest} Sek.";
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'wawi'   => 'JTL WAWI',
            'ninox'  => 'Ninox',
            'manual' => 'Manuell',
            default  => ucfirst($this->source),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'running'   => 'Läuft',
            'completed' => 'Erfolgreich',
            'failed'    => 'Fehler',
            'partial'   => 'Teilweise',
            default     => $this->status,
        };
    }
}
