<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver\DriverUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/driver/uploads/{upload}
 *
 * Serves an uploaded file to the authenticated driver.
 * Returns the file inline (suitable for in-browser viewing).
 */
class DriverUploadFileController extends Controller
{
    public function __invoke(Request $request, DriverUpload $upload): StreamedResponse
    {
        if ($upload->status !== DriverUpload::STATUS_UPLOADED || ! $upload->file_path) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($upload->file_path)) {
            abort(404);
        }

        $filename = $upload->original_name ?? 'file';

        return Storage::disk('local')->response($upload->file_path, $filename, [
            'Content-Type'        => $upload->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }
}
