<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a single communication event (email, phone call, manual note).
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string      $source          gmail|manual|phone
 * @property string      $direction       in|out
 * @property string|null $message_id      RFC Message-ID (dedup key)
 * @property string|null $thread_id
 * @property string|null $gmail_id
 * @property string|null $from_address
 * @property array|null  $to_addresses
 * @property array|null  $cc_addresses
 * @property string|null $subject
 * @property string|null $body_text
 * @property string|null $body_html
 * @property string|null $snippet
 * @property \Carbon\Carbon|null $received_at
 * @property \Carbon\Carbon|null $imported_at
 * @property string      $status          new|review|assigned|archived
 * @property string|null $communicable_type
 * @property int|null    $communicable_id
 * @property int|null    $sender_contact_id
 * @property int|null    $overall_confidence
 * @property \Carbon\Carbon|null $reviewed_at
 * @property int|null    $reviewed_by_user_id
 * @property int|null    $created_by_user_id
 * @property array|null  $raw_headers
 */
class Communication extends Model
{
    public const SOURCE_GMAIL  = 'gmail';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_PHONE  = 'phone';

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    public const STATUS_NEW      = 'new';
    public const STATUS_REVIEW   = 'review';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id', 'source', 'direction',
        'message_id', 'thread_id', 'gmail_id',
        'from_address', 'to_addresses', 'cc_addresses',
        'subject', 'body_text', 'body_html', 'snippet',
        'received_at', 'imported_at', 'status',
        'communicable_type', 'communicable_id',
        'sender_contact_id', 'overall_confidence',
        'reviewed_at', 'reviewed_by_user_id', 'created_by_user_id',
        'raw_headers',
    ];

    protected $casts = [
        'to_addresses' => 'array',
        'cc_addresses' => 'array',
        'raw_headers'  => 'array',
        'received_at'  => 'datetime',
        'imported_at'  => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function communicable(): MorphTo
    {
        return $this->morphTo();
    }

    public function senderContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'sender_contact_id');
    }

    public function confidence(): HasOne
    {
        return $this->hasOne(CommunicationConfidence::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(CommunicationAudit::class)->orderBy('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CommunicationTag::class, 'communication_tag_pivot', 'communication_id', 'tag_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeNeedsReview($query): void
    {
        $query->where('status', self::STATUS_REVIEW);
    }

    public function scopeNew($query): void
    {
        $query->where('status', self::STATUS_NEW);
    }

    public function scopeForCompany($query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_NEW      => 'Neu',
            self::STATUS_REVIEW   => 'Zu prüfen',
            self::STATUS_ASSIGNED => 'Zugeordnet',
            self::STATUS_ARCHIVED => 'Archiviert',
            default               => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW      => 'badge-info',
            self::STATUS_REVIEW   => 'badge-warning',
            self::STATUS_ASSIGNED => 'badge-success',
            self::STATUS_ARCHIVED => 'badge-neutral',
            default               => 'badge-neutral',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_GMAIL  => 'Gmail',
            self::SOURCE_MANUAL => 'Manuell',
            self::SOURCE_PHONE  => 'Telefon',
            default             => $this->source,
        };
    }
}
