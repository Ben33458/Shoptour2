<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationAttachment extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_ERROR     = 'error';

    protected $table = 'communication_attachments';

    protected $fillable = [
        'communication_id', 'filename', 'mime_type', 'size_bytes',
        'storage_path', 'sha256_hash', 'processing_status',
        'extracted_text', 'extracted_at', 'gmail_attachment_id',
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
    ];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
