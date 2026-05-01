<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ninox\NinoxImportRun;
use App\Services\Ninox\NinoxImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NinoxImportController extends Controller
{
    public function __construct(
        private readonly NinoxImportService $service,
    ) {}

    public function index(): View
    {
        $runs = NinoxImportRun::with('createdBy')
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('admin.ninox-import.index', compact('runs'));
    }

    public function show(NinoxImportRun $run): View
    {
        $run->load('tables');

        return view('admin.ninox-import.show', compact('run'));
    }

    /**
     * Import both Ninox databases (kehr + alt) and sync kehr → ninox_* tables.
     */
    public function run(Request $request): RedirectResponse
    {
        try {
            $result  = $this->service->runAll($request->user()->id);
            $kehrRun = $result['kehr'];
            $altRun  = $result['alt'];
            $sync    = $result['sync'];

            $kehrStatus = $kehrRun->status === 'completed'
                ? "kehr: {$kehrRun->records_imported} Datensätze"
                : "kehr: FEHLER — {$kehrRun->error_message}";

            $altStatus = $altRun->status === 'completed'
                ? "alt: {$altRun->records_imported} Datensätze"
                : "alt: FEHLER — {$altRun->error_message}";

            $syncStatus = isset($sync['error'])
                ? "Sync-Fehler: {$sync['error']}"
                : "Sync: {$sync['synced_tables']} Tabellen, {$sync['synced_records']} Zeilen aktualisiert";

            $allOk = $kehrRun->status === 'completed' && $altRun->status === 'completed';
            $flash = $allOk ? 'success' : 'error';
            $msg   = implode(' | ', [$kehrStatus, $altStatus, $syncStatus]);

            return redirect()->route('admin.ninox-import.show', $kehrRun)->with($flash, $msg);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
