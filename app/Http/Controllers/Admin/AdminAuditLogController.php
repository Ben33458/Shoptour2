<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_until')) {
            $query->whereDate('created_at', '<=', $request->input('date_until'));
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.audit-logs.index', [
            'logs'       => $logs,
            'filters'    => $request->only(['level', 'action', 'date_from', 'date_until']),
        ]);
    }
}
