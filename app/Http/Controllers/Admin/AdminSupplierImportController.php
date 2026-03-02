<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SupplierCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminSupplierImportController extends Controller
{
    public function __construct(
        private readonly SupplierCsvImporter $importer,
    ) {}

    public function index(): View
    {
        return view('admin.imports.supplier');
    }

    public function upload(Request $request): View|RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file    = $request->file('csv_file');
        $preview = $this->importer->preview($file);

        $tmpPath = 'imports/suppliers_' . time() . '_' . uniqid() . '.csv';
        Storage::disk('local')->put($tmpPath, file_get_contents($file->getRealPath()));

        return view('admin.imports.supplier', [
            'headers'    => $preview['headers'],
            'rows'       => $preview['rows'],
            'row_errors' => $preview['errors'],
            'preview'    => $preview['preview'],
            'tmp_path'   => $tmpPath,
        ]);
    }

    public function execute(Request $request): RedirectResponse|View
    {
        $request->validate(['tmp_path' => ['required', 'string']]);

        $tmpPath  = $request->input('tmp_path');
        $fullPath = Storage::disk('local')->path($tmpPath);

        if (! file_exists($fullPath)) {
            return redirect()->route('admin.imports.suppliers')
                ->with('error', 'Temporäre Datei nicht gefunden. Bitte erneut hochladen.');
        }

        $result = $this->importer->import($fullPath);
        Storage::disk('local')->delete($tmpPath);

        return view('admin.imports.supplier', [
            'result'     => $result,
            'row_errors' => $result['errors'],
        ]);
    }
}
