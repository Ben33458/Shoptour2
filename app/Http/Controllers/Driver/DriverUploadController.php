<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver\DriverUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * POST /api/driver/upload
 *
 * Accepts a file for a driver upload. Supports two modes:
 *
 * Mode A – two-step (offline-first):
 *   1. Client sends an upload_requested driver_event → server creates a DriverUpload placeholder.
 *   2. Client POSTs the file here with device_id + client_upload_id.
 *
 * Mode B – direct (simpler, online-only):
 *   Client POSTs the file with device_id + tour_stop_id (no prior event needed).
 *   The server auto-generates a client_upload_id and creates the DriverUpload row.
 *
 * In both modes: if a matching (device_id, client_upload_id) row already exists it is
 * reused. Re-uploading simply overwrites the stored file and returns 200 (idempotent).
 *
 * Accepted MIME types: image/jpeg, image/png, image/webp, application/pdf
 * Max file size: 10 MB
 *
 * Response 200:
 * {
 *   "status": "uploaded",
 *   "upload_id": 42,
 *   "client_upload_id": "uuid-...",
 *   "file_path": "driver-uploads/2026/02/..."
 * }
 */
class DriverUploadController extends Controller
{
    private const ALLOWED_MIMES = ['jpeg', 'jpg', 'png', 'webp', 'pdf'];
    private const MAX_SIZE_KB   = 10_240; // 10 MB

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Identity
            'device_id'        => ['required', 'string', 'max:128'],
            // Optional: if omitted a UUID is auto-generated (Mode B)
            'client_upload_id' => ['nullable', 'string', 'max:128'],
            // Mode B fields (direct upload without a prior upload_requested event)
            'tour_stop_id'     => ['nullable', 'integer'],
            'upload_type'      => ['nullable', 'string', 'in:proof_of_delivery,delivery_note,other'],
            'note'             => ['nullable', 'string', 'max:1000'],
            // The file itself
            'file'             => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'max:' . self::MAX_SIZE_KB,
            ],
        ]);

        $deviceId       = $validated['device_id'];
        $clientUploadId = $validated['client_upload_id'] ?? (string) Str::uuid();

        /** @var int|null $employeeId */
        $employeeId = $request->attributes->get('driver_employee_id');

        // Try to find an existing upload job (Mode A / re-upload)
        $uploadJob = DriverUpload::where('device_id', $deviceId)
            ->where('client_upload_id', $clientUploadId)
            ->first();

        // None found → create a new job on-the-fly (Mode B — direct upload)
        if ($uploadJob === null) {
            $uploadJob = DriverUpload::create([
                'employee_id'      => $employeeId,
                'device_id'        => $deviceId,
                'client_upload_id' => $clientUploadId,
                'tour_stop_id'     => isset($validated['tour_stop_id']) ? (int) $validated['tour_stop_id'] : null,
                'upload_type'      => $validated['upload_type'] ?? DriverUpload::TYPE_OTHER,
                'status'           => DriverUpload::STATUS_PENDING,
            ]);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $subDir     = 'driver-uploads/' . now()->format('Y/m');
        $storedPath = Storage::disk('local')->putFile($subDir, $file);

        if ($storedPath === false) {
            $uploadJob->update(['status' => DriverUpload::STATUS_FAILED]);

            return response()->json(['error' => 'Failed to store file.'], 500);
        }

        $uploadJob->update([
            'status'        => DriverUpload::STATUS_UPLOADED,
            'file_path'     => $storedPath,
            'mime_type'     => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'employee_id'   => $uploadJob->employee_id ?? $employeeId,
        ]);

        return response()->json([
            'status'           => 'uploaded',
            'upload_id'        => $uploadJob->id,
            'client_upload_id' => $clientUploadId,
            'file_path'        => $storedPath,
        ]);
    }
}
