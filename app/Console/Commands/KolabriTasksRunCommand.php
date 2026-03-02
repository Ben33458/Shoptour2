<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\InvoiceAvailable;
use App\Models\Admin\DeferredTask;
use App\Models\Admin\Invoice;
use App\Services\Integrations\LexofficeSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Process pending deferred tasks from the DB-backed queue.
 *
 * Designed for shared hosting where no persistent queue worker is available.
 * Schedule every 5 minutes via cron (see routes/console.php).
 *
 * Supported task types:
 *  - lexoffice.sync_invoice   payload: {invoice_id: int}
 *  - email.invoice_available  payload: {invoice_id: int, email: string}
 */
class KolabriTasksRunCommand extends Command
{
    protected $signature = 'kolabri:tasks:run
        {--limit=50 : Maximum number of tasks to process in this run}
        {--dry-run  : List pending tasks without processing them}';

    protected $description = 'Process pending deferred tasks (DB-backed queue fallback)';

    public function handle(): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        /** @var \Illuminate\Database\Eloquent\Collection<int, DeferredTask> $tasks */
        $tasks = DeferredTask::pending()
            ->oldest()
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty()) {
            $this->line('No pending tasks.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-run: {$tasks->count()} pending task(s) found:");
            foreach ($tasks as $task) {
                $this->line("  [{$task->id}] {$task->type} (attempts: {$task->attempts}/{$task->max_attempts})");
            }
            return self::SUCCESS;
        }

        $processed = 0;
        $failed    = 0;

        foreach ($tasks as $task) {
            $task->update([
                'status'   => DeferredTask::STATUS_RUNNING,
                'attempts' => $task->attempts + 1,
            ]);

            try {
                $this->dispatch($task);

                $task->update(['status' => DeferredTask::STATUS_DONE]);
                $processed++;
                $this->line("  ✓ [{$task->id}] {$task->type}");
            } catch (\Throwable $e) {
                $status = $task->attempts >= $task->max_attempts
                    ? DeferredTask::STATUS_FAILED
                    : DeferredTask::STATUS_PENDING;

                $task->update([
                    'status'     => $status,
                    'last_error' => $e->getMessage(),
                ]);

                $failed++;
                $this->warn("  ✗ [{$task->id}] {$task->type}: {$e->getMessage()} (status: {$status})");
            }
        }

        $this->info("Done. Processed: {$processed}, failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Dispatch a single task to the appropriate handler.
     *
     * @throws \UnexpectedValueException for unknown task types
     * @throws \Throwable                re-throws handler exceptions so the caller can retry
     */
    private function dispatch(DeferredTask $task): void
    {
        $payload = $task->getPayload();

        match ($task->type) {
            'lexoffice.sync_invoice' => $this->handleLexofficeSync($payload),
            'email.invoice_available' => $this->handleEmailInvoiceAvailable($payload),
            default => throw new \UnexpectedValueException("Unknown task type: {$task->type}"),
        };
    }

    /** @param array<string, mixed> $payload */
    private function handleLexofficeSync(array $payload): void
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $invoice   = Invoice::findOrFail($invoiceId);

        app(LexofficeSync::class)->syncInvoice($invoice);
    }

    /** @param array<string, mixed> $payload */
    private function handleEmailInvoiceAvailable(array $payload): void
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $email     = (string) ($payload['email'] ?? '');

        if (! $email) {
            throw new \InvalidArgumentException("Missing email in payload for invoice {$invoiceId}");
        }

        $invoice = Invoice::findOrFail($invoiceId);
        Mail::to($email)->send(new InvoiceAvailable($invoice));
    }
}
