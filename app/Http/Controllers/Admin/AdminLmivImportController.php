<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Catalog\LmivCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * WP-15 – LMIV CSV import (upload → preview → execute).
 *
 * Routes:
 *   GET  /admin/imports/lmiv              index()    – upload form
 *   POST /admin/imports/lmiv/upload       upload()   – parse CSV + show preview
 *   POST /admin/imports/lmiv/execute      execute()  – run import
 */
class AdminLmivImportController extends Controller
{
    public function __construct(
        private readonly LmivCsvImporter $importer,
    ) {}

    /**
     * GET /admin/imports/lmiv
     */
    public function index(): View
    {
        return view('admin.imports.lmiv');
    }

    /**
     * POST /admin/imports/lmiv/upload
     */
    public function upload(Request $request): View|RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file    = $request->file('csv_file');
        $preview = $this->importer->preview($file);

        $tmpPath = 'imports/lmiv_' . time() . '_' . uniqid() . '.csv';
        Storage::disk('local')->put($tmpPath, (string) file_get_contents($file->getRealPath()));

        return view('admin.imports.lmiv', [
            'headers'    => $preview['headers'],
            'rows'       => $preview['rows'],
            'row_errors' => $preview['errors'],
            'preview'    => $preview['preview'],
            'tmp_path'   => $tmpPath,
        ]);
    }

    /**
     * POST /admin/imports/lmiv/execute
     */
    public function execute(Request $request): View|RedirectResponse
    {
        $request->validate([
            'tmp_path' => ['required', 'string'],
        ]);

        $tmpPath  = $request->input('tmp_path');
        $fullPath = Storage::disk('local')->path($tmpPath);

        if (! file_exists($fullPath)) {
            return redirect()
                ->route('admin.imports.lmiv')
                ->with('error', 'Temporäre Datei nicht gefunden. Bitte erneut hochladen.');
        }

        $result = $this->importer->import($fullPath, $request->user()?->id);
        Storage::disk('local')->delete($tmpPath);

        return view('admin.imports.lmiv', [
            'result'     => $result,
            'row_errors' => $result['errors'],
        ]);
    }
}
