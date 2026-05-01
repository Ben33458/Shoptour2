<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Communications\Communication;
use App\Models\Communications\CommunicationAttachment;
use Illuminate\Support\Facades\Storage;

class AttachmentProcessorService
{
    /**
     * Store attachment data (raw binary content) and create a DB record.
     *
     * @param Communication $communication
     * @param string $filename
     * @param string $mimeType
     * @param string $rawContent  Binary content of the attachment
     * @param string|null $gmailAttachmentId  Gmail Part ID (for deferred download)
     */
    public function store(
        Communication $communication,
        string $filename,
        string $mimeType,
        string $rawContent,
        ?string $gmailAttachmentId = null
    ): CommunicationAttachment {
        $hash = hash('sha256', $rawContent);
        $year = now()->format('Y');
        $month = now()->format('m');
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $relativePath = "communications/attachments/{$year}/{$month}/{$hash}.{$ext}";

        // Store file
        Storage::disk('local')->put($relativePath, $rawContent);

        return CommunicationAttachment::create([
            'communication_id'    => $communication->id,
            'filename'            => $filename,
            'mime_type'           => $mimeType,
            'size_bytes'          => strlen($rawContent),
            'storage_path'        => $relativePath,
            'sha256_hash'         => $hash,
            'processing_status'   => CommunicationAttachment::STATUS_PROCESSED,
            'gmail_attachment_id' => $gmailAttachmentId,
        ]);
    }

    /**
     * Register an attachment metadata record without storing the file yet (deferred download).
     */
    public function registerPending(
        Communication $communication,
        string $filename,
        string $mimeType,
        int $sizeBytes,
        string $gmailAttachmentId
    ): CommunicationAttachment {
        return CommunicationAttachment::create([
            'communication_id'    => $communication->id,
            'filename'            => $filename,
            'mime_type'           => $mimeType,
            'size_bytes'          => $sizeBytes,
            'processing_status'   => CommunicationAttachment::STATUS_PENDING,
            'gmail_attachment_id' => $gmailAttachmentId,
        ]);
    }
}
