<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent record of a confirmed match between a local entity and an
 * external data source (Ninox, JTL-WaWi, Lexoffice).
 *
 * @property int         $id
 * @property string      $entity_type     'customer' | 'supplier' | 'product'
 * @property int         $local_id        ID in customers / suppliers / products
 * @property string      $source          'ninox' | 'wawi' | 'lexoffice'
 * @property string      $source_id       External record key
 * @property string      $status          'auto' | 'confirmed' | 'ignored'
 * @property int|null    $matched_by      users.id, NULL = auto-matched
 * @property array|null  $source_snapshot Snapshot of external data at match time
 * @property array|null  $diff_at_match   Fields that differed at match time
 * @property \Carbon\Carbon|null $confirmed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SourceMatch extends Model
{
    public const STATUS_AUTO      = 'auto';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IGNORED   = 'ignored';

    public const SOURCE_NINOX     = 'ninox';
    public const SOURCE_WAWI      = 'wawi';
    public const SOURCE_LEXOFFICE = 'lexoffice';

    public const ENTITY_CUSTOMER  = 'customer';
    public const ENTITY_SUPPLIER  = 'supplier';
    public const ENTITY_PRODUCT   = 'product';

    protected $fillable = [
        'entity_type',
        'local_id',
        'source',
        'source_id',
        'status',
        'matched_by',
        'source_snapshot',
        'diff_at_match',
        'confirmed_at',
    ];

    protected $casts = [
        'local_id'        => 'integer',
        'matched_by'      => 'integer',
        'source_snapshot' => 'array',
        'diff_at_match'   => 'array',
        'confirmed_at'    => 'datetime',
    ];

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }
}
