<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communications\Communication;
use App\Models\Communications\CommunicationTag;
use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Communications\CommunicationAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(private CommunicationAuditService $auditService) {}

    public function index(Request $request): View
    {
        $query = Communication::with(['senderContact', 'tags', 'confidence'])
            ->orderByDesc('received_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        if ($request->get('unassigned')) {
            $query->whereNull('communicable_id');
        }

        if ($tagId = $request->get('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('id', $tagId));
        }

        $communications = $query->paginate(30)->withQueryString();
        $reviewCount    = Communication::where('status', Communication::STATUS_REVIEW)->count();
        $tags           = CommunicationTag::orderBy('name')->get();

        return view('admin.communications.index', compact('communications', 'reviewCount', 'tags'));
    }

    public function show(Communication $communication): View
    {
        $communication->load(['senderContact', 'tags', 'confidence', 'audits.user', 'attachments', 'communicable']);

        $customers = Customer::where('active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'company_name', 'customer_number']);
        $suppliers = Supplier::where('active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.communications.show', compact('communication', 'customers', 'suppliers'));
    }

    public function assign(Request $request, Communication $communication): RedirectResponse
    {
        $request->validate([
            'communicable_type' => 'required|in:customer,supplier',
            'communicable_id'   => 'required|integer|min:1',
        ]);

        $type = $request->input('communicable_type') === 'customer'
            ? Customer::class
            : Supplier::class;
        $id = (int) $request->input('communicable_id');

        $oldType = $communication->communicable_type;
        $oldId   = $communication->communicable_id;

        $communication->update([
            'communicable_type' => $type,
            'communicable_id'   => $id,
            'status'            => Communication::STATUS_ASSIGNED,
        ]);

        $this->auditService->log($communication, 'assigned', [
            'old_type' => $oldType,
            'old_id'   => $oldId,
            'new_type' => $type,
            'new_id'   => $id,
        ], auth()->id());

        return back()->with('success', 'Kommunikation zugeordnet.');
    }

    public function review(Communication $communication): RedirectResponse
    {
        $communication->update([
            'reviewed_at'          => now(),
            'reviewed_by_user_id'  => auth()->id(),
            'status'               => $communication->communicable_id
                ? Communication::STATUS_ASSIGNED
                : Communication::STATUS_REVIEW,
        ]);

        $this->auditService->log($communication, 'reviewed', [], auth()->id());

        return back()->with('success', 'Als geprüft markiert.');
    }

    public function archive(Communication $communication): RedirectResponse
    {
        $communication->update(['status' => Communication::STATUS_ARCHIVED]);
        $this->auditService->log($communication, 'archived', [], auth()->id());

        return back()->with('success', 'Archiviert.');
    }

    public function updateStatus(Request $request, Communication $communication): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:new,review,assigned,archived',
        ]);

        $old = $communication->status;
        $communication->update(['status' => $request->input('status')]);

        $this->auditService->log($communication, 'status_changed', [
            'old' => $old,
            'new' => $request->input('status'),
        ], auth()->id());

        return back()->with('success', 'Status geändert.');
    }

    public function reply(Request $request, Communication $communication): RedirectResponse
    {
        $request->validate([
            'body_text' => 'required|string|max:10000',
            'type'      => 'nullable|in:reply,note',
        ]);

        $isReply   = $request->input('type', 'reply') === 'reply';
        $subject   = $isReply ? 'Re: ' . ($communication->subject ?? '') : 'Notiz zu: ' . ($communication->subject ?? '');
        $direction = $isReply ? Communication::DIRECTION_OUT : Communication::DIRECTION_OUT;

        $reply = Communication::create([
            'company_id'         => $communication->company_id,
            'source'             => Communication::SOURCE_MANUAL,
            'direction'          => $direction,
            'thread_id'          => $communication->thread_id,
            'subject'            => $subject,
            'body_text'          => $request->input('body_text'),
            'from_address'       => auth()->user()->email ?? null,
            'to_addresses'       => $communication->from_address ? [$communication->from_address] : [],
            'received_at'        => now(),
            'imported_at'        => now(),
            'status'             => $communication->communicable_id
                ? Communication::STATUS_ASSIGNED
                : Communication::STATUS_NEW,
            'communicable_type'  => $communication->communicable_type,
            'communicable_id'    => $communication->communicable_id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->auditService->log($reply, $isReply ? 'replied' : 'note_added', [
            'parent_id' => $communication->id,
        ], auth()->id());

        $label = $isReply ? 'Antwort' : 'Notiz';
        return back()->with('success', "{$label} gespeichert.");
    }

    public function createManual(Request $request): RedirectResponse
    {
        $request->validate([
            'subject'           => 'required|string|max:500',
            'body_text'         => 'nullable|string',
            'communicable_type' => 'nullable|in:customer,supplier',
            'communicable_id'   => 'nullable|integer',
        ]);

        $type = null;
        if ($request->input('communicable_type') === 'customer') {
            $type = Customer::class;
        } elseif ($request->input('communicable_type') === 'supplier') {
            $type = Supplier::class;
        }

        $communication = Communication::create([
            'company_id'         => auth()->user()->company_id,
            'source'             => Communication::SOURCE_MANUAL,
            'direction'          => Communication::DIRECTION_OUT,
            'subject'            => $request->input('subject'),
            'body_text'          => $request->input('body_text'),
            'received_at'        => now(),
            'imported_at'        => now(),
            'status'             => $type ? Communication::STATUS_ASSIGNED : Communication::STATUS_NEW,
            'communicable_type'  => $type,
            'communicable_id'    => $request->input('communicable_id'),
            'created_by_user_id' => auth()->id(),
        ]);

        $this->auditService->log($communication, 'manual_note', [
            'subject' => $request->input('subject'),
        ], auth()->id());

        return redirect()->route('admin.communications.show', $communication)
            ->with('success', 'Manuelle Aktivität erstellt.');
    }
}
