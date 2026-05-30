<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver\DriverUpload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImmichService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.immich.url', ''), '/');
        $this->apiKey  = (string) config('services.immich.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * Upload a DriverUpload to Immich, set tags, add to monthly album,
     * and persist the immich_asset_id + immich_status back to the model.
     */
    public function pushDriverUpload(DriverUpload $upload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $localPath = Storage::disk('local')->path($upload->file_path);
        if (! file_exists($localPath)) {
            Log::warning("ImmichService: file not found for upload {$upload->id}: {$localPath}");
            return;
        }

        $assetId = $this->uploadAsset($localPath, $upload->original_name ?? basename($localPath), $upload->created_at);
        if ($assetId === null) {
            return;
        }

        $albumName = 'Fahrer-Uploads / ' . $upload->created_at->format('Y-m');
        $albumId   = $this->createOrFindAlbum($albumName);
        if ($albumId !== null) {
            $this->addAssetToAlbum($assetId, $albumId);
        }

        $tags = $this->buildTags($upload);
        $this->setTags($assetId, $tags);

        $upload->update([
            'immich_asset_id' => $assetId,
            'immich_status'   => 'offen',
        ]);

        $this->setDescription($assetId, $this->buildDescription($upload));
    }

    /**
     * Change the workflow status of an asset both in the DB and in Immich tags.
     * Also refreshes the description so the action links reflect the new status.
     */
    public function updateStatus(DriverUpload $upload, string $newStatus): void
    {
        $upload->update(['immich_status' => $newStatus]);

        if (! $this->isConfigured() || $upload->immich_asset_id === null) {
            return;
        }

        // Update status tag
        $current  = $this->getAssetTags($upload->immich_asset_id);
        $filtered = array_values(array_filter($current, fn($t) => ! str_starts_with($t, 'status/')));
        $filtered[] = 'status/' . $newStatus;
        $this->setTags($upload->immich_asset_id, $filtered);

        // Refresh description so action links show the current status
        $this->setDescription($upload->immich_asset_id, $this->buildDescription($upload));
    }

    /**
     * Build a plain-text description for an Immich asset.
     * Includes direct action links so the user can change the status from
     * within Immich's info panel (I key → description field).
     */
    public function buildDescription(DriverUpload $upload): string
    {
        $baseUrl   = rtrim((string) config('app.url'), '/');
        $statusMap = ['offen' => 'Offen', 'geprueft' => 'Geprüft', 'abgerechnet' => 'Abgerechnet'];
        $current   = $statusMap[$upload->immich_status ?? 'offen'] ?? ($upload->immich_status ?? '–');

        $typMap = [
            'delivery_note'     => 'Lieferschein',
            'proof_of_delivery' => 'Liefernachweis',
            'other'             => 'Sonstiges',
        ];
        $typ = $typMap[$upload->upload_type ?? ''] ?? 'Upload';

        $lines   = [];
        $lines[] = "Fahrer-Upload #{$upload->id} · {$typ}";
        $lines[] = "Status: {$current}";
        $lines[] = '';

        // Action links
        foreach (['geprueft' => '✓ Geprüft', 'abgerechnet' => '€ Abgerechnet'] as $s => $label) {
            if ($upload->immich_status !== $s) {
                $lines[] = "{$label}: {$baseUrl}/admin/driver-uploads/{$upload->id}/mark/{$s}";
            }
        }

        $lines[] = '';
        $lines[] = "Admin: {$baseUrl}/admin/driver-uploads";

        return implode("\n", $lines);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function uploadAsset(string $localPath, string $originalName, \DateTimeInterface $createdAt): ?string
    {
        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->attach('assetData', fopen($localPath, 'r'), $originalName)
            ->post($this->baseUrl . '/api/assets', [
                'deviceAssetId' => 'shoptour2-' . md5($localPath),
                'deviceId'      => 'shoptour2-laravel',
                'fileCreatedAt' => $createdAt->format('c'),
                'fileModifiedAt' => $createdAt->format('c'),
            ]);

        if (! $response->successful()) {
            Log::error('ImmichService upload failed: ' . $response->status() . ' ' . $response->body());
            return null;
        }

        return $response->json('id');
    }

    private function createOrFindAlbum(string $name): ?string
    {
        // List existing albums and find by name
        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->get($this->baseUrl . '/api/albums');

        if ($response->successful()) {
            foreach ($response->json() as $album) {
                if ($album['albumName'] === $name) {
                    return $album['id'];
                }
            }
        }

        // Create new album
        $create = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->post($this->baseUrl . '/api/albums', ['albumName' => $name]);

        if ($create->successful()) {
            return $create->json('id');
        }

        Log::error('ImmichService createAlbum failed: ' . $create->body());
        return null;
    }

    private function addAssetToAlbum(string $assetId, string $albumId): void
    {
        Http::withHeaders(['x-api-key' => $this->apiKey])
            ->put($this->baseUrl . '/api/albums/' . $albumId . '/assets', [
                'ids' => [$assetId],
            ]);
    }

    public function setDescription(string $assetId, string $description): void
    {
        Http::withHeaders(['x-api-key' => $this->apiKey])
            ->put($this->baseUrl . '/api/assets/' . $assetId, [
                'description' => $description,
            ]);
    }

    private function getAssetTags(string $assetId): array
    {
        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->get($this->baseUrl . '/api/assets/' . $assetId);

        if (! $response->successful()) {
            return [];
        }

        return array_map(fn($t) => $t['value'], $response->json('tags') ?? []);
    }

    private function setTags(string $assetId, array $tagValues): void
    {
        // Ensure all tags exist
        $tagIds = [];
        foreach ($tagValues as $value) {
            $tagId = $this->createOrFindTag($value);
            if ($tagId !== null) {
                $tagIds[] = $tagId;
            }
        }

        if (empty($tagIds)) {
            return;
        }

        Http::withHeaders(['x-api-key' => $this->apiKey])
            ->put($this->baseUrl . '/api/tags/assets', [
                'tagIds'   => $tagIds,
                'assetIds' => [$assetId],
            ]);
    }

    private function createOrFindTag(string $value): ?string
    {
        // Try to create (returns existing if already exists via upsert)
        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->post($this->baseUrl . '/api/tags', ['name' => $value]);

        if ($response->successful()) {
            return $response->json('id');
        }

        // If 400/conflict, search for it
        $list = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->get($this->baseUrl . '/api/tags');
        if ($list->successful()) {
            foreach ($list->json() as $tag) {
                if ($tag['value'] === $value || $tag['name'] === $value) {
                    return $tag['id'];
                }
            }
        }

        return null;
    }

    private function buildTags(DriverUpload $upload): array
    {
        $tags = [];

        $tags[] = match ($upload->upload_type) {
            DriverUpload::TYPE_DELIVERY_NOTE     => 'typ/lieferschein',
            DriverUpload::TYPE_PROOF_OF_DELIVERY => 'typ/lieferfoto',
            default                              => 'typ/aufgabe',
        };

        $tags[] = 'status/offen';

        if ($upload->tour_stop_id !== null) {
            $tags[] = 'stop/' . $upload->tour_stop_id;

            // Resolve customer via tour_stop → order → customer
            try {
                $customerId = \DB::table('driver_uploads as du')
                    ->join('tour_stops as ts', 'du.tour_stop_id', '=', 'ts.id')
                    ->join('orders as o', 'ts.order_id', '=', 'o.id')
                    ->where('du.id', $upload->id)
                    ->value('o.customer_id');

                if ($customerId !== null) {
                    $tags[] = 'kunde/' . $customerId;
                }
            } catch (\Throwable) {
                // Non-critical, skip
            }
        }

        if ($upload->order_id !== null) {
            $tags[] = 'bestellung/' . $upload->order_id;
        }

        return $tags;
    }
}
