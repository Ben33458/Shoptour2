<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProductCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminProductImportController extends Controller
{
    public function __construct(
        private readonly ProductCsvImporter $importer,
    ) {}

    /**
     * GET /admin/imports/products
     * Show the CSV upload form.
     */
    public function index(): View
    {
        return view('admin.imports.product');
    }

    /**
     * POST /admin/imports/products/upload
     * Store the CSV temporarily and show preview.
     */
    public function upload(Request $request): View|RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file    = $request->file('csv_file');
        $preview = $this->importer->preview($file);

        $tmpPath = 'imports/products_' . time() . '_' . uniqid() . '.csv';
        Storage::disk('local')->put($tmpPath, file_get_contents($file->getRealPath()));

        return view('admin.imports.product', [
            'headers'    => $preview['headers'],
            'rows'       => $preview['rows'],
            'row_errors' => $preview['errors'],
            'preview'    => $preview['preview'],
            'tmp_path'   => $tmpPath,
        ]);
    }

    /**
     * POST /admin/imports/products/execute
     * Run the actual import.
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
                ->route('admin.imports.products')
                ->with('error', 'Temporäre Datei nicht gefunden. Bitte erneut hochladen.');
        }

        $result = $this->importer->import($fullPath);
        Storage::disk('local')->delete($tmpPath);

        return view('admin.imports.product', [
            'result'     => $result,
            'row_errors' => $result['errors'],
        ]);
    }
}
