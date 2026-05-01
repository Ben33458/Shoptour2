<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\InvoiceAvailable;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Artisan;
use App\Models\Admin\DeferredTask;
use App\Models\Admin\Invoice;
use App\Models\Orders\Order;
use App\Services\Admin\AuditLogService;
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
 *  - lexoffice.sync_invoice      payload: {invoice_id: int}
 *  - email.invoice_available     payload: {invoice_id: int, email: string}
 *  - email.order_confirmation    payload: {order_id: int, customer_id: int}
 *  - wawi.sync_prices            payload: {} — runs wawi:sync-prices
 *  - wawi.sync_leergut           payload: {} — runs wawi:sync-leergut
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
            'lexoffice.sync_invoice'   => $this->handleLexofficeSync($payload),
            'email.invoice_available'  => $this->handleEmailInvoiceAvailable($payload),
            'email.order_confirmation' => $this->handleEmailOrderConfirmation($payload),
            'wawi.sync_prices'         => Artisan::call('wawi:sync-prices'),
            'wawi.sync_leergut'        => Artisan::call('wawi:sync-leergut'),
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

        app(AuditLogService::class)->log('invoice.mail.sent', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'recipient'      => $email,
            'source'         => 'deferred_task',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function handleEmailOrderConfirmation(array $payload): void
    {
        $orderId = (int) ($payload['order_id'] ?? 0);

        $order = Order::with(['items.product', 'rentalBookingItems.rentalItem', 'customer'])
            ->findOrFail($orderId);

        $customerEmail = $order->customer?->email ?? $order->customer?->billing_email;
        if (! $customerEmail) {
            throw new \InvalidArgumentException("No customer email for order {$orderId}");
        }

        $mailable = new OrderConfirmation($order);

        // Send to customer
        Mail::to($customerEmail)->send($mailable);

        app(AuditLogService::class)->log('order.confirmation.sent', $order, [
            'order_id'   => $order->id,
            'recipient'  => $customerEmail,
            'source'     => 'deferred_task',
        ]);

        // Send internal copy to company
        $internalEmail = config('mail.from.address');
        if ($internalEmail) {
            Mail::to($internalEmail)->send(new OrderConfirmation($order));
        }
    }
}
