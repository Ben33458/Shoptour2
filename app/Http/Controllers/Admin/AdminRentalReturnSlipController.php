<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\RentalReturnSlip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRentalReturnSlipController extends Controller
{
    public function index(Request $request): View
    {
        $query = RentalReturnSlip::with(['order.customer'])->orderBy('created_at', 'desc');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $returnSlips = $query->paginate(30);
        return view('admin.rental.return-slips.index', compact('returnSlips'));
    }

    public function show(RentalReturnSlip $returnSlip): View
    {
        $returnSlip->load([
            'order.customer',
            'driver',
            'items.bookingItem.rentalItem',
            'items.damageTariff',
        ]);
        return view('admin.rental.return-slips.show', compact('returnSlip'));
    }

    public function updateItemCharge(Request $request, RentalReturnSlip $returnSlip, int $itemId): RedirectResponse
    {
        $item = $returnSlip->items()->findOrFail($itemId);
        $data = $request->validate([
            'manual_extra_charge_eur' => 'nullable|numeric|min:0',
            'notes'                   => 'nullable|string',
        ]);
        $item->update([
            'manual_extra_charge_milli' => !empty($data['manual_extra_charge_eur'])
                ? (int) round((float) $data['manual_extra_charge_eur'] * 1_000_000)
                : null,
            'notes' => $data['notes'] ?? $item->notes,
        ]);
        return back()->with('success', 'Nachbelastung angepasst.');
    }

    public function markReviewed(RentalReturnSlip $returnSlip): RedirectResponse
    {
        $returnSlip->update(['status' => RentalReturnSlip::STATUS_REVIEWED]);
        return back()->with('success', 'Rückgabeschein geprüft.');
    }

    public function markCharged(RentalReturnSlip $returnSlip): RedirectResponse
    {
        $returnSlip->update(['status' => RentalReturnSlip::STATUS_CHARGED]);
        return back()->with('success', 'Nachbelastung verbucht.');
    }
}
