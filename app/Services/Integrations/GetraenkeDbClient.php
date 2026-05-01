<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Pricing\AppSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GetraenkeDbClient
{
    public function __construct(private readonly PendingRequest $http) {}

    public static function make(): self
    {
        $http = Http::baseUrl(AppSetting::get('getraenkedb.api_url') ?: config('services.getraenkedb.url'))
            ->withToken(AppSetting::get('getraenkedb.api_key') ?: config('services.getraenkedb.key'))
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(30);

        return new self($http);
    }

    private const CACHE_TTL = 86400; // 24 h

    /**
     * Search products by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchProducts(string $query): array
    {
        $key = 'gdb_search_' . md5($query);

        return Cache::remember($key, self::CACHE_TTL, function () use ($query): array {
            try {
                $response = $this->http->get('products', ['q' => $query]);
                $response->throw();

                return $response->json() ?? [];
            } catch (RequestException $e) {
                Log::warning('GetraenkeDbClient::searchProducts failed', [
                    'query'  => $query,
                    'status' => $e->response?->status(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get a single product family by slug.
     *
     * @return array<string, mixed>
     */
    public function getProduct(string $slug): array
    {
        $key = 'gdb_product_' . md5($slug);

        return Cache::remember($key, self::CACHE_TTL, function () use ($slug): array {
            try {
                $response = $this->http->get("products/{$slug}");
                $response->throw();

                return $response->json() ?? [];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning('GetraenkeDbClient::getProduct connection failed', ['slug' => $slug, 'err' => $e->getMessage()]);

                return [];
            } catch (RequestException $e) {
                Log::warning('GetraenkeDbClient::getProduct failed', ['slug' => $slug, 'status' => $e->response?->status()]);

                return [];
            }
        });
    }

    /**
     * Lookup by GTIN. Returns combined product_family + trade_item or [].
     * Result is cached for 24 h (GTIN→product mapping rarely changes).
     *
     * @return array<string, mixed>
     */
    public function getProductByGtin(string $gtin): array
    {
        $key = 'gdb_gtin_' . $gtin;

        return Cache::remember($key, self::CACHE_TTL, function () use ($gtin): array {
            $response = $this->http->get("gtin/{$gtin}");

            if ($response->status() === 404) {
                return [];
            }

            try {
                $response->throw();
            } catch (RequestException $e) {
                Log::warning('GetraenkeDbClient::getProductByGtin failed', [
                    'gtin'   => $gtin,
                    'status' => $e->response?->status(),
                ]);

                return [];
            }

            return $response->json() ?? [];
        });
    }

    /**
     * Build the full URL to an image path relative to the getraenkeDB base.
     */
    public function getImageUrl(string $imagePath): string
    {
        $base = rtrim((string) config('services.getraenkedb.url'), '/');
        // Remove /api/v1 suffix to get the storage base
        $storageBase = preg_replace('#/api/v\d+$#', '', $base);

        return $storageBase . '/' . ltrim($imagePath, '/');
    }

    /**
     * Download an image from a URL and store it at $destPath on the public disk.
     */
    public function downloadImage(string $url, string $destPath): bool
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return false;
            }

            Storage::disk('public')->put($destPath, $response->body());

            return true;
        } catch (\Throwable $e) {
            Log::warning('GetraenkeDbClient::downloadImage failed', [
                'url'  => $url,
                'dest' => $destPath,
                'err'  => $e->getMessage(),
            ]);

            return false;
        }
    }
}
