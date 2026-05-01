<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Debtor\DebtorNote;
use App\Models\Pricing\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DebtorNoteController extends Controller
{
    /**
     * POST /admin/debitoren/{customer}/notizen
     * Create a new debtor note / task / promise / dispute.
     */
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'type'                  => 'required|in:note,task,payment_promise,dispute,warning',
            'body'                  => 'required|string|max:2000',
            'promised_date'         => 'nullable|date',
            'due_at'                => 'nullable|date',
            'lexoffice_voucher_id'  => 'nullable|integer|exists:lexoffice_vouchers,id',
            'assigned_to_user_id'   => 'nullable|integer|exists:users,id',
        ]);

        DebtorNote::create([
            ...$data,
            'customer_id'        => $customer->id,
            'status'             => DebtorNote::STATUS_OPEN,
            'created_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Notiz gespeichert.');
    }

    /**
     * PATCH /admin/debitoren/notizen/{note}/status
     * Mark a note as done or re-open.
     */
    public function updateStatus(Request $request, DebtorNote $note): RedirectResponse
    {
        $request->validate(['status' => 'required|in:open,done']);

        $note->update(['status' => $request->status]);

        return back()->with('success', 'Status aktualisiert.');
    }

    /**
     * DELETE /admin/debitoren/notizen/{note}
     * Delete a note.
     */
    public function destroy(DebtorNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('success', 'Eintrag gelöscht.');
    }
}
