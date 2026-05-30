<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocScanUpload;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AdminDocScanFileController extends Controller
{
    public function file(DocScanUpload $upload): Response
    {
        if (!Storage::disk('local')->exists($upload->storage_path)) {
            abort(404, 'Datei nicht mehr vorhanden');
        }

        $ext         = strtolower(pathinfo($upload->storage_path, PATHINFO_EXTENSION));
        $contentType = $ext === 'pdf' ? 'application/pdf' : 'image/jpeg';

        return response(Storage::disk('local')->get($upload->storage_path), 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'inline; filename="' . $upload->original_filename . '"',
        ]);
    }
}
