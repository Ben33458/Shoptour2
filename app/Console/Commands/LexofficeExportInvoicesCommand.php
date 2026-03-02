<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Export finalized invoices in a lexoffice-compatible CSV format.
 *
 * Output file: storage/app/exports/lexoffice_invoices_<from>_<to>.csv
 *
 * Columns:
 *   invoice_number, date, customer_name, total_gross, tax_total, currency
 *
 * Usage:
 *   php artisan kolabri:lexoffice:export-invoices --from=2024-01-01 --to=2024-12-31
 */
class LexofficeExportInvoicesCommand extends Command
{
    protected $signature = 'kolabri:lexoffice:export-invoices
                            {--from= : Start date inclusive (YYYY-MM-DD, required)}
                            {--to=   : End date inclusive   (YYYY-MM-DD, required)}';

    protected $description = 'Export finalized invoices as lexoffice-compatible CSV';

    public function handle(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');

        if (! $from || ! $to) {
            $this->error('Both --from and --to are required (YYYY-MM-DD).');
            return self::FAILURE;
        }

        // Validate
        try {
            $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $toDate   = \Carbon\Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        } catch (\Exception) {
            $this->error('Invalid date format. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        if ($fromDate->isAfter($toDate)) {
            $this->error('--from must not be after --to.');
            return self::FAILURE;
        }

        // ── Query finalized invoices ───────────────────────────────────────
        $invoices = DB::table('invoices as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('i.status', Invoice::STATUS_FINALIZED)
            ->whereBetween('i.finalized_at', [$fromDate, $toDate])
            ->select(
                'i.invoice_number',
                'i.finalized_at',
                'c.first_name',
                'c.last_name',
                'c.customer_number',
                'i.total_gross_milli',
                'i.total_tax_milli',
            )
            ->orderBy('i.invoice_number')
            ->get();

        if ($invoices->isEmpty()) {
            $this->warn("No finalized invoices found between {$from} and {$to}.");
            return self::SUCCESS;
        }

        // ── Build CSV ─────────────────────────────────────────────────────
        $fileName = "exports/lexoffice_invoices_{$from}_{$to}.csv";
        $lines    = [];

        // Header row
        $lines[] = implode(',', [
            'invoice_number',
            'date',
            'customer_name',
            'total_gross',
            'tax_total',
            'currency',
        ]);

        foreach ($invoices as $row) {
            $customerName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))
                ?: ($row->customer_number ?? '');

            $totalGross = number_format($row->total_gross_milli / 1_000_000, 2, '.', '');
            $taxTotal   = number_format($row->total_tax_milli   / 1_000_000, 2, '.', '');
            $date       = \Carbon\Carbon::parse($row->finalized_at)->toDateString();

            $lines[] = implode(',', [
                $this->csvField($row->invoice_number ?? ''),
                $date,
                $this->csvField($customerName),
                $totalGross,
                $taxTotal,
                'EUR',
            ]);
        }

        $csv = implode("\n", $lines) . "\n";

        // ── Write to storage/app/exports/ ─────────────────────────────────
        Storage::disk('local')->put($fileName, $csv);

        $fullPath = storage_path('app/' . $fileName);

        $this->info("✓ Exported {$invoices->count()} invoices to:");
        $this->line("  {$fullPath}");

        return self::SUCCESS;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Wrap a field in double quotes, escaping any internal double quotes.
     */
    private function csvField(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
