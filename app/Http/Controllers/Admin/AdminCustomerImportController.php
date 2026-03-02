<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CustomerCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminCustomerImportController extends Controller
{
    public function __construct(
        private readonly CustomerCsvImporter $importer,
    ) {}

    /**
     * GET /admin/imports/customers
     * Show the CSV upload form.
     */
    public function index(): View
    {
        return view('admin.imports.customer');
    }

    /**
     * POST /admin/imports/customers/upload
     * Store the CSV temporarily and redirect to preview.
     */
    public function upload(Request $request): View|RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 MB
        ]);

        $file    = $request->file('csv_file');
        $preview = $this->importer->preview($file);

        // Store file for execute step
        $tmpPath = 'imports/customers_' . time() . '_' . uniqid() . '.csv';
        Storage::disk('local')->put($tmpPath, file_get_contents($file->getRealPath()));

        return view('admin.imports.customer', [
            'headers'    => $preview['headers'],
            'rows'       => $preview['rows'],
            'row_errors' => $preview['errors'],
            'preview'    => $preview['preview'],
            'tmp_path'   => $tmpPath,
        ]);
    }

    /**
     * POST /admin/imports/customers/execute
     * Run the actual import using the previously uploaded file.
     */
    public function execute(Request $request): RedirectResponse|View
    {
        $request->validate([
            'tmp_path' => ['required', 'string'],
        ]);

        $tmpPath  = $request->input('tmp_path');
        $fullPath = Storage::disk('local')->path($tmpPath);

        if (! file_exists($fullPath)) {
            return redirect()
                ->route('admin.imports.customers')
                ->with('error', 'Temporäre Datei nicht gefunden. Bitte erneut hochladen.');
        }

        $result = $this->importer->import($fullPath);

        // Clean up temp file
        Storage::disk('local')->delete($tmpPath);

        return view('admin.imports.customer', [
            'result'     => $result,
            'row_errors' => $result['errors'],
        ]);
    }
}
