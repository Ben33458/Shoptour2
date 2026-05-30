<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\DocScanUpload;
use App\Models\Employee\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Supplier\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MeinDocScanController extends Controller
{
    private string $docscanUrl;

    public function __construct()
    {
        $this->docscanUrl = rtrim(env('DOCSCAN_URL', 'http://89.167.121.25:5002'), '/');
    }

    public function index(): View
    {
        $employee = Employee::findOrFail(session('employee_id'));

        // Purge expired soft-deletes (>7 days) for this employee
        DocScanUpload::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('deleted_at', '<', now()->subDays(7))
            ->get()
            ->each(function ($u) {
                Storage::disk('local')->delete($u->storage_path);
                $u->forceDelete();
            });

        $myUploads = DocScanUpload::withTrashed()
            ->where('employee_id', $employee->id)
            ->with(['routedByEmployee'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $this->attachInternLabels($myUploads);

        $pendingAll = null;
        $deletedAll = null;
        if (in_array($employee->role, ['admin', 'manager'])) {
            // Global purge of expired soft-deletes
            DocScanUpload::withTrashed()
                ->where('deleted_at', '<', now()->subDays(7))
                ->get()
                ->each(function ($u) {
                    Storage::disk('local')->delete($u->storage_path);
                    $u->forceDelete();
                });

            $pendingAll = DocScanUpload::where('status', 'pending')
                ->whereNull('intern_typ')
                ->with(['employee'])
                ->orderByDesc('created_at')
                ->get();

            $this->attachInternLabels($pendingAll);

            $deletedAll = DocScanUpload::onlyTrashed()
                ->with(['employee', 'deletedByEmployee'])
                ->orderByDesc('deleted_at')
                ->get();
        }

        return view('mein.docscan.index', compact('employee', 'myUploads', 'pendingAll', 'deletedAll'));
    }

    public function storeFiles(Request $request): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $request->validate([
            'files'   => 'required|array|min:1|max:20',
            'files.*' => 'file|mimes:pdf,jpeg,jpg,png,webp|max:20480',
        ]);

        $files            = $request->file('files');
        $originalFilename = $files[0]->getClientOriginalName();
        $storagePath      = 'docscan/' . $employee->id . '/' . uniqid() . '_' . time();
        $pageCount        = 1;

        if (count($files) === 1) {
            $ext         = $files[0]->getClientOriginalExtension() ?: 'jpg';
            $storagePath .= '.' . $ext;
            Storage::disk('local')->put($storagePath, file_get_contents($files[0]->getRealPath()));
        } else {
            // Multiple images → merge via FastAPI /merge-only (no AI, just PDF combine)
            $httpRequest = Http::timeout(30);
            foreach ($files as $file) {
                $httpRequest = $httpRequest->attach(
                    'files',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType()]
                );
            }
            try {
                $mergeResp = $httpRequest->post($this->docscanUrl . '/merge-only');
            } catch (\Exception $e) {
                return response()->json(['error' => 'Zusammenführen fehlgeschlagen: ' . $e->getMessage()], 500);
            }
            if (!$mergeResp->successful()) {
                return response()->json(['error' => 'Fehler beim Zusammenführen der Seiten'], 500);
            }
            $mergeData   = $mergeResp->json();
            $storagePath .= '.pdf';
            Storage::disk('local')->put($storagePath, base64_decode($mergeData['bytes_b64']));
            $pageCount = $mergeData['page_count'] ?? count($files);
        }

        $upload = DocScanUpload::create([
            'employee_id'       => $employee->id,
            'original_filename' => $originalFilename,
            'storage_path'      => $storagePath,
            'page_count'        => $pageCount,
            'status'            => 'pending',
        ]);

        $isImage = !str_ends_with(strtolower($storagePath), '.pdf');

        return response()->json([
            'id'         => $upload->id,
            'filename'   => $originalFilename,
            'page_count' => $pageCount,
            'created_at' => $upload->created_at->format('d.m.y H:i'),
            'is_image'   => $isImage,
            'file_url'   => route('mein.docscan.file', $upload),
        ]);
    }

    public function analyze(DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        if ($upload->employee_id !== $employee->id && !in_array($employee->role, ['admin', 'manager'])) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        if (!Storage::disk('local')->exists($upload->storage_path)) {
            return response()->json(['error' => 'Datei nicht gefunden'], 404);
        }

        $fileBytes   = Storage::disk('local')->get($upload->storage_path);
        $ext         = strtolower(pathinfo($upload->storage_path, PATHINFO_EXTENSION));
        $contentType = $ext === 'pdf' ? 'application/pdf' : 'image/jpeg';

        try {
            $classifyResp = Http::timeout(90)
                ->attach('files', $fileBytes, $upload->original_filename, ['Content-Type' => $contentType])
                ->post($this->docscanUrl . '/classify');
        } catch (\Exception $e) {
            $upload->update(['ai_suggestion' => 'unknown', 'ai_reason' => 'Analyse fehlgeschlagen']);
            return response()->json(['error' => 'KI-Analyse fehlgeschlagen: ' . $e->getMessage()], 500);
        }

        if (!$classifyResp->successful()) {
            $upload->update(['ai_suggestion' => 'unknown', 'ai_reason' => 'KI nicht erreichbar']);
            return response()->json(['error' => 'KI-Service nicht erreichbar'], 500);
        }

        $classifyData = $classifyResp->json();
        $suggestion   = $classifyData['type'] ?? 'unknown';
        $pageCount    = $classifyData['page_count'] ?? $upload->page_count;
        $metadata     = array_filter([
            'title'         => $classifyData['title']         ?? null,
            'correspondent' => $classifyData['correspondent'] ?? null,
            'document_type' => $classifyData['document_type'] ?? null,
            'date'          => $classifyData['date']          ?? null,
            'amount'        => $classifyData['amount']        ?? null,
        ]);

        $upload->update([
            'page_count'    => $pageCount,
            'ai_suggestion' => in_array($suggestion, ['rechnung', 'dokument', 'beides']) ? $suggestion : 'unknown',
            'ai_reason'     => $classifyData['reason'] ?? '',
            'ai_metadata'   => $metadata ?: null,
        ]);

        return response()->json([
            'suggestion' => $suggestion,
            'reason'     => $classifyData['reason'] ?? '',
            'page_count' => $pageCount,
            'metadata'   => $metadata,
        ]);
    }

    public function rename(Request $request, DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $canEdit = $upload->employee_id === $employee->id
            || in_array($employee->role, ['admin', 'manager']);

        if (!$canEdit) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $request->validate(['title' => 'required|string|max:255']);

        $metadata          = $upload->ai_metadata ?? [];
        $metadata['title'] = trim($request->input('title'));
        $upload->update(['ai_metadata' => $metadata]);

        return response()->json(['ok' => true, 'title' => $metadata['title']]);
    }

    public function assign(Request $request, DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        if (!in_array($employee->role, ['admin', 'manager'])) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $request->validate([
            'destination' => 'required|in:paperless,lexoffice,both',
        ]);

        $destination = $request->input('destination');
        $destinations = $destination === 'both'
            ? ['paperless', 'lexoffice']
            : [$destination];

        if (!Storage::disk('local')->exists($upload->storage_path)) {
            return response()->json(['error' => 'Datei nicht mehr vorhanden'], 404);
        }

        $fileBytes    = Storage::disk('local')->get($upload->storage_path);
        $filename     = $upload->original_filename;
        $ext          = strtolower(pathinfo($upload->storage_path, PATHINFO_EXTENSION));
        $contentType  = $ext === 'pdf' ? 'application/pdf' : 'image/jpeg';

        try {
            $routeResp = Http::timeout(60)
                ->attach('file', $fileBytes, $filename, ['Content-Type' => $contentType])
                ->post($this->docscanUrl . '/route', [
                    'destinations' => json_encode($destinations),
                ]);
        } catch (\Exception $e) {
            $upload->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => 'Routing fehlgeschlagen: ' . $e->getMessage()], 500);
        }

        if (!$routeResp->successful()) {
            $err = $routeResp->json()['detail'] ?? $routeResp->body();
            $upload->update(['status' => 'failed', 'error_message' => $err]);
            return response()->json(['error' => $err], 500);
        }

        $upload->update([
            'status'      => 'routed',
            'destination' => $destination,
            'routed_by'   => $employee->id,
            'routed_at'   => now(),
        ]);

        // Delete file from storage after successful routing
        Storage::disk('local')->delete($upload->storage_path);

        return response()->json(['ok' => true, 'routed_to' => $routeResp->json()['routed_to'] ?? []]);
    }

    public function destroy(DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $canDelete = $upload->employee_id === $employee->id
            || in_array($employee->role, ['admin', 'manager']);

        if (!$canDelete) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $upload->update(['deleted_by' => $employee->id]);
        $upload->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        if (!in_array($employee->role, ['admin', 'manager'])) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $upload = DocScanUpload::withTrashed()->findOrFail($id);

        if (!$upload->trashed()) {
            return response()->json(['error' => 'Nicht gelöscht'], 400);
        }

        $upload->restore();
        $upload->update(['deleted_by' => null]);

        return response()->json(['ok' => true]);
    }

    public function replaceFile(Request $request, DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        if (!in_array($employee->role, ['admin', 'manager'])) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png|max:20480',
        ]);

        $oldPath = $upload->storage_path;
        $storagePath = 'docscan/' . $upload->employee_id . '/' . uniqid() . '_' . time() . '.jpg';
        Storage::disk('local')->put($storagePath, file_get_contents($request->file('file')->getRealPath()));
        Storage::disk('local')->delete($oldPath);

        $upload->update(['storage_path' => $storagePath]);

        return response()->json(['ok' => true]);
    }

    public function assignIntern(Request $request, DocScanUpload $upload): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $request->validate([
            'intern_typ' => 'required|in:anlieferung,lieferschein_kunden',
            'intern_id'  => 'required|integer|min:1',
        ]);

        $typ = $request->input('intern_typ');
        $id  = (int) $request->input('intern_id');

        if ($typ === 'anlieferung') {
            $gr = GoodsReceipt::findOrFail($id);
            $gr->update(['docscan_upload_id' => $upload->id]);
        } else {
            $order = Order::findOrFail($id);
            $order->update(['lieferschein_upload_id' => $upload->id]);
        }

        $upload->update([
            'intern_typ'           => $typ,
            'intern_id'            => $id,
            'intern_zugeordnet_at' => now(),
            'intern_zugeordnet_by' => $employee->id,
        ]);

        return response()->json(['ok' => true, 'intern_typ' => $typ, 'intern_id' => $id]);
    }

    public function searchAnlieferungen(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = GoodsReceipt::with('supplier')
            ->orderByDesc('arrived_at')
            ->limit(20);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('lieferschein_nr', 'like', "%{$q}%")
                   ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                   ->orWhere('arrived_at', 'like', "%{$q}%");
            });
        }

        $results = $query->get()->map(fn (GoodsReceipt $gr) => [
            'id'     => $gr->id,
            'label'  => ($gr->supplier?->name ?? '?') . ' – ' . ($gr->arrived_at?->format('d.m.Y') ?? '?')
                        . ($gr->lieferschein_nr ? ' (' . $gr->lieferschein_nr . ')' : ''),
            'status' => $gr->status,
        ]);

        return response()->json($results);
    }

    public function searchBestellungen(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = Order::with(['customer', 'tourStop'])
            ->addSelect([
                'last_delivered_at' => \Illuminate\Support\Facades\DB::table('tour_stops')
                    ->select('finished_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('finished_at')
                    ->limit(1),
            ])
            ->orderByDesc('last_delivered_at')
            ->orderByDesc('delivery_date')
            ->limit(20);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('order_number', 'like', "%{$q}%")
                   ->orWhereHas('customer', fn ($c) => $c->where('last_name', 'like', "%{$q}%")
                       ->orWhere('first_name', 'like', "%{$q}%")
                       ->orWhere('company_name', 'like', "%{$q}%")
                       ->orWhere('customer_number', 'like', "%{$q}%"));
            });
        }

        $results = $query->get()->map(fn (Order $order) => [
            'id'    => $order->id,
            'label' => $order->order_number . ' – ' . ($order->customer?->company_name ?: trim(($order->customer?->first_name ?? '') . ' ' . ($order->customer?->last_name ?? '')))
                       . ($order->tourStop?->finished_at ? ' · Geliefert ' . $order->tourStop->finished_at->format('d.m.Y') : ($order->delivery_date ? ' · Geplant ' . $order->delivery_date->format('d.m.Y') : '')),
        ]);

        return response()->json($results);
    }

    public function quickCreateAnlieferung(Request $request): JsonResponse
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $request->validate([
            'supplier_id'    => 'required|integer|exists:suppliers,id',
            'warehouse_id'   => 'required|integer|exists:warehouses,id',
            'arrived_at'     => 'required|date',
            'lieferschein_nr'=> 'nullable|string|max:100',
        ]);

        $gr = GoodsReceipt::create([
            'supplier_id'    => $request->input('supplier_id'),
            'warehouse_id'   => $request->input('warehouse_id'),
            'arrived_at'     => $request->input('arrived_at'),
            'lieferschein_nr'=> $request->input('lieferschein_nr'),
            'status'         => GoodsReceipt::STATUS_GEBUCHT,
            'kontrollstufe'  => GoodsReceipt::KONTROLLSTUFE_NUR_ANGEKOMMEN,
            'gebucht_at'     => now(),
            'gebucht_by_employee_id' => $employee->id,
        ]);

        $supplier = Supplier::find($request->input('supplier_id'));

        return response()->json([
            'id'    => $gr->id,
            'label' => ($supplier?->name ?? '?') . ' – ' . $gr->arrived_at->format('d.m.Y')
                       . ($gr->lieferschein_nr ? ' (' . $gr->lieferschein_nr . ')' : ''),
        ]);
    }

    private function attachInternLabels(\Illuminate\Support\Collection $uploads): void
    {
        $anlieferungIds = $uploads->where('intern_typ', 'anlieferung')->pluck('intern_id')->filter()->unique();
        $bestellungIds  = $uploads->where('intern_typ', 'lieferschein_kunden')->pluck('intern_id')->filter()->unique();

        $goodsReceipts = $anlieferungIds->isNotEmpty()
            ? GoodsReceipt::with('supplier')->whereIn('id', $anlieferungIds)->get()->keyBy('id')
            : collect();

        $orders = $bestellungIds->isNotEmpty()
            ? Order::with('customer')->whereIn('id', $bestellungIds)->get()->keyBy('id')
            : collect();

        $uploads->each(function (DocScanUpload $u) use ($goodsReceipts, $orders) {
            if ($u->intern_typ === 'anlieferung' && $u->intern_id) {
                $gr = $goodsReceipts[$u->intern_id] ?? null;
                $u->intern_label = $gr
                    ? ($gr->supplier?->name ?? '?') . ' – ' . ($gr->arrived_at?->format('d.m.Y') ?? '?')
                      . ($gr->lieferschein_nr ? ' (' . $gr->lieferschein_nr . ')' : '')
                    : '#' . $u->intern_id;
            } elseif ($u->intern_typ === 'lieferschein_kunden' && $u->intern_id) {
                $order = $orders[$u->intern_id] ?? null;
                $name  = $order?->customer?->company_name
                    ?: trim(($order?->customer?->first_name ?? '') . ' ' . ($order?->customer?->last_name ?? ''));
                $u->intern_label = $order
                    ? ($order->order_number ?? '') . ' – ' . $name
                    : '#' . $u->intern_id;
            }
        });
    }

    public function file(DocScanUpload $upload): Response
    {
        $employee = Employee::findOrFail(session('employee_id'));

        $canView = $upload->employee_id === $employee->id
            || in_array($employee->role, ['admin', 'manager']);

        if (!$canView) {
            abort(403);
        }

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
