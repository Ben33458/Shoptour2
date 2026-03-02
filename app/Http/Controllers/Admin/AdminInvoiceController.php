<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Orders\Order;
use App\Services\Admin\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class AdminInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * GET /admin/invoices
     * List all invoices.
     */
    public function index(): View
    {
        $invoices = Invoice::with(['order.customer'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.invoices.index', compact('invoices'));
    }

    /**
     * GET /admin/orders/{order}/invoice
     * Show invoice for an order (creates draft shell if none exists).
     */
    public function show(Order $order): View
    {
        $invoice = Invoice::where('order_id', $order->id)->with(['items', 'payments'])->first();
        $order->load('customer');

        return view('admin.invoices.show', compact('order', 'invoice'));
    }

    /**
     * POST /admin/orders/{order}/invoice/draft
     * (Re)generate the draft invoice from current order data.
     */
    public function draft(Order $order): RedirectResponse
    {
        try {
            $this->invoiceService->generateDraft($order);

            return redirect()
                ->route('admin.orders.invoice', $order)
                ->with('success', 'Entwurf neu berechnet.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.orders.invoice', $order)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/invoices/{invoice}/finalize
     * Finalize the invoice and generate PDF.
     */
    public function finalize(Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoiceService->finalizeInvoice($invoice);

            return redirect()
                ->route('admin.orders.invoice', $invoice->order_id)
                ->with('success', 'Rechnung finalisiert. PDF wurde erzeugt.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.orders.invoice', $invoice->order_id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * GET /admin/invoices/{invoice}/download
     * Stream the PDF file to the browser.
     */
    public function download(Invoice $invoice): Response
    {
        if (! $invoice->isFinalized() || $invoice->pdf_path === null) {
            abort(404, 'PDF noch nicht verfügbar.');
        }

        if (! Storage::disk('local')->exists($invoice->pdf_path)) {
            abort(404, 'PDF-Datei nicht gefunden.');
        }

        $content  = Storage::disk('local')->get($invoice->pdf_path);
        $filename = basename($invoice->pdf_path);

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
