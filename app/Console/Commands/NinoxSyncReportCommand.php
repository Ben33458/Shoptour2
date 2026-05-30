<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\NinoxSyncReportMail;
use App\Models\Admin\DeferredTask;
use App\Models\Catalog\Product;
use App\Models\Employee\Employee;
use App\Models\Orders\Order;
use App\Models\Pricing\Customer;
use App\Models\System\SyncRun;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NinoxSyncReportCommand extends Command
{
    protected $signature   = 'ninox:sync-report {--hours=12 : Period in hours to cover}';
    protected $description = 'Send a Ninox sync summary email for the past N hours';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $from  = now()->subHours($hours);
        $to    = now();
        $email = config('ninox.report_email', 'unmoeglich@gmail.com');

        $report = $this->buildReport($from, $to);

        Mail::to($email)->send(new NinoxSyncReportMail($report));

        $this->info("Sync report sent to {$email} (period: {$hours}h).");
        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function buildReport(Carbon $from, Carbon $to): array
    {
        // ── Push tasks (shoptour2 → Ninox) ───────────────────────────────────

        $pushTasks = DeferredTask::where('type', 'LIKE', 'ninox.push_%')
            ->whereBetween('updated_at', [$from, $to])
            ->whereIn('status', [DeferredTask::STATUS_DONE, DeferredTask::STATUS_FAILED])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Breakdown by type
        $pushByType = [];
        foreach ($pushTasks as $task) {
            $pushByType[$task->type] ??= ['done' => 0, 'failed' => 0];
            $pushByType[$task->type][$task->status === DeferredTask::STATUS_DONE ? 'done' : 'failed']++;
        }

        // Failed tasks with resolved entity info
        $pushErrors = $pushTasks
            ->where('status', DeferredTask::STATUS_FAILED)
            ->map(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'label'       => $this->resolveEntityLabel($t),
                'error'       => $t->last_error,
                'attempts'    => $t->attempts,
                'updated_at'  => $t->updated_at->format('d.m. H:i'),
            ])
            ->values()
            ->all();

        // Sample of done tasks per type (last 3 each)
        $pushSamples = [];
        foreach ($pushTasks->where('status', DeferredTask::STATUS_DONE)->groupBy('type') as $type => $tasks) {
            $pushSamples[$type] = $tasks->take(3)->map(fn ($t) => [
                'label'      => $this->resolveEntityLabel($t),
                'updated_at' => $t->updated_at->format('d.m. H:i'),
            ])->values()->all();
        }

        // ── Sync runs (manual imports / ninox:pull) ───────────────────────────

        $syncRuns = SyncRun::where('source', 'ninox')
            ->where('started_at', '>=', $from)
            ->orderBy('started_at')
            ->get();

        $syncRunRows = $syncRuns->map(fn ($r) => [
            'started_at' => $r->started_at->format('d.m. H:i'),
            'status'     => $r->status,
            'records'    => $r->records_processed ?? 0,
            'duration'   => $r->finished_at
                ? $r->started_at->diffInSeconds($r->finished_at) . ' s'
                : '–',
        ])->all();

        // ── New deferred tasks created (observer activity) ────────────────────

        $tasksCreated = DeferredTask::where('type', 'LIKE', 'ninox.push_%')
            ->where('created_at', '>=', $from)
            ->count();

        // Breakdown: new tasks created by type
        $tasksCreatedByType = DeferredTask::where('type', 'LIKE', 'ninox.push_%')
            ->where('created_at', '>=', $from)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        // ── Problematische Datensätze ─────────────────────────────────────────

        // 1. Kunden-Stubs: ninox_kunden_id gesetzt, aber Nummer beginnt mit "NINOX-"
        //    → kein automatisches Match gefunden beim Import, manuell reconcilen
        $unlinkedStubs = Customer::whereNotNull('ninox_kunden_id')
            ->where('customer_number', 'LIKE', 'NINOX-%')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'customer_number'  => $c->customer_number,
                'name'             => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))
                    ?: ($c->company_name ?? '–'),
                'ninox_kunden_id'  => $c->ninox_kunden_id,
                'created_at'       => $c->created_at->format('d.m.Y H:i'),
            ])
            ->all();

        // 2. Ninox-Bestellungen ohne Positionen
        $ordersWithoutItems = Order::whereNotNull('ninox_id')
            ->whereDoesntHave('items')
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($o) => [
                'order_number'  => $o->order_number,
                'customer_name' => $o->customer?->displayName() ?? '–',
                'delivery_date' => $o->delivery_date?->format('d.m.Y') ?? '–',
                'ninox_id'      => $o->ninox_id,
                'notes'         => mb_strimwidth($o->notes ?? '', 0, 80, '…'),
            ])
            ->all();

        // 3. Stuck tasks: pending seit mehr als 2 Stunden
        $stuckTasks = DeferredTask::where('type', 'LIKE', 'ninox.push_%')
            ->where('status', DeferredTask::STATUS_PENDING)
            ->where('updated_at', '<', now()->subHours(2))
            ->orderBy('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'type'       => $t->type,
                'label'      => $this->resolveEntityLabel($t),
                'attempts'   => $t->attempts,
                'since'      => $t->updated_at->format('d.m. H:i'),
            ])
            ->all();

        return [
            'period_from'            => $from->format('d.m.Y H:i'),
            'period_to'              => $to->format('d.m.Y H:i'),
            'push_total'             => $pushTasks->count(),
            'push_done'              => $pushTasks->where('status', DeferredTask::STATUS_DONE)->count(),
            'push_failed'            => $pushTasks->where('status', DeferredTask::STATUS_FAILED)->count(),
            'push_by_type'           => $pushByType,
            'push_errors'            => $pushErrors,
            'push_samples'           => $pushSamples,
            'sync_runs_total'        => $syncRuns->count(),
            'sync_runs_failed'       => $syncRuns->where('status', 'failed')->count(),
            'sync_runs'              => $syncRunRows,
            'tasks_created'          => $tasksCreated,
            'tasks_created_by_type'  => $tasksCreatedByType,
            'unlinked_stubs'         => $unlinkedStubs,
            'orders_without_items'   => $ordersWithoutItems,
            'stuck_tasks'            => $stuckTasks,
        ];
    }

    /**
     * Resolve a human-readable label for the entity referenced in a DeferredTask payload.
     */
    private function resolveEntityLabel(DeferredTask $task): string
    {
        $payload = $task->getPayload();

        return match ($task->type) {
            'ninox.push_customer' => $this->customerLabel((int) ($payload['customer_id'] ?? 0)),
            'ninox.push_order'    => $this->orderLabel((int) ($payload['order_id'] ?? 0)),
            'ninox.push_employee' => $this->employeeLabel((int) ($payload['employee_id'] ?? 0)),
            'ninox.push_product'  => $this->productLabel((int) ($payload['product_id'] ?? 0)),
            default               => json_encode($payload),
        };
    }

    private function customerLabel(int $id): string
    {
        if ($id <= 0) return '(unbekannt)';
        $c = Customer::find($id);
        if (! $c) return "Kunde #{$id} (gelöscht)";
        return $c->displayName() . ' · ' . $c->customer_number;
    }

    private function orderLabel(int $id): string
    {
        if ($id <= 0) return '(unbekannt)';
        $o = Order::with('customer')->find($id);
        if (! $o) return "Bestellung #{$id} (gelöscht)";
        $customer = $o->customer?->displayName() ?? '–';
        return ($o->order_number ?? "#{$id}") . ' · ' . $customer;
    }

    private function employeeLabel(int $id): string
    {
        if ($id <= 0) return '(unbekannt)';
        $e = Employee::find($id);
        if (! $e) return "Mitarbeiter #{$id} (gelöscht)";
        return $e->first_name . ' ' . $e->last_name;
    }

    private function productLabel(int $id): string
    {
        if ($id <= 0) return '(unbekannt)';
        $p = Product::find($id);
        if (! $p) return "Artikel #{$id} (gelöscht)";
        return ($p->produktname ?? $p->artikelnummer ?? "#{$id}");
    }
}
