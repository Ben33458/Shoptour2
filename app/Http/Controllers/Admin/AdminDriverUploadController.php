<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver\DriverUpload;
use App\Services\ImmichService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminDriverUploadController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $query = DriverUpload::query()
            ->where('status', DriverUpload::STATUS_UPLOADED)
            ->orderByDesc('created_at');

        if ($request->filled('immich_status')) {
            $query->where('immich_status', $request->input('immich_status'));
        } elseif (! $request->has('alle')) {
            // Default: only show 'offen' and not-yet-pushed (null)
            $query->whereIn('immich_status', ['offen', null]);
        }

        if ($request->filled('typ')) {
            $query->where('upload_type', $request->input('typ'));
        }

        if ($request->filled('von')) {
            $query->whereDate('created_at', '>=', $request->input('von'));
        }
        if ($request->filled('bis')) {
            $query->whereDate('created_at', '<=', $request->input('bis'));
        }

        $uploads = $query->paginate(24)->withQueryString();

        // Eager-load customer names via DB for display
        $customerNames = [];
        $stopIds = $uploads->pluck('tour_stop_id')->filter()->unique()->values()->all();
        if (! empty($stopIds)) {
            $rows = \DB::table('tour_stops as ts')
                ->join('orders as o', 'ts.order_id', '=', 'o.id')
                ->join('customers as c', 'o.customer_id', '=', 'c.id')
                ->whereIn('ts.id', $stopIds)
                ->select('ts.id as stop_id', 'c.id as customer_id', 'c.company_name', 'c.name')
                ->get();
            foreach ($rows as $row) {
                $customerNames[$row->stop_id] = [
                    'id'   => $row->customer_id,
                    'name' => $row->company_name ?: $row->name,
                ];
            }
        }

        return view('admin.driver-uploads.index', compact('uploads', 'customerNames'));
    }

    public function updateStatus(Request $request, DriverUpload $upload): JsonResponse
    {
        $request->validate([
            'immich_status' => 'required|in:offen,geprueft,abgerechnet',
        ]);

        $newStatus = $request->input('immich_status');

        try {
            app(ImmichService::class)->updateStatus($upload, $newStatus);
        } catch (\Throwable $e) {
            Log::warning("Immich updateStatus failed for upload {$upload->id}: " . $e->getMessage());
            // DB is already updated inside updateStatus() before the Immich call
            $upload->update(['immich_status' => $newStatus]);
        }

        return response()->json(['ok' => true, 'immich_status' => $newStatus]);
    }

    /**
     * GET /admin/driver-uploads/{upload}/mark/{status}
     *
     * One-click action URL for use inside Immich asset descriptions.
     * The user clicks the link from the Immich info panel → new tab opens,
     * status is updated, a minimal confirmation page is shown.
     */
    public function mark(Request $request, DriverUpload $upload, string $status): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        if (! in_array($status, ['offen', 'geprueft', 'abgerechnet'], true)) {
            abort(422, 'Unbekannter Status');
        }

        try {
            app(ImmichService::class)->updateStatus($upload, $status);
        } catch (\Throwable $e) {
            Log::warning("Immich mark failed for upload {$upload->id}: " . $e->getMessage());
            $upload->update(['immich_status' => $status]);
        }

        $labels = ['offen' => 'Offen', 'geprueft' => 'Geprüft', 'abgerechnet' => 'Abgerechnet'];
        $label  = $labels[$status] ?? $status;

        // If the request came from a browser tab (Accept: text/html), show a
        // minimal confirmation page so the user knows it worked.
        return response(<<<HTML
            <!DOCTYPE html><html lang="de"><head><meta charset="utf-8">
            <title>Status aktualisiert</title>
            <style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;
                         justify-content:center;min-height:100vh;margin:0;background:#f9fafb}
                   .box{text-align:center;padding:40px;background:#fff;border-radius:12px;
                        box-shadow:0 2px 12px rgba(0,0,0,.08);max-width:360px}
                   .icon{font-size:2.5rem;margin-bottom:12px}
                   h2{margin:0 0 8px;font-size:1.15rem}
                   p{color:#6b7280;font-size:.9rem;margin:0 0 20px}
                   a{display:inline-block;padding:8px 20px;background:#1a56db;color:#fff;
                     border-radius:6px;text-decoration:none;font-size:.9rem}</style>
            </head><body>
            <div class="box">
                <div class="icon">✅</div>
                <h2>Status: {$label}</h2>
                <p>Upload #{$upload->id} wurde erfolgreich aktualisiert.</p>
                <a href="/admin/driver-uploads">Zur Übersicht</a>
            </div>
            </body></html>
        HTML, 200, ['Content-Type' => 'text/html']);
    }

    /** POST — push current status + action links into Immich description. */
    public function syncDescription(DriverUpload $upload): JsonResponse
    {
        $svc = app(ImmichService::class);

        if (! $svc->isConfigured() || $upload->immich_asset_id === null) {
            return response()->json(['ok' => false, 'error' => 'Kein Immich-Asset verknüpft']);
        }

        try {
            $svc->setDescription($upload->immich_asset_id, $svc->buildDescription($upload));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    public function thumbnail(DriverUpload $upload): Response
    {
        if ($upload->file_path === null || ! Storage::disk('local')->exists($upload->file_path)) {
            abort(404);
        }

        $mime = $upload->mime_type ?? 'image/jpeg';
        $content = Storage::disk('local')->get($upload->file_path);

        return response($content, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
