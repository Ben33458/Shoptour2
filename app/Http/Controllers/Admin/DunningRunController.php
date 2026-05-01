<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Debtor\DunningRun;
use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\Customer;
use App\Services\Debtor\DebtorPdfService;
use App\Services\Debtor\DunningMailService;
use App\Services\Debtor\DunningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DunningRunController extends Controller
{
    public function __construct(
        private readonly DunningService $dunningService,
        private readonly DunningMailService $mailService,
        private readonly DebtorPdfService $pdfService,
    ) {}

    /**
     * GET /admin/mahnlaeufe
     * List all dunning runs.
     */
    public function index(): View
    {
        $runs = DunningRun::with(['createdBy'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.dunning.index', compact('runs'));
    }

    /**
     * GET /admin/mahnlaeufe/neu
     * Show proposals for a new dunning run.
     */
    public function create(): View
    {
        $proposals = $this->dunningService->buildProposals();

        return view('admin.dunning.create', compact('proposals'));
    }

    /**
     * POST /admin/mahnlaeufe
     * Create a draft dunning run from selected proposals.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_ids'  => 'required|array|min:1',
            'customer_ids.*'=> 'integer|exists:customers,id',
            'test_mode'     => 'nullable|boolean',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $selectedIds = collect($request->customer_ids);
        $allProposals = $this->dunningService->buildProposals();

        // Filter proposals to only selected customers
        $selectedProposals = $allProposals->filter(
            fn ($p) => $selectedIds->contains($p['customer']->id)
        );

        if ($selectedProposals->isEmpty()) {
            return back()->with('error', 'Keine mahnfähigen Kunden in der Auswahl.');
        }

        $run = $this->dunningService->createRun(
            $selectedProposals,
            (int) auth()->id(),
            (bool) $request->test_mode,
        );

        if ($request->notes) {
            $run->update(['notes' => $request->notes]);
        }

        return redirect()
            ->route('admin.dunning.show', $run)
            ->with('success', 'Mahnlauf erstellt. Bitte prüfen und ausführen.');
    }

    /**
     * GET /admin/mahnlaeufe/{run}
     * Show a dunning run with its items.
     */
    public function show(DunningRun $run): View
    {
        $run->load(['createdBy', 'items.customer']);

        return view('admin.dunning.show', compact('run'));
    }

    /**
     * POST /admin/mahnlaeufe/{run}/execute
     * Execute the dunning run — send all pending items.
     */
    public function execute(DunningRun $run): RedirectResponse
    {
        if (! $run->isDraft()) {
            return back()->with('error', 'Mahnlauf ist nicht im Entwurfs-Status.');
        }

        try {
            $result = $this->mailService->executeRun($run);

            $msg = sprintf(
                'Mahnlauf ausgeführt: %d versendet, %d fehlgeschlagen.',
                $result['sent'],
                $result['failed'],
            );

            if ($run->is_test_mode) {
                $msg = '[TESTMODUS] ' . $msg;
            }

            return redirect()
                ->route('admin.dunning.show', $run)
                ->with('success', $msg);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/debitoren/{customer}/mahnung
     * Create and immediately execute a single-customer dunning run.
     */
    public function sendQuick(Request $request, Customer $customer): RedirectResponse
    {
        $force    = $request->boolean('force');
        $proposal = $force
            ? $this->dunningService->buildForcedProposal($customer)
            : $this->dunningService->buildProposalForCustomer($customer);

        if (! $proposal) {
            if ($force) {
                // Forced but still no proposal → truly no open invoices
                return back()->with('error', 'Keine offenen Rechnungen vorhanden — Mahnversand nicht möglich.');
            }

            // Normal block: diagnose reasons and offer override
            $diagnosis = $this->dunningService->diagnoseBlockingReasons($customer);

            return back()->with('dunning_blocked', $diagnosis);
        }

        // Admin can override channel when using force mode
        if ($force && in_array($request->input('channel'), [DunningRunItem::CHANNEL_EMAIL, DunningRunItem::CHANNEL_POST], true)) {
            $proposal['channel'] = $request->input('channel');
        }

        $run = $this->dunningService->createRun(
            collect([$proposal]),
            (int) auth()->id(),
            $this->dunningService->isTestMode(),
        );

        if ($force) {
            $run->update(['notes' => 'Admin-Override: Sperrprüfungen wurden manuell übergangen.']);
        }

        try {
            $this->mailService->executeRun($run);

            // Optionally send a copy to the logged-in admin
            if ($request->boolean('copy_to_me')) {
                $item = $run->items()->with('customer')->first();
                if ($item) {
                    \Illuminate\Support\Facades\Mail::to(auth()->user()->email)
                        ->send(new \App\Mail\DunningLevel1Mail($item));
                }
            }

            $channelLabel = $proposal['channel'] === DunningRunItem::CHANNEL_POST
                ? 'als Briefpost weitergeleitet + E-Mail-Kopie an Kunden versendet'
                : 'als E-Mail versendet an ' . $proposal['recipient_email'];

            return redirect()
                ->route('admin.debtor.show', $customer)
                ->with('success', sprintf(
                    '%sMahnung Stufe %d %s.',
                    $force ? '[Override] ' : '',
                    $proposal['proposed_level'],
                    $channelLabel,
                ));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/mahnlaeufe/{run}/reset
     * Reset a finished test-mode run back to draft so it can be re-executed.
     */
    public function reset(DunningRun $run): RedirectResponse
    {
        if (! $run->is_test_mode) {
            return back()->with('error', 'Nur Testläufe können zurückgesetzt werden.');
        }

        if ($run->status === DunningRun::STATUS_CANCELLED) {
            return back()->with('error', 'Stornierte Läufe können nicht zurückgesetzt werden.');
        }

        $run->update([
            'status'  => DunningRun::STATUS_DRAFT,
            'sent_at' => null,
        ]);

        $run->items()->update([
            'status'        => DunningRunItem::STATUS_PENDING,
            'sent_at'       => null,
            'error_message' => null,
            'pdf_path'      => null,
        ]);

        return back()->with('success', 'Testlauf zurückgesetzt – kann erneut ausgeführt werden.');
    }

    /**
     * POST /admin/mahnlaeufe/{run}/cancel
     * Cancel a draft dunning run.
     */
    public function cancel(DunningRun $run): RedirectResponse
    {
        if (! $run->isDraft()) {
            return back()->with('error', 'Nur Entwürfe können storniert werden.');
        }

        $run->update(['status' => DunningRun::STATUS_CANCELLED]);

        return redirect()
            ->route('admin.dunning.index')
            ->with('success', 'Mahnlauf #' . $run->id . ' storniert.');
    }

    /**
     * POST /admin/mahnlaeufe/{run}/items/{item}/skip
     * Skip a single item in a draft run.
     */
    public function skipItem(DunningRun $run, DunningRunItem $item): RedirectResponse
    {
        if (! $run->isDraft()) {
            return back()->with('error', 'Mahnlauf ist bereits abgeschlossen.');
        }

        $item->update(['status' => DunningRunItem::STATUS_SKIPPED]);

        return back()->with('success', 'Position übersprungen.');
    }

    /**
     * GET /admin/mahnlaeufe/{run}/items/{item}/pdf
     * Download the dunning letter PDF for an item.
     */
    public function downloadPdf(DunningRun $run, DunningRunItem $item): Response
    {
        abort_if($item->dunning_run_id !== $run->id, 404);

        if (! $item->pdf_path || ! Storage::disk('local')->exists($item->pdf_path)) {
            // Generate on-the-fly if not yet done
            $item->load('customer');
            $pdfPath = $this->pdfService->generateDunningLetterPdf($item);
            $item->update(['pdf_path' => $pdfPath]);
        }

        $content  = Storage::disk('local')->get($item->pdf_path);
        $filename = 'Mahnung_' . $item->customer->customer_number . '_Stufe' . $item->dunning_level . '.pdf';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
