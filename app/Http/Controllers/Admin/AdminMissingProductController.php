<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\MissingProductReport;
use Illuminate\Http\Request;

class AdminMissingProductController extends Controller
{
    public function index(Request $request)
    {
        $status    = $request->get('status', '');
        $sort      = $request->get('sort', 'created_at');
        $dir       = $request->get('dir', 'desc');
        $search    = trim($request->get('q', ''));

        $allowedSorts = ['created_at', 'produktname', 'artikelnummer', 'status'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query = MissingProductReport::with('employee', 'product.supplierProducts.supplier')
            ->orderBy($sort, $dir);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('produktname', 'like', "%{$search}%")
                  ->orWhere('artikelnummer', 'like', "%{$search}%")
                  ->orWhere('info_text', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(50)->withQueryString();

        return view('admin.missing-products.index', compact('reports', 'status', 'sort', 'dir', 'search'));
    }

    public function update(Request $request, MissingProductReport $report)
    {
        $data = $request->validate([
            'status'     => 'required|in:open,ordered,done,rejected',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $report->update($data);

        return back()->with('success', 'Status aktualisiert.');
    }
}
