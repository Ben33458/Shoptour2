<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reconcile\ProductReconcileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bringt die source_matches für Ninox-Produkte auf den aktuellen Stand:
 *
 *  1. Auto-Ignore: Datensätze mit nicht_in_kolabri_vorhanden=1 oder
 *     zum_loeschen_markiert=1 werden als "ignored" markiert.
 *
 *  2. Import: Alle confirmed-Matches ohne local_id werden als Produkte angelegt.
 *
 *  3. Auto-Match: Noch offene Datensätze werden gegen WaWi gematcht
 *     (Rule 0: kolabri-artnr, Rule 1: EAN, Rule 2: Fuzzy ≥ 90%).
 */
class NinoxCleanupCommand extends Command
{
    protected $signature = 'ninox:cleanup
                            {--dry-run       : Keine Änderungen speichern}
                            {--skip-ignore   : Schritt 1 (Auto-Ignore) überspringen}
                            {--skip-import   : Schritt 2 (Import confirmed) überspringen}
                            {--skip-match    : Schritt 3 (Auto-Match) überspringen}';

    protected $description = 'Bereinigt ungematchte Ninox-Produkte: auto-ignore, import confirmed, auto-match.';

    public function __construct(private readonly ProductReconcileService $reconcile)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        $this->printStats('Vorher');
        $this->newLine();

        if (! $this->option('skip-ignore')) {
            $this->step1AutoIgnore($dryRun);
            $this->newLine();
        }

        if (! $this->option('skip-import')) {
            $this->step2ImportConfirmed($dryRun);
            $this->newLine();
        }

        if (! $this->option('skip-match')) {
            $this->step3AutoMatch($dryRun);
            $this->newLine();
        }

        if (! $dryRun) {
            $this->printStats('Nachher');
        }

        return self::SUCCESS;
    }

    // =========================================================================

    private function step1AutoIgnore(bool $dryRun): void
    {
        $this->info('── Schritt 1: Auto-Ignore ──────────────────────────────────────');

        // Already-matched source_ids
        $matchedIds = DB::table('source_matches')
            ->where('entity_type', 'product')
            ->where('source', 'ninox')
            ->pluck('source_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $candidates = DB::table('ninox_marktbestand')
            ->whereNotIn('ninox_id', $matchedIds)
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    // nicht_in_kolabri_vorhanden = 1
                    $q2->whereNotNull('nicht_in_kolabri_vorhanden')
                       ->where('nicht_in_kolabri_vorhanden', '!=', 0);
                })->orWhere(function ($q2): void {
                    // zum_loeschen_markiert = 1
                    $q2->whereNotNull('zum_loeschen_markiert')
                       ->where('zum_loeschen_markiert', '!=', 0);
                });
            })
            ->get(['ninox_id', 'artikelname', 'nicht_in_kolabri_vorhanden', 'zum_loeschen_markiert']);

        $this->line("  Kandidaten: {$candidates->count()}");

        $ignored = 0;
        foreach ($candidates as $row) {
            $reason = match (true) {
                ! empty($row->nicht_in_kolabri_vorhanden) && $row->nicht_in_kolabri_vorhanden != 0 => 'nicht_in_kolabri',
                ! empty($row->zum_loeschen_markiert)      && $row->zum_loeschen_markiert != 0      => 'zum_loeschen',
                default                                                                              => 'auto',
            };

            $this->line("  [ignore:{$reason}] {$row->artikelname} (#{$row->ninox_id})");

            if (! $dryRun) {
                DB::table('source_matches')->insert([
                    'entity_type'     => 'product',
                    'local_id'        => null,
                    'source'          => 'ninox',
                    'source_id'       => (string) $row->ninox_id,
                    'status'          => 'ignored',
                    'confidence'      => 0,
                    'rule'            => $reason,
                    'source_snapshot' => json_encode((array) $row),
                    'confirmed_at'    => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $ignored++;
        }

        $this->info("  → {$ignored} als 'ignored' markiert" . ($dryRun ? ' [dry-run]' : ''));
    }

    private function step2ImportConfirmed(bool $dryRun): void
    {
        $this->info('── Schritt 2: Import confirmed ohne Produkt ────────────────────');

        $pending = DB::table('source_matches')
            ->where('entity_type', 'product')
            ->where('source', 'ninox')
            ->where('status', 'confirmed')
            ->whereNull('local_id')
            ->get(['source_id', 'source_snapshot']);

        $this->line("  Ausstehend: {$pending->count()}");

        if ($pending->isEmpty()) {
            $this->line('  Nichts zu importieren.');
            return;
        }

        foreach ($pending as $sm) {
            $snap = json_decode($sm->source_snapshot, true) ?? [];
            $this->line('  → ' . ($snap['artikelname'] ?? '?') . ' (ninox_id=' . $sm->source_id . ')');
        }

        if ($dryRun) {
            $this->warn("  [dry-run] Würde importConfirmed ausführen.");
            return;
        }

        // Use system user ID 1 as fallback for CLI-triggered imports
        $result = $this->reconcile->importConfirmed(1);

        $this->info("  Importiert: {$result['imported']}");
        $this->info("  Aktualisiert: {$result['updated']}");

        if ($result['skipped'] > 0) {
            $this->warn("  Übersprungen: {$result['skipped']}");
            foreach ($result['skipped_details'] as $d) {
                $this->line("    - {$d['name']}: {$d['reason']}");
            }
        }

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $e) {
                $this->error("  Fehler: {$e}");
            }
        }
    }

    private function step3AutoMatch(bool $dryRun): void
    {
        $this->info('── Schritt 3: Auto-Match für offene Datensätze ─────────────────');

        // Count truly open records (no source_match, not excluded)
        $matchedIds = DB::table('source_matches')
            ->where('entity_type', 'product')
            ->where('source', 'ninox')
            ->pluck('source_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $openCount = DB::table('ninox_marktbestand')
            ->whereNotIn('ninox_id', $matchedIds)
            ->where(function ($q): void {
                $q->whereNull('zum_loeschen_markiert')->orWhere('zum_loeschen_markiert', 0);
            })
            ->where(function ($q): void {
                $q->whereNull('nicht_in_kolabri_vorhanden')->orWhere('nicht_in_kolabri_vorhanden', 0);
            })
            ->count();

        $this->line("  Offene Datensätze: {$openCount}");

        if ($openCount === 0) {
            $this->line('  Nichts zu matchen.');
            return;
        }

        if ($dryRun) {
            $this->warn("  [dry-run] Würde autoMatchAll(minConfidence=90) ausführen.");
            return;
        }

        $result = $this->reconcile->autoMatchAll(90);

        $this->info("  Auto-gematcht: {$result['auto_matched']}");
        $this->line("  Bereits erledigt: {$result['already_done']}");
        $this->line("  Kein Match (< 90%): {$result['skipped']}");

        if ($result['auto_matched'] > 0) {
            $this->newLine();
            $this->warn("  → Bitte im Reconcile-UI prüfen und bestätigen: /admin/reconcile/products");
        }
    }

    // =========================================================================

    private function printStats(string $label): void
    {
        $total    = DB::table('ninox_marktbestand')->count();
        $statuses = DB::table('source_matches')
            ->where('entity_type', 'product')
            ->where('source', 'ninox')
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $confirmed = $statuses['confirmed'] ?? 0;
        $ignored   = $statuses['ignored']   ?? 0;
        $auto      = $statuses['auto']      ?? 0;
        $done      = $confirmed + $ignored + $auto;
        $open      = $total - $done;

        $this->line("── {$label} ──────────────────────────────────────────────────────");
        $this->line("  Ninox gesamt:  {$total}");
        $this->line("  confirmed:     {$confirmed}");
        $this->line("  auto:          {$auto}");
        $this->line("  ignored:       {$ignored}");
        $this->line("  offen:         {$open}");
    }
}
