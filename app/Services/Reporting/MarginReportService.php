<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Admin\Invoice;
use App\Models\Admin\InvoiceItem;

/**
 * WP-16 - Margin / Deckungsbeitrag report.
 *
 * Computes revenue (net) minus cost of goods (cost_milli snapshot) for all
 * product lines on finalized invoices in the given date range.
 *
 * Lines without a cost_milli snapshot are included with margin = null.
 */
class MarginReportService
{
    /**
     * @return array{
     *   total_revenue_net_milli: int,
     *   total_cost_milli: int,
     *   total_margin_milli: int,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function summary(int $companyId, string $from, string $to): array
    {
        $items = InvoiceItem::where('line_type', InvoiceItem::TYPE_PRODUCT)
            ->whereHas('invoice', static function ($q) use ($companyId, $from, $to): void {
                $q->where('company_id', $companyId)
                  ->where('status', Invoice::STATUS_FINALIZED)
                  ->whereBetween('finalized_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            })
            ->with('invoice:id,invoice_number,finalized_at')
            ->get();

        $totalRevenueMilli = 0;
        $totalCostMilli    = 0;
        $rows              = [];

        foreach ($items as $item) {
            $lineCost   = $item->cost_milli !== null
                ? (int) round($item->qty * $item->cost_milli)
                : null;
            $lineMargin = $lineCost !== null
                ? $item->line_total_net_milli - $lineCost
                : null;

            $totalRevenueMilli += $item->line_total_net_milli;
            if ($lineCost !== null) {
                $totalCostMilli += $lineCost;
            }

            $rows[] = [
                'invoice_number'     => $item->invoice?->invoice_number,
                'description'        => $item->description,
                'qty'                => $item->qty,
                'revenue_net_milli'  => $item->line_total_net_milli,
                'cost_milli'         => $lineCost,
                'margin_milli'       => $lineMargin,
            ];
        }

        return [
            'total_revenue_net_milli' => $totalRevenueMilli,
            'total_cost_milli'        => $totalCostMilli,
            'total_margin_milli'      => $totalRevenueMilli - $totalCostMilli,
            'rows'                    => $rows,
        ];
    }
}
