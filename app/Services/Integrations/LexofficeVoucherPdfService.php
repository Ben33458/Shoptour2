<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Admin\LexofficeVoucher;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fetches and caches Lexoffice invoice PDFs on demand.
 *
 * PDFs are stored under storage/app/lexoffice/pdfs/{voucher_number}.pdf
 * and the path is written back to lexoffice_vouchers.pdf_path.
 *
 * A cached PDF is considered fresh for 30 days; after that it is re-fetched.
 */
class LexofficeVoucherPdfService
{
    private const CACHE_DAYS = 30;
    private const DISK       = 'local';

    public function __construct(
        private readonly LexofficeClient $client,
    ) {}

    /**
     * Return a local path to the PDF, fetching it from Lexoffice if necessary.
     *
     * @throws RuntimeException if the voucher has no Lexoffice ID or the API call fails
     */
    public function getOrFetch(LexofficeVoucher $voucher): string
    {
        // Already cached and fresh?
        if ($voucher->pdf_path
            && $voucher->pdf_fetched_at
            && $voucher->pdf_fetched_at->diffInDays(now()) < self::CACHE_DAYS
            && Storage::disk(self::DISK)->exists($voucher->pdf_path)
        ) {
            return $voucher->pdf_path;
        }

        if (empty($voucher->lexoffice_voucher_id)) {
            throw new RuntimeException('Voucher hat keine Lexoffice-ID — kein PDF verfügbar.');
        }

        $pdf      = $this->client->getVoucherDocument($voucher->lexoffice_voucher_id);
        $filename = $this->buildFilename($voucher);

        Storage::disk(self::DISK)->put($filename, $pdf);

        $voucher->update([
            'pdf_path'       => $filename,
            'pdf_fetched_at' => now(),
        ]);

        return $filename;
    }

    /**
     * Return the absolute filesystem path for serving the PDF,
     * fetching it first if needed.
     */
    public function absolutePath(LexofficeVoucher $voucher): string
    {
        $relative = $this->getOrFetch($voucher);
        return Storage::disk(self::DISK)->path($relative);
    }

    private function buildFilename(LexofficeVoucher $voucher): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $voucher->voucher_number ?? $voucher->lexoffice_voucher_id);
        return "lexoffice/pdfs/{$safe}.pdf";
    }
}
