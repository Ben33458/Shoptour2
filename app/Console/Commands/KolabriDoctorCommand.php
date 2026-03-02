<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin\DeferredTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity checker for the Kolabri database.
 *
 * Runs a series of read-only checks and reports issues.
 * With --fix, safe automated fixes are applied (e.g. resetting stuck tasks).
 *
 * Exit codes:
 *  0 (SUCCESS) — no issues found
 *  1 (FAILURE) — at least one issue found
 */
class KolabriDoctorCommand extends Command
{
    protected $signature = 'kolabri:doctor
        {--fix : Apply safe automated fixes (e.g. reset stuck tasks)}';

    protected $description = 'Check database integrity and report issues';

    /** @var list<string> */
    private array $issues = [];

    public function handle(): int
    {
        $this->info('Running Kolabri Doctor…');
        $this->newLine();

        $this->checkFinalizedInvoicesWithoutNumber();
        $this->checkFinalizedInvoicesWithoutDate();
        $this->checkOrphanedInvoiceItems();
        $this->checkOrphanedPayments();
        $this->checkStuckTasks();

        if (empty($this->issues)) {
            $this->info('✅ All checks passed. Database is healthy.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('❌ Found ' . count($this->issues) . ' issue(s):');
        foreach ($this->issues as $issue) {
            $this->line("   • {$issue}");
        }

        return self::FAILURE;
    }

    // =========================================================================

    /** Check 1: Finalized invoices without an invoice_number */
    private function checkFinalizedInvoicesWithoutNumber(): void
    {
        $ids = DB::table('invoices')
            ->where('status', 'finalized')
            ->whereNull('invoice_number')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->line('  ✓ No finalized invoices without invoice_number.');
            return;
        }

        $this->warn("  ✗ Finalized invoices without invoice_number: [{$ids->implode(', ')}]");
        $this->issues[] = "Finalized invoices without invoice_number: IDs [{$ids->implode(', ')}]";
    }

    /** Check 2: Finalized invoices without finalized_at timestamp */
    private function checkFinalizedInvoicesWithoutDate(): void
    {
        $ids = DB::table('invoices')
            ->where('status', 'finalized')
            ->whereNull('finalized_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->line('  ✓ No finalized invoices without finalized_at.');
            return;
        }

        $this->warn("  ✗ Finalized invoices without finalized_at: [{$ids->implode(', ')}]");
        $this->issues[] = "Finalized invoices without finalized_at: IDs [{$ids->implode(', ')}]";
    }

    /** Check 3: InvoiceItems pointing to non-existent invoices (orphans) */
    private function checkOrphanedInvoiceItems(): void
    {
        $count = DB::table('invoice_items as ii')
            ->leftJoin('invoices as i', 'ii.invoice_id', '=', 'i.id')
            ->whereNull('i.id')
            ->count();

        if ($count === 0) {
            $this->line('  ✓ No orphaned invoice_items.');
            return;
        }

        $this->warn("  ✗ Orphaned invoice_items (no parent invoice): {$count} row(s).");
        $this->issues[] = "Orphaned invoice_items: {$count} row(s)";
    }

    /** Check 4: Payments pointing to non-existent invoices (orphans) */
    private function checkOrphanedPayments(): void
    {
        $count = DB::table('payments as p')
            ->leftJoin('invoices as i', 'p.invoice_id', '=', 'i.id')
            ->whereNull('i.id')
            ->count();

        if ($count === 0) {
            $this->line('  ✓ No orphaned payments.');
            return;
        }

        $this->warn("  ✗ Orphaned payments (no parent invoice): {$count} row(s).");
        $this->issues[] = "Orphaned payments: {$count} row(s)";
    }

    /**
     * Check 5: Deferred tasks stuck in "running" for more than 1 hour.
     *
     * With --fix: resets them to "pending" so they can be retried.
     */
    private function checkStuckTasks(): void
    {
        $stuckCutoff = now()->subHour();

        $stuck = DeferredTask::where('status', DeferredTask::STATUS_RUNNING)
            ->where('updated_at', '<', $stuckCutoff)
            ->get(['id', 'type', 'updated_at']);

        if ($stuck->isEmpty()) {
            $this->line('  ✓ No stuck running tasks.');
            return;
        }

        $this->warn("  ✗ Stuck running tasks (> 1 hour): {$stuck->count()} task(s).");
        foreach ($stuck as $task) {
            $this->line("      #{$task->id} {$task->type} (stuck since {$task->updated_at->format('d.m.Y H:i')})");
        }

        $this->issues[] = "Stuck tasks: {$stuck->count()} task(s)";

        if ($this->option('fix')) {
            DeferredTask::where('status', DeferredTask::STATUS_RUNNING)
                ->where('updated_at', '<', $stuckCutoff)
                ->update([
                    'status'     => DeferredTask::STATUS_PENDING,
                    'last_error' => 'Reset by kolabri:doctor --fix (was stuck in running state)',
                    'updated_at' => now(),
                ]);

            $this->info("  → Reset {$stuck->count()} stuck task(s) to pending.");
        }
    }
}
