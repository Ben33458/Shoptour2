<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Admin\Invoice;
use Illuminate\Support\Collection;

/**
 * WP-16 - Revenue report (Umsatzbericht).
 *
 * Aggregates finalized invoices for a given company and date range.
 * Returns totals (gross, net, tax, deposit) and a row-per-invoice detail list.
 */
class RevenueReportService
{
    /**
     * @return array{
     *   total_gross_milli: int,
     *   total_net_milli: int,
     *   total_tax_milli: int,
     *   total_deposit_milli: int,
     *   invoice_count: int,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function summary(int $companyId, string $from, string $to): array
    {
        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::where('company_id', $companyId)
            ->where('status', Invoice::STATUS_FINALIZED)
            ->whereBetween('finalized_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('finalized_at')
            ->get();

        $rows = $invoices->map(static fn (Invoice $inv): array => [
            'invoice_number'      => $inv->invoice_number,
            'finalized_at'        => $inv->finalized_at?->format('d.m.Y') ?? '-',
            'total_gross_milli'   => $inv->total_gross_milli,
            'total_net_milli'     => $inv->total_net_milli,
            'total_tax_milli'     => $inv->total_tax_milli,
            'total_deposit_milli' => $inv->total_deposit_milli,
        ])->values()->all();

        return [
            'total_gross_milli'   => (int) $invoices->sum('total_gross_milli'),
            'total_net_milli'     => (int) $invoices->sum('total_net_milli'),
            'total_tax_milli'     => (int) $invoices->sum('total_tax_milli'),
            'total_deposit_milli' => (int) $invoices->sum('total_deposit_milli'),
            'invoice_count'       => $invoices->count(),
            'rows'                => $rows,
        ];
    }
}
