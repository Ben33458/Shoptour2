<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\OrderAdjustment;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\Product;
use App\Models\Orders\Order;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminCloseoutController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * GET /admin/orders/{order}/closeout
     * Show existing adjustments + form to add a new one.
     */
    public function show(Order $order): View
    {
        $order->load('customer');
        $adjustments = OrderAdjustment::where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->get();

        $gebindeList = Gebinde::orderBy('name')->get();
        $products    = Product::where('active', true)->orderBy('artikelnummer')->get();

        return view('admin.closeout.show', compact('order', 'adjustments', 'gebindeList', 'products'));
    }

    /**
     * POST /admin/orders/{order}/closeout
     * Append a new adjustment record.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment_type' => ['required', 'in:' . implode(',', OrderAdjustment::TYPES)],
            'gebinde_id'      => ['nullable', 'integer', 'exists:gebinde,id'],
            'product_id'      => ['nullable', 'integer', 'exists:products,id'],
            'reference_label' => ['nullable', 'string', 'max:200'],
            'qty'             => ['required', 'integer', 'min:1'],
            'amount_euros'    => ['nullable', 'numeric'],
            'note'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Build reference label if not provided
        if (empty($validated['reference_label'])) {
            if (! empty($validated['gebinde_id'])) {
                $validated['reference_label'] = Gebinde::find($validated['gebinde_id'])?->name;
            } elseif (! empty($validated['product_id'])) {
                $p                            = Product::find($validated['product_id']);
                $validated['reference_label'] = $p?->artikelnummer . ' ' . $p?->produktname;
            }
        }

        $adj = OrderAdjustment::create([
            'order_id'           => $order->id,
            'adjustment_type'    => $validated['adjustment_type'],
            'gebinde_id'         => $validated['gebinde_id'] ?? null,
            'product_id'         => $validated['product_id'] ?? null,
            'reference_label'    => $validated['reference_label'] ?? null,
            'qty'                => $validated['qty'],
            'amount_milli'       => isset($validated['amount_euros'])
                ? eur_to_milli((float) $validated['amount_euros'])
                : 0,
            'note'               => $validated['note'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->auditLog->log('adjustment.created', $adj, [
            'order_id'        => $order->id,
            'adjustment_type' => $adj->adjustment_type,
            'qty'             => $adj->qty,
        ]);

        return redirect()
            ->route('admin.orders.closeout', $order)
            ->with('success', 'Anpassung gespeichert.');
    }
}
