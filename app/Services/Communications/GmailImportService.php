<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Communications\Communication;
use App\Models\Communications\CommunicationAttachment;
use App\Models\Communications\CommunicationConfidence;
use App\Models\Communications\CommunicationRule;
use App\Models\Communications\CommunicationTag;
use App\Models\Communications\GmailSyncState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GmailImportService
{
    public function __construct(
        private AssignmentService         $assignment,
        private RuleEngineService         $ruleEngine,
        private AttachmentProcessorService $attachments,
        private CommunicationAuditService  $audit,
    ) {}

    /**
     * Nachrichten aus Gmail importieren.
     *
     * $manual = true  → Manueller Knopfdruck: letzte 50 aus INBOX
     * $manual = false → Automatisierter Lauf: nur nest/einflug
     *
     * Label-Workflow (manuell):
     *   Erfolg: nest/gelandet setzen
     *   Fehler:  nest/abgestürzt setzen
     *
     * Label-Workflow (automatisiert):
     *   1. nest/im-flug setzen (vor Verarbeitung)
     *   2a. Erfolg: nest/einflug + nest/im-flug entfernen, nest/gelandet setzen
     *   2b. Fehler:  nest/im-flug entfernen, nest/abgestürzt setzen
     *
     * @return int Anzahl neu importierter Nachrichten
     */
    public function importNew(GmailSyncState $syncState, bool $manual = false): int
    {
        $client = new GmailClient($syncState);

        $syncState->sync_status = GmailSyncState::STATUS_RUNNING;
        $syncState->save();

        try {
            // In beiden Modi Labels sicherstellen (manuell: für gelandet/abgestürzt)
            $nestLabels = $client->ensureNestLabels();

            if ($manual) {
                // Manuell: letzte 50 aus INBOX
                $messageRefs = $client->listInboxMessages(50);
            } else {
                // Automatisiert: nur Nachrichten mit nest/einflug
                $messageRefs = $client->listMessagesByLabelId($nestLabels[GmailClient::LABEL_EINFLUG]);
            }

            $count = 0;
            foreach ($messageRefs as $ref) {
                $gmailId = $ref['id'] ?? null;
                if (!$gmailId) continue;

                if (!$manual) {
                    // nest/im-flug setzen — Verarbeitung beginnt
                    $client->modifyLabels($gmailId, [$nestLabels[GmailClient::LABEL_IM_FLUG]], []);
                }

                try {
                    $this->importMessage($client, $syncState, $gmailId);
                    $count++;

                    if ($manual) {
                        // Manuell: nur gelandet setzen
                        $client->modifyLabels($gmailId, [$nestLabels[GmailClient::LABEL_GELANDET]], []);
                    } else {
                        // Automatisiert: nest/einflug + nest/im-flug entfernen, nest/gelandet setzen
                        $client->modifyLabels(
                            $gmailId,
                            [$nestLabels[GmailClient::LABEL_GELANDET]],
                            [$nestLabels[GmailClient::LABEL_EINFLUG], $nestLabels[GmailClient::LABEL_IM_FLUG]]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("GmailImport: Nachricht {$gmailId} übersprungen", ['error' => $e->getMessage()]);

                    if ($manual) {
                        // Manuell: abgestürzt setzen
                        $client->modifyLabels($gmailId, [$nestLabels[GmailClient::LABEL_ABGESTUERZT]], []);
                    } else {
                        // Automatisiert: nest/im-flug entfernen, nest/abgestürzt setzen
                        $client->modifyLabels(
                            $gmailId,
                            [$nestLabels[GmailClient::LABEL_ABGESTUERZT]],
                            [$nestLabels[GmailClient::LABEL_IM_FLUG]]
                        );
                    }
                }
            }

            $syncState->sync_status    = GmailSyncState::STATUS_IDLE;
            $syncState->last_synced_at = now();
            $syncState->error_message  = null;
            $syncState->save();

            return $count;

        } catch (\Throwable $e) {
            $syncState->sync_status   = GmailSyncState::STATUS_ERROR;
            $syncState->error_message = $e->getMessage();
            $syncState->save();
            throw $e;
        }
    }

    private function importMessage(GmailClient $client, GmailSyncState $syncState, string $gmailId): void
    {
        $raw = $client->getMessage($gmailId);

        // Extract headers
        $headers = [];
        foreach ($raw['payload']['headers'] ?? [] as $h) {
            $headers[strtolower($h['name'])] = $h['value'];
        }

        $messageId = $headers['message-id'] ?? null;

        // Deduplication
        if ($messageId && Communication::where('message_id', $messageId)->exists()) {
            return;
        }

        // Also deduplicate by gmail_id
        if (Communication::where('gmail_id', $gmailId)->exists()) {
            return;
        }

        $fromAddress   = $this->extractEmail($headers['from'] ?? '');
        $toAddresses   = $this->extractEmails($headers['to'] ?? '');
        $ccAddresses   = $this->extractEmails($headers['cc'] ?? '');
        $subject       = $headers['subject'] ?? null;
        $receivedAt    = isset($raw['internalDate'])
            ? \Carbon\Carbon::createFromTimestampMs($raw['internalDate'])
            : now();
        $snippet       = $raw['snippet'] ?? null;
        $threadId      = $raw['threadId'] ?? null;

        // Parse body parts
        [$bodyText, $bodyHtml, $attachmentParts] = $this->parseParts($raw['payload'] ?? []);

        // Assignment
        $assignmentResult = $this->assignment->assign($fromAddress);

        // Rule engine
        $engineResult = $this->ruleEngine->evaluate([
            'from_address'       => $fromAddress,
            'to_addresses'       => $toAddresses,
            'subject'            => $subject,
            'has_attachments'    => !empty($attachmentParts),
            'attachment_mimetypes' => array_column($attachmentParts, 'mimeType'),
            'company_id'         => $syncState->company_id,
        ], $assignmentResult);

        // Determine status
        $status = $engineResult['overall'] >= 80
            ? Communication::STATUS_ASSIGNED
            : Communication::STATUS_REVIEW;

        // Also mark as assigned if we have a firm org match via assignment (even without rules)
        if ($assignmentResult['dim_org'] >= 70 && $assignmentResult['communicable_id']) {
            $status = Communication::STATUS_ASSIGNED;
        }

        DB::transaction(function () use (
            $syncState, $gmailId, $messageId, $threadId, $fromAddress, $toAddresses,
            $ccAddresses, $subject, $bodyText, $bodyHtml, $snippet, $receivedAt,
            $headers, $assignmentResult, $engineResult, $status, $attachmentParts, $client
        ) {
            $communication = Communication::create([
                'company_id'         => $syncState->company_id,
                'source'             => Communication::SOURCE_GMAIL,
                'direction'          => Communication::DIRECTION_IN,
                'message_id'         => $messageId,
                'thread_id'          => $threadId,
                'gmail_id'           => $gmailId,
                'from_address'       => $fromAddress,
                'to_addresses'       => $toAddresses,
                'cc_addresses'       => $ccAddresses ?: null,
                'subject'            => $subject,
                'body_text'          => $bodyText,
                'body_html'          => $bodyHtml,
                'snippet'            => $snippet,
                'received_at'        => $receivedAt,
                'imported_at'        => now(),
                'status'             => $status,
                'communicable_type'  => $engineResult['communicable_type'],
                'communicable_id'    => $engineResult['communicable_id'],
                'sender_contact_id'  => $assignmentResult['sender_contact_id'],
                'overall_confidence' => $engineResult['overall'],
                'raw_headers'        => $headers,
            ]);

            // Save confidence record
            CommunicationConfidence::create([
                'communication_id' => $communication->id,
                'dim_contact'      => $engineResult['dim_contact'],
                'dim_org'          => $engineResult['dim_org'],
                'dim_role'         => $engineResult['dim_role'],
                'dim_category'     => $engineResult['dim_category'],
                'dim_document'     => $engineResult['dim_document'],
                'dim_action'       => $engineResult['dim_action'],
                'overall'          => $engineResult['overall'],
                'rule_matches'     => $engineResult['rule_matches'] ?: null,
            ]);

            // Register attachments (register as pending — actual download on demand or via queue)
            foreach ($attachmentParts as $part) {
                if (!empty($part['body']['attachmentId'])) {
                    $this->attachments->registerPending(
                        $communication,
                        $part['filename'] ?? 'attachment',
                        $part['mimeType'] ?? 'application/octet-stream',
                        $part['body']['size'] ?? 0,
                        $part['body']['attachmentId']
                    );
                }
            }

            // Apply tag rules
            foreach ($engineResult['rule_matches'] as $match) {
                if ($match['action_type'] === CommunicationRule::ACTION_SET_TAG && $match['action_value']) {
                    $tag = CommunicationTag::where('company_id', $syncState->company_id)
                        ->where('name', $match['action_value'])
                        ->first();
                    if ($tag) {
                        $communication->tags()->attach($tag->id);
                    }
                }
            }

            // Audit: imported
            $this->audit->log($communication, 'imported', [
                'from'       => $fromAddress,
                'subject'    => $subject,
                'gmail_id'   => $gmailId,
                'confidence' => $engineResult['overall'],
            ]);

            // Audit: rule matches
            if (!empty($engineResult['rule_matches'])) {
                $this->audit->log($communication, 'rule_matched', [
                    'rules' => array_column($engineResult['rule_matches'], 'rule_name'),
                ]);
            }
        });
    }

    // =========================================================================
    // Parser helpers
    // =========================================================================

    private function parseParts(array $payload): array
    {
        $bodyText        = null;
        $bodyHtml        = null;
        $attachmentParts = [];

        $this->walkParts($payload, $bodyText, $bodyHtml, $attachmentParts);

        return [$bodyText, $bodyHtml, $attachmentParts];
    }

    private function walkParts(array $part, ?string &$bodyText, ?string &$bodyHtml, array &$attachments): void
    {
        $mimeType = $part['mimeType'] ?? '';
        $body     = $part['body'] ?? [];
        $filename = $part['filename'] ?? '';

        if ($filename && !empty($body['attachmentId'])) {
            $attachments[] = $part;
            return;
        }

        if ($mimeType === 'text/plain' && !empty($body['data'])) {
            $bodyText = base64_decode(strtr($body['data'], '-_', '+/'));
            return;
        }

        if ($mimeType === 'text/html' && !empty($body['data'])) {
            $bodyHtml = base64_decode(strtr($body['data'], '-_', '+/'));
            return;
        }

        foreach ($part['parts'] ?? [] as $subPart) {
            $this->walkParts($subPart, $bodyText, $bodyHtml, $attachments);
        }
    }

    private function extractEmail(string $raw): string
    {
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return strtolower(trim($m[1]));
        }
        return strtolower(trim($raw));
    }

    private function extractEmails(string $raw): array
    {
        $parts  = explode(',', $raw);
        $emails = [];
        foreach ($parts as $p) {
            $e = $this->extractEmail($p);
            if ($e) $emails[] = $e;
        }
        return $emails;
    }
}
