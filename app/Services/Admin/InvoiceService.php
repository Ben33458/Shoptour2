<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\Invoice;
use App\Models\Admin\InvoiceItem;
use App\Models\Admin\OrderAdjustment;
use App\Models\Orders\Order;
use App\Models\Pricing\AppSetting;
use App\Models\Supplier\SupplierProduct;
use App\Services\Integrations\LexofficeSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generates, recalculates and finalizes invoices for orders.
 *
 * Draft lifecycle:
 *   generateDraft(order) → creates or replaces invoice + invoice_items from
 *   delivered quantities (from order_item_fulfillments) and order_adjustments.
 *
 * Finalize:
 *   finalizeInvoice(invoice) → assigns invoice_number, locks status, generates PDF.
 */
class InvoiceService
{
    private const INVOICE_PREFIX = 'RE';

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Create or fully recalculate the draft invoice for the given order.
     * If a draft invoice already exists it is replaced (items deleted, totals recomputed).
     * Finalized invoices cannot be re-drafted.
     *
     * @throws RuntimeException if the invoice is already finalized
     */
    public function generateDraft(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::where('order_id', $order->id)->first();

            if ($invoice !== null && $invoice->isFinalized()) {
                throw new RuntimeException(
                    "Rechnung #{$invoice->id} ist bereits finalisiert und kann nicht neu berechnet werden."
                );
            }

            // (Re)create the invoice shell
            $invoice ??= new Invoice(['order_id' => $order->id, 'status' => Invoice::STATUS_DRAFT]);

            // Denormalize company_id for reporting (WP-16)
            $invoice->company_id = $order->company_id;

            // Wipe existing line items before rebuilding
            if ($invoice->exists) {
                $invoice->items()->delete();
            }

            $invoice->status = Invoice::STATUS_DRAFT;
            $invoice->save();

            // ----------------------------------------------------------------
            // Build line items from order items (using delivered qty if available)
            // ----------------------------------------------------------------
            $order->load(['items', 'tourStop.itemFulfillments']);

            // Pre-load purchase prices for all products on this order (WP-16 cost_milli)
            $productIds    = $order->items->pluck('product_id')->filter()->unique()->values();
            $purchasePrices = SupplierProduct::whereIn('product_id', $productIds)
                ->where('active', true)
                ->orderByDesc('id') // most recent record wins if multiple
                ->get()
                ->keyBy('product_id')
                ->map(static fn (SupplierProduct $sp): int => $sp->purchase_price_milli);

            $totalNetMilli   = 0;
            $totalGrossMilli = 0;
            $totalTaxMilli   = 0;
            $totalDepositMilli = 0;

            foreach ($order->items as $orderItem) {
                // Use delivered_qty from fulfillment scoreboard if it exists,
                // otherwise fall back to ordered qty
                $fulfillment  = $order->tourStop?->itemFulfillments
                    ->firstWhere('order_item_id', $orderItem->id);
                $qty = $fulfillment !== null
                    ? (float) $fulfillment->delivered_qty
                    : (float) $orderItem->qty;

                if ($qty <= 0) {
                    continue; // Skip fully-undelivered items
                }

                $lineTotalNetMilli   = (int) round($qty * $orderItem->unit_price_net_milli);
                $lineTotalGrossMilli = (int) round($qty * $orderItem->unit_price_gross_milli);
                $lineTaxMilli        = $lineTotalGrossMilli - $lineTotalNetMilli;

                InvoiceItem::create([
                    'invoice_id'              => $invoice->id,
                    'order_item_id'           => $orderItem->id,
                    'line_type'               => InvoiceItem::TYPE_PRODUCT,
                    'description'             => $orderItem->product_name_snapshot
                        . ' [' . $orderItem->artikelnummer_snapshot . ']',
                    'qty'                     => $qty,
                    'unit_price_net_milli'    => $orderItem->unit_price_net_milli,
                    'unit_price_gross_milli'  => $orderItem->unit_price_gross_milli,
                    'tax_rate_basis_points'   => $orderItem->tax_rate_basis_points ?? 1_900,
                    'line_total_net_milli'    => $lineTotalNetMilli,
                    'line_total_gross_milli'  => $lineTotalGrossMilli,
                    // WP-16: snapshot unit purchase price for margin reporting
                    'cost_milli'              => $purchasePrices->get($orderItem->product_id),
                ]);

                $totalNetMilli   += $lineTotalNetMilli;
                $totalGrossMilli += $lineTotalGrossMilli;
                $totalTaxMilli   += $lineTaxMilli;
            }

            // Deposit line (pfand)
            $depositMilli = (int) $order->total_pfand_brutto_milli;
            if ($depositMilli > 0) {
                InvoiceItem::create([
                    'invoice_id'              => $invoice->id,
                    'line_type'               => InvoiceItem::TYPE_DEPOSIT,
                    'description'             => 'Pfand (Leihgebinde)',
                    'qty'                     => 1,
                    'unit_price_net_milli'    => $depositMilli,
                    'unit_price_gross_milli'  => $depositMilli,
                    'tax_rate_basis_points'   => 0,
                    'line_total_net_milli'    => $depositMilli,
                    'line_total_gross_milli'  => $depositMilli,
                ]);
                $totalDepositMilli = $depositMilli;
                $totalGrossMilli  += $depositMilli;
            }

            // ----------------------------------------------------------------
            // Adjustments (leergut / bruch)
            // ----------------------------------------------------------------
            $adjustments      = OrderAdjustment::where('order_id', $order->id)->get();
            $totalAdjMilli    = 0;

            foreach ($adjustments as $adj) {
                InvoiceItem::create([
                    'invoice_id'              => $invoice->id,
                    'adjustment_id'           => $adj->id,
                    'line_type'               => InvoiceItem::TYPE_ADJUSTMENT,
                    'description'             => ucfirst($adj->adjustment_type) . ': '
                        . ($adj->reference_label ?? 'Pos. ' . $adj->id)
                        . ($adj->note ? ' — ' . $adj->note : ''),
                    'qty'                     => (float) $adj->qty,
                    'unit_price_net_milli'    => $adj->amount_milli,
                    'unit_price_gross_milli'  => $adj->amount_milli,
                    'tax_rate_basis_points'   => 0,
                    'line_total_net_milli'    => $adj->amount_milli * $adj->qty,
                    'line_total_gross_milli'  => $adj->amount_milli * $adj->qty,
                ]);
                $totalAdjMilli += $adj->amount_milli * $adj->qty;
            }

            $totalGrossMilli += $totalAdjMilli;

            // ----------------------------------------------------------------
            // Update invoice totals
            // ----------------------------------------------------------------
            $invoice->update([
                'total_net_milli'         => $totalNetMilli,
                'total_gross_milli'       => $totalGrossMilli,
                'total_tax_milli'         => $totalTaxMilli,
                'total_adjustments_milli' => $totalAdjMilli,
                'total_deposit_milli'     => $totalDepositMilli,
            ]);

            $invoice->load('items');

            return $invoice;
        });
    }

    /**
     * Finalize a draft invoice: assign invoice number, generate PDF, lock.
     *
     * @throws RuntimeException if already finalized
     */
    public function finalizeInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->isFinalized()) {
            throw new RuntimeException("Rechnung #{$invoice->id} ist bereits finalisiert.");
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->load('items', 'order.customer');

            // Assign next invoice number
            $year       = now()->year;
            $lastNumber = Invoice::where('invoice_number', 'LIKE', self::INVOICE_PREFIX . "-{$year}-%")
                ->whereNotNull('invoice_number')
                ->count();
            $seq        = $lastNumber + 1;
            $invoiceNum = sprintf('%s-%d-%05d', self::INVOICE_PREFIX, $year, $seq);

            // Generate PDF using DomPDF + Blade template (WP-19)
            $invoice->load('items', 'order.customer');
            $company    = \App\Models\Company::find($invoice->company_id);
            $pdfContent = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'company' => $company])
                ->setPaper('a4', 'portrait')
                ->output();
            $pdfPath    = 'invoices/' . $invoiceNum . '.pdf';
            Storage::disk('local')->put($pdfPath, $pdfContent);

            // Lock invoice
            $invoice->update([
                'invoice_number' => $invoiceNum,
                'status'         => Invoice::STATUS_FINALIZED,
                'pdf_path'       => $pdfPath,
                'finalized_at'   => now(),
            ]);

            $invoice->refresh();

            // Audit log
            $this->auditLog->log('invoice.finalized', $invoice, [
                'invoice_number' => $invoiceNum,
                'order_id'       => $invoice->order_id,
                'total_gross'    => $invoice->total_gross_milli,
            ]);

            // WP-17: Push to Lexoffice if integration is enabled.
            // Non-blocking — finalization succeeds even if the push fails.
            if (AppSetting::get('lexoffice.enabled', '0') === '1') {
                try {
                    app(LexofficeSync::class)->syncInvoice($invoice);
                } catch (\Throwable $e) {
                    // syncInvoice already stored the error on the invoice; just log.
                    Log::warning('Lexoffice sync failed for invoice ' . $invoice->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // WP-17: Send InvoiceAvailable notification email.
            // Non-blocking — finalization succeeds even if mail dispatch fails.
            try {
                $customer = $invoice->order->customer ?? null;
                if ($customer?->email) {
                    Mail::to($customer->email)
                        ->send(new \App\Mail\InvoiceAvailable($invoice));

                    $this->auditLog->log('invoice.mail.sent', $invoice, [
                        'invoice_number' => $invoice->invoice_number,
                        'recipient'      => $customer->email,
                        'customer_nr'    => $customer->customer_number,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('InvoiceAvailable mail failed for invoice ' . $invoice->id, [
                    'error' => $e->getMessage(),
                ]);

                $this->auditLog->log('invoice.mail.failed', $invoice, [
                    'invoice_number' => $invoice->invoice_number ?? null,
                    'recipient'      => $customer?->email,
                    'customer_nr'    => $customer?->customer_number,
                    'error'          => $e->getMessage(),
                ], level: 'error');
            }

            return $invoice;
        });
    }
}
