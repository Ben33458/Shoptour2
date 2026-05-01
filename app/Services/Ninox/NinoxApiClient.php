<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use App\Models\Pricing\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic Ninox REST API client.
 *
 * Docs: https://docs.ninox.com/en/api
 */
class NinoxApiClient
{
    private string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $teamId,
        private readonly string $dbId,
    ) {
        $this->baseUrl = "https://api.ninox.com/v1/teams/{$this->teamId}/databases/{$this->dbId}";
    }

    /**
     * Create a client for a specific database.
     *
     * @param  string|null  $dbId  'kehr' | 'alt' | explicit DB ID — defaults to kehr
     */
    public static function make(?string $dbId = null): static
    {
        $key    = AppSetting::get('ninox.api_key')   ?: (config('services.ninox.api_key')   ?? '');
        $teamId = AppSetting::get('ninox.team_id')  ?: (config('services.ninox.team_id')  ?? '');

        $resolvedDbId = match ($dbId) {
            'kehr'  => AppSetting::get('ninox.db_id_kehr') ?: config('services.ninox.db_id_kehr'),
            'alt'   => AppSetting::get('ninox.db_id_alt')  ?: config('services.ninox.db_id_alt'),
            null    => AppSetting::get('ninox.db_id_kehr') ?: config('services.ninox.db_id_kehr'),
            default => $dbId,
        };

        if (! $key || ! $teamId || ! $resolvedDbId) {
            throw new RuntimeException('Ninox API nicht konfiguriert (NINOX_API_KEY, NINOX_TEAM_ID, NINOX_DB_ID_KEHR/ALT fehlen).');
        }

        return new static($key, $teamId, $resolvedDbId);
    }

    // ── Tables ────────────────────────────────────────────────────────────────

    /**
     * List all tables in the database.
     *
     * @return array<array{id: string, name: string}>
     */
    public function getTables(): array
    {
        $response = $this->get('/tables');
        return $response;
    }

    // ── Records ───────────────────────────────────────────────────────────────

    /**
     * Get records from a table.
     * Ninox API uses 0-based pagination: page=0 → first page, page=1 → second page, …
     */
    public function getRecords(string $tableId, int $page = 0, int $perPage = 100): array
    {
        return $this->get("/tables/{$tableId}/records", [
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Get ALL records from a table (iterates all pages).
     * Ninox API is 0-based: start at page=0.
     */
    public function getAllRecords(string $tableId, int $perPage = 100): array
    {
        $all  = [];
        $page = 0;  // Ninox API is 0-based

        do {
            $batch = $this->getRecords($tableId, $page, $perPage);
            $all   = array_merge($all, $batch);
            $page++;
        } while (count($batch) === $perPage);

        return $all;
    }

    /**
     * Get a single record.
     */
    public function getRecord(string $tableId, string $recordId): array
    {
        return $this->get("/tables/{$tableId}/records/{$recordId}");
    }

    // ── HTTP helper ───────────────────────────────────────────────────────────

    private function get(string $path, array $query = []): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->accept('application/json')
                ->timeout(30)
                ->get($this->baseUrl . $path, $query);

            if ($response->failed()) {
                throw new RuntimeException(
                    "Ninox API Fehler [{$response->status()}]: {$response->body()}"
                );
            }

            $data = $response->json();

            // Ninox wraps records in an array or returns them directly
            if (is_array($data)) {
                return $data;
            }

            return [];
        } catch (ConnectionException $e) {
            throw new RuntimeException("Ninox API nicht erreichbar: {$e->getMessage()}", 0, $e);
        }
    }
}
