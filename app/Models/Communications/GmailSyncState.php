<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;

class GmailSyncState extends Model
{
    public const STATUS_IDLE    = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_ERROR   = 'error';

    protected $table = 'gmail_sync_state';

    protected $fillable = [
        'company_id', 'email_address',
        'last_history_id', 'last_synced_at',
        'sync_status', 'error_message',
        'access_token', 'refresh_token', 'token_expires_at',
    ];

    protected $casts = [
        'last_synced_at'   => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function getDecryptedAccessToken(): ?string
    {
        return $this->access_token ? decrypt($this->access_token) : null;
    }

    public function getDecryptedRefreshToken(): ?string
    {
        return $this->refresh_token ? decrypt($this->refresh_token) : null;
    }

    public function setEncryptedAccessToken(string $token): void
    {
        $this->access_token = encrypt($token);
    }

    public function setEncryptedRefreshToken(string $token): void
    {
        $this->refresh_token = encrypt($token);
    }
}
