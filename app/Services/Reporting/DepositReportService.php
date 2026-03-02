<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Admin\Invoice;
use App\Models\Admin\InvoiceItem;

/**
 * WP-16 - Deposit / Pfandkonto report.
 *
 * Tracks deposit cash flows from finalized invoices:
 *   Pfand rein  - deposit lines (customer pays deposit when goods are delivered)
 *   Pfand raus  - adjustment credit lines (leergut / bruch returns, negative amount)
 *   Saldo       - net deposit balance in the period
 */
class DepositReportService
{
    /**
     * @return array{
     *   pfand_rein_milli: int,
     *   pfand_raus_milli: int,
     *   saldo_milli: int,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function summary(int $companyId, string $from, string $to): array
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->where('status', Invoice::STATUS_FINALIZED)
            ->whereBetween('finalized_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('items')
            ->orderBy('finalized_at')
            ->get();

        $pfandReinMilli = 0;
        $pfandRausMilli = 0;
        $rows           = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                if ($item->line_type === InvoiceItem::TYPE_DEPOSIT) {
                    $pfandReinMilli += $item->line_total_gross_milli;
                    $rows[] = [
                        'invoice_number' => $invoice->invoice_number,
                        'finalized_at'   => $invoice->finalized_at?->format('d.m.Y') ?? '-',
                        'type'           => 'pfand_rein',
                        'description'    => $item->description,
                        'amount_milli'   => $item->line_total_gross_milli,
                    ];
                } elseif ($item->line_type === InvoiceItem::TYPE_ADJUSTMENT
                    && $item->line_total_gross_milli < 0
                ) {
                    // Leergut / Bruch credits are negative adjustments
                    $pfandRausMilli += abs($item->line_total_gross_milli);
                    $rows[] = [
                        'invoice_number' => $invoice->invoice_number,
                        'finalized_at'   => $invoice->finalized_at?->format('d.m.Y') ?? '-',
                        'type'           => 'pfand_raus',
                        'description'    => $item->description,
                        'amount_milli'   => $item->line_total_gross_milli,
                    ];
                }
            }
        }

        return [
            'pfand_rein_milli' => $pfandReinMilli,
            'pfand_raus_milli' => $pfandRausMilli,
            'saldo_milli'      => $pfandReinMilli - $pfandRausMilli,
            'rows'             => $rows,
        ];
    }
}
