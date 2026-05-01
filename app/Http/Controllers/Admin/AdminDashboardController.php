<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communications\Communication;
use App\Models\Employee\EmployeeFeedback;
use App\Models\Employee\Shift;
use App\Models\Employee\VacationRequest;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\System\SyncRun;
use App\Services\Statistics\PosStatisticsService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly PosStatisticsService $posStats) {}

    public function index(): View
    {
        // ── Sync-Status ───────────────────────────────────────────────────────
        $wawiLast     = SyncRun::lastSuccessfulFor('wawi');
        $ninoxLast    = SyncRun::lastSuccessfulFor('ninox');
        $wawiOverdue  = SyncRun::isOverdue('wawi', 12);
        $ninoxOverdue = SyncRun::isOverdue('ninox', 12);

        // ── Schicht-Warnungen ─────────────────────────────────────────────────

        // Nicht eingestempelt: Schicht hätte vor >30 Min beginnen sollen, aber kein actual_start
        $notClockedIn = Shift::whereNull('actual_start')
            ->where('planned_start', '<', now()->subMinutes(30))
            ->whereDate('planned_start', today())
            ->where('status', '!=', 'cancelled')
            ->with('employee')
            ->get();

        // Überstunden: Schicht läuft noch (kein actual_end), aber geplantes Ende >30 Min überschritten
        $overtime = Shift::whereNotNull('actual_start')
            ->whereNull('actual_end')
            ->where('planned_end', '<', now()->subMinutes(30))
            ->with(['employee', 'timeEntries' => fn ($q) => $q->whereNull('clocked_out_at')])
            ->get();

        // ── Fehlende Lieferartikel ────────────────────────────────────────────
        // OrderItems aus bestätigten/versendeten Bestellungen ohne vollständige Erfüllung
        $missingItems = OrderItem::whereHas('order', fn ($q) =>
                $q->whereIn('status', ['confirmed', 'shipped']))
            ->whereRaw('qty > COALESCE(
                (SELECT delivered_qty + not_delivered_qty
                 FROM order_item_fulfillments
                 WHERE order_item_id = order_items.id
                 LIMIT 1), 0)')
            ->with(['order', 'product'])
            ->limit(50)
            ->get();

        // ── Mitarbeiter-Feedback ──────────────────────────────────────────────
        $feedbackItems = EmployeeFeedback::with('employee')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('created_at')
            ->get();

        // ── Quick Stats ───────────────────────────────────────────────────────
        $stats = [
            'orders_today'          => Order::whereDate('created_at', today())->count(),
            'pending_vacation'      => VacationRequest::where('status', 'pending')->count(),
            'open_shift_reports'    => Shift::whereDoesntHave('report')
                                            ->where('status', 'completed')
                                            ->count(),
            'communications_review' => Communication::where('status', 'review')->count(),
            'open_feedback'         => EmployeeFeedback::where('status', 'open')->count(),
        ];

        // ── Gekühlte Kästen — Verkaufsentwicklung ─────────────────────────────
        $gekuehlteTrend   = $this->posStats->artikelWeeklyTrend('52288', 12);
        $mengen           = array_column($gekuehlteTrend, 'menge');
        $gekuehlteGesamt  = array_sum($mengen);
        $gekuehlteUmsatz  = array_sum(array_column($gekuehlteTrend, 'umsatz'));
        $gekuehlteDieseKw = end($gekuehlteTrend)->menge ?? 0;
        $gekuehlteMax     = max(array_merge([1], $mengen));

        return view('admin.dashboard', compact(
            'wawiLast', 'ninoxLast', 'wawiOverdue', 'ninoxOverdue',
            'notClockedIn', 'overtime', 'missingItems', 'stats', 'feedbackItems',
            'gekuehlteTrend', 'gekuehlteGesamt', 'gekuehlteUmsatz',
            'gekuehlteDieseKw', 'gekuehlteMax'
        ));
    }
}
