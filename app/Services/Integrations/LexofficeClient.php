<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Pricing\AppSetting;
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
        $this->apiKey  = AppSetting::get('lexoffice.api_key') ?: (string) config('services.lexoffice.api_key', '');
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
        $response = $this->get('/contacts', ['roles' => $role, 'page' => $page, 'size' => $size]);
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
        $response = $this->get("/contacts/{$id}");
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
        $response = $this->get('/voucherlist', array_merge($filters, ['page' => $page, 'size' => $size]));
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
        $response = $this->get("/vouchers/{$id}");
        $this->assertSuccess($response, "vouchers/{$id}");
        return $response->json();
    }

    /**
     * Download the rendered PDF for a voucher.
     * Returns raw binary content (application/pdf).
     *
     * Lexoffice stores file IDs in the voucher's `files` array.
     * Two-step: GET /vouchers/{id} → extract fileId → GET /files/{fileId}.
     */
    public function getVoucherDocument(string $voucherId): string
    {
        // Step 1: fetch voucher to get file ID (uses built-in rate-limit delay)
        $voucher = $this->getVoucher($voucherId);
        $files   = $voucher['files'] ?? [];

        if (empty($files)) {
            throw new RuntimeException(
                "Voucher {$voucherId} hat kein Dokument (files-Array leer oder nicht vorhanden)."
            );
        }

        $fileId = $files[0];

        // Step 2: download the PDF file
        usleep(600_000);

        $request = Http::baseUrl($this->baseUri)
            ->withToken($this->apiKey)
            ->withHeaders(['Accept' => 'application/pdf']);

        if (! config('services.lexoffice.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get("/files/{$fileId}");

        if ($response->failed()) {
            throw new RuntimeException(
                "Lexoffice PDF error for voucher {$voucherId} (file {$fileId}): HTTP {$response->status()} — {$response->body()}"
            );
        }

        return $response->body();
    }

    // ── Additional pull methods ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function listArticles(int $page = 0, int $size = 100): array
    {
        // API requires size >= 25
        $size     = max(25, $size);
        $response = $this->get('/articles', ['page' => $page, 'size' => $size]);
        $this->assertSuccess($response, 'articles');
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function listPaymentConditions(int $page = 0, int $size = 100): array
    {
        $response = $this->get('/payment-conditions', ['page' => $page, 'size' => $size]);
        $this->assertSuccess($response, 'payment-conditions');
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function listPostingCategories(int $page = 0, int $size = 100): array
    {
        $response = $this->get('/posting-categories', ['page' => $page, 'size' => $size]);
        $this->assertSuccess($response, 'posting-categories');
        return $response->json();
    }

    /** @return array<string, mixed> */
    public function listPrintLayouts(int $page = 0, int $size = 100): array
    {
        $response = $this->get('/print-layouts', ['page' => $page, 'size' => $size]);
        $this->assertSuccess($response, 'print-layouts');
        return $response->json();
    }

    /**
     * Fetch payment details for a single voucher (Eingangs-/Ausgangszahlungen).
     * Returns openAmount, paymentStatus and an array of individual payment records.
     *
     * @return array<string, mixed>
     */
    public function getPayments(string $voucherId): array
    {
        $response = $this->get("/payments/{$voucherId}");
        $this->assertSuccess($response, "payments/{$voucherId}");
        return $response->json() ?? [];
    }

    /** @return array<string, mixed> */
    public function listRecurringTemplates(int $page = 0, int $size = 100): array
    {
        $response = $this->get('/recurring-templates', ['page' => $page, 'size' => $size]);
        $this->assertSuccess($response, 'recurring-templates');
        return $response->json();
    }

    /** @return array<int, mixed> */
    public function listCountries(): array
    {
        $response = $this->get('/countries');
        $this->assertSuccess($response, 'countries');
        return $response->json() ?? [];
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

    /**
     * Execute an HTTP GET with automatic retry on 429 (rate limit).
     * Lexoffice allows max 2 requests per second.
     *
     * @param  array<string, mixed>  $query
     * @return Response
     */
    private function get(string $endpoint, array $query = []): Response
    {
        $delay = 600_000; // 600 ms → stays safely under 2 req/s

        while (true) {
            usleep($delay);
            $response = $this->http()->get($endpoint, $query);

            if ($response->status() !== 429) {
                return $response;
            }

            // Exponential backoff on rate limit, cap at 10 s
            $delay = min((int) ($delay * 2), 10_000_000);
        }
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
