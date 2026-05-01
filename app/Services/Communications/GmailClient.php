<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Communications\GmailSyncState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Gmail REST API v1.
 *
 * Credentials: config/services.php → services.gmail.*
 * Tokens:      encrypted in gmail_sync_state table
 *
 * Gmail-Labels für den automatisierten Import:
 *   nest/einflug    = Soll eingelesen werden
 *   nest/im-flug    = Verarbeitung läuft
 *   nest/gelandet   = Erfolgreich übernommen
 *   nest/abgestürzt = Verarbeitung fehlgeschlagen
 *
 * Benötigter OAuth-Scope: gmail.modify (nicht nur readonly —
 * wir müssen Labels setzen/entfernen können).
 */
class GmailClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const API_BASE  = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /**
     * Scope gmail.modify erlaubt Lesen + Label-Änderungen.
     * Wer bisher nur gmail.readonly verbunden hat, muss neu autorisieren.
     */
    private const SCOPES = 'https://www.googleapis.com/auth/gmail.modify';

    // ── Nest-Label-Namen ─────────────────────────────────────────────────────
    public const LABEL_EINFLUG     = 'nest/einflug';
    public const LABEL_IM_FLUG     = 'nest/im-flug';
    public const LABEL_GELANDET    = 'nest/gelandet';
    public const LABEL_ABGESTUERZT = 'nest/abgestürzt';

    public const NEST_LABELS = [
        self::LABEL_EINFLUG,
        self::LABEL_IM_FLUG,
        self::LABEL_GELANDET,
        self::LABEL_ABGESTUERZT,
    ];

    /** In-Memory-Cache der Label-IDs für diesen Request. */
    private array $labelIdCache = [];

    public function __construct(private GmailSyncState $syncState) {}

    // =========================================================================
    // OAuth helpers
    // =========================================================================

    public static function authUrl(): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('services.gmail.client_id'),
            'redirect_uri'  => config('services.gmail.redirect_uri'),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    public static function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'redirect_uri'  => config('services.gmail.redirect_uri'),
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gmail token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    // =========================================================================
    // Token management
    // =========================================================================

    private function accessToken(): string
    {
        if ($this->syncState->isTokenExpired()) {
            $this->refreshToken();
        }

        return $this->syncState->getDecryptedAccessToken()
            ?? throw new \RuntimeException('No Gmail access token available.');
    }

    private function refreshToken(): void
    {
        $refreshToken = $this->syncState->getDecryptedRefreshToken();
        if (!$refreshToken) {
            throw new \RuntimeException('Kein Refresh-Token — bitte Gmail neu verbinden (gmail.modify-Berechtigung benötigt).');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gmail token refresh failed: ' . $response->body());
        }

        $data = $response->json();
        $this->syncState->setEncryptedAccessToken($data['access_token']);
        if (isset($data['expires_in'])) {
            $this->syncState->token_expires_at = now()->addSeconds($data['expires_in'] - 60);
        }
        $this->syncState->save();
    }

    // =========================================================================
    // Nachrichten abrufen
    // =========================================================================

    /**
     * Letzten N Nachrichten aus dem Posteingang (INBOX) holen.
     * Wird beim manuellen Import per Knopfdruck verwendet.
     *
     * @return array [{id: string, threadId: string}]
     */
    public function listInboxMessages(int $maxResults = 50): array
    {
        $response = $this->get('/messages', [
            'labelIds'   => 'INBOX',
            'maxResults' => $maxResults,
        ]);
        return $response['messages'] ?? [];
    }

    /**
     * Nur Nachrichten mit einem bestimmten Label-ID holen.
     * Wird beim automatisierten Import verwendet (nest/einflug).
     *
     * @return array [{id: string, threadId: string}]
     */
    public function listMessagesByLabelId(string $labelId, int $maxResults = 100): array
    {
        $response = $this->get('/messages', [
            'labelIds'   => $labelId,
            'maxResults' => $maxResults,
        ]);
        return $response['messages'] ?? [];
    }

    /**
     * Neue Nachrichten seit einem History-ID (inkrementeller Sync).
     */
    public function listHistory(string $startHistoryId, int $maxResults = 100): array
    {
        $response = $this->get('/history', [
            'startHistoryId'  => $startHistoryId,
            'historyTypes'    => 'messageAdded',
            'maxResults'      => $maxResults,
        ]);

        $messages = [];
        foreach ($response['history'] ?? [] as $item) {
            foreach ($item['messagesAdded'] ?? [] as $added) {
                $messages[] = $added['message'];
            }
        }

        // Update history ID
        if (isset($response['historyId'])) {
            $this->syncState->last_history_id = $response['historyId'];
            $this->syncState->save();
        }

        return $messages;
    }

    /**
     * Get a full message (headers + body + attachments metadata).
     */
    public function getMessage(string $id): array
    {
        return $this->get("/messages/{$id}", ['format' => 'full']);
    }

    /**
     * Download an attachment's raw data (Base64url-encoded by Gmail).
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $response = $this->get("/messages/{$messageId}/attachments/{$attachmentId}");
        $data = $response['data'] ?? '';
        // Gmail uses URL-safe Base64
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * E-Mail-Adresse des verbundenen Accounts abrufen.
     */
    public function getEmailAddress(): string
    {
        $response = Http::withToken($this->accessToken())
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');

        if (!$response->successful()) {
            throw new \RuntimeException('Profil-Abruf fehlgeschlagen: ' . $response->body());
        }

        return $response->json()['emailAddress'] ?? '';
    }

    // =========================================================================
    // Label-Verwaltung
    // =========================================================================

    /**
     * Alle Labels des Postfachs abrufen.
     *
     * @return array [{id, name, type, ...}]
     */
    public function listLabels(): array
    {
        $response = $this->get('/labels');
        return $response['labels'] ?? [];
    }

    /**
     * Alle vier Nest-Labels anlegen falls nicht vorhanden.
     * Gibt ein Array name → id zurück.
     *
     * @return array<string, string>  z.B. ['nest/einflug' => 'Label_123', ...]
     */
    public function ensureNestLabels(): array
    {
        if ($this->labelIdCache) {
            return $this->labelIdCache;
        }

        $existing = [];
        foreach ($this->listLabels() as $label) {
            $existing[$label['name']] = $label['id'];
        }

        $result = [];
        foreach (self::NEST_LABELS as $name) {
            if (isset($existing[$name])) {
                $result[$name] = $existing[$name];
            } else {
                $result[$name] = $this->createLabel($name);
                Log::info("GmailClient: Label angelegt — {$name}");
            }
        }

        $this->labelIdCache = $result;
        return $result;
    }

    /**
     * Labels einer Nachricht ändern (hinzufügen / entfernen).
     *
     * @param string[] $addLabelIds
     * @param string[] $removeLabelIds
     */
    public function modifyLabels(string $messageId, array $addLabelIds, array $removeLabelIds): void
    {
        $this->post("/messages/{$messageId}/modify", [
            'addLabelIds'    => array_values(array_filter($addLabelIds)),
            'removeLabelIds' => array_values(array_filter($removeLabelIds)),
        ]);
    }

    /**
     * Neues Gmail-Label anlegen und dessen ID zurückgeben.
     */
    private function createLabel(string $name): string
    {
        $response = $this->post('/labels', [
            'name'                  => $name,
            'labelListVisibility'   => 'labelShow',
            'messageListVisibility' => 'show',
        ]);
        return $response['id'] ?? throw new \RuntimeException("Label konnte nicht erstellt werden: {$name}");
    }

    // =========================================================================
    // HTTP-Hilfsmethoden
    // =========================================================================

    private function get(string $path, array $params = []): array
    {
        $url      = self::API_BASE . $path;
        $response = Http::withToken($this->accessToken())->get($url, $params);

        if (!$response->successful()) {
            Log::error('Gmail API GET error', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("Gmail API Fehler ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $data = []): array
    {
        $url      = self::API_BASE . $path;
        $response = Http::withToken($this->accessToken())->post($url, $data);

        if (!$response->successful()) {
            Log::error('Gmail API POST error', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("Gmail API Fehler ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
