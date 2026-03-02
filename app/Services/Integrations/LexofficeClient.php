<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP wrapper around the Lexoffice v1 REST API.
 *
 * All methods throw RuntimeException on non-2xx responses.
 * Callers (LexofficeSync) are responsible for catching and recording errors.
 */
class LexofficeClient
{
    private string $baseUri;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUri = (string) config('services.lexoffice.base_uri', 'https://api.lexoffice.io/v1');
        $this->apiKey  = (string) config('services.lexoffice.api_key', '');
    }

    /**
     * Create or update a contact (customer) in Lexoffice.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function pushContact(array $payload): array
    {
        $response = $this->http()->post('/contacts', $payload);
        $this->assertSuccess($response, 'contacts');

        return $response->json();
    }

    /**
     * Create a voucher (invoice) in Lexoffice.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function pushVoucher(array $payload): array
    {
        $response = $this->http()->post('/vouchers', $payload);
        $this->assertSuccess($response, 'vouchers');

        return $response->json();
    }

    // -------------------------------------------------------------------------

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUri)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson();
    }

    private function assertSuccess(Response $response, string $endpoint): void
    {
        if ($response->failed()) {
            throw new RuntimeException(
                "Lexoffice API error on {$endpoint}: HTTP {$response->status()} \xe2\x80\x94 {$response->body()}"
            );
        }
    }
}
