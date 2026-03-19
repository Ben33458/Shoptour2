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

    // ── Pull methods ──────────────────────────────────────────────────────────

    /**
     * List contacts filtered by role ('customer' or 'vendor').
     * Returns the raw page payload: { content: [...], totalPages: N, ... }
     *
     * @return array<string, mixed>
     */
    public function listContacts(string $role = 'customer', int $page = 0, int $size = 100): array
    {
        $response = $this->http()->get('/contacts', [
            'roles' => $role,
            'page'  => $page,
            'size'  => $size,
        ]);
        $this->assertSuccess($response, 'contacts');

        return $response->json();
    }

    /**
     * Fetch a single contact by its Lexoffice UUID.
     *
     * @return array<string, mixed>
     */
    public function getContact(string $id): array
    {
        $response = $this->http()->get("/contacts/{$id}");
        $this->assertSuccess($response, "contacts/{$id}");

        return $response->json();
    }

    /**
     * List vouchers (invoices) with optional filters.
     * Useful filters: voucherType=salesinvoice, voucherStatus=open|paid|voided
     *
     * @param  array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listVouchers(array $filters = [], int $page = 0, int $size = 100): array
    {
        $response = $this->http()->get('/voucherlist', array_merge($filters, [
            'page' => $page,
            'size' => $size,
        ]));
        $this->assertSuccess($response, 'voucherlist');

        return $response->json();
    }

    /**
     * Fetch a single voucher by its Lexoffice UUID (includes payment status).
     *
     * @return array<string, mixed>
     */
    public function getVoucher(string $id): array
    {
        $response = $this->http()->get("/vouchers/{$id}");
        $this->assertSuccess($response, "vouchers/{$id}");

        return $response->json();
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::baseUrl($this->baseUri)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson();

        // Allow disabling SSL verification in local dev on Windows
        // (PHP often lacks a CA bundle). Never disable in production!
        if (! config('services.lexoffice.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        return $request;
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
