@extends('admin.layout')

@section('title', 'Lexoffice Integration')

@section('actions')
    <a href="{{ route('admin.settings.integrations') }}" class="btn btn-outline btn-sm">⚙ Einstellungen & API-Keys</a>
@endsection

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;background:var(--c-success-bg,#d1fae5);border:1px solid var(--c-success,#10b981);border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #ef4444;border-radius:6px;color:#7f1d1d">
        {{ session('error') }}
    </div>
@endif

{{-- ── 0. Inkrementeller Sync (wie stündlicher Cron) ───────────────────── --}}
<div class="card" style="margin-bottom:24px;border:2px solid var(--c-primary,#2563eb)">
    <div class="card-header" style="background:var(--c-primary,#2563eb);color:#fff">Synchronisierung</div>
    <div style="padding:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <div style="font-weight:600;margin-bottom:4px">Inkrementellen Sync jetzt ausführen</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Führt denselben Abgleich aus wie der stündliche Cron-Job — holt alle Kontakte und Belege,
                die seit dem letzten Lauf neu oder aktualisiert wurden.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.run-sync') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Jetzt synchronisieren</button>
        </form>
    </div>
</div>

{{-- ── 1. Vollständiger Datenimport ────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Vollständiger Datenimport</div>
    <div style="padding:20px">
        <p style="margin-bottom:16px;color:var(--c-muted);font-size:13px">
            Liest <strong>alle verfügbaren Daten</strong> aus Lexoffice aus und speichert sie in dedizierte
            <code>lexoffice_*</code>-Tabellen. Shoptour-Stammdaten (Kunden, Lieferanten, Rechnungen) werden
            <strong>nicht verändert</strong>.
        </p>
        <p style="margin-bottom:16px;font-size:13px">Importiert werden: Kontakte, Belege (alle Typen), Artikel, Zahlungskonditionen, Buchungskategorien, Drucklayouts, Wiederkehrende Rechnungen, Länderliste.</p>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.import-all') }}" id="lexoffice-import-all-form" style="display:inline">
            @csrf
            <button type="submit" id="lexoffice-import-all-btn" class="btn btn-primary btn-sm">
                Sämtliche Daten importieren
            </button>
        </form>

        {{-- Letzter Import-Status --}}
        @if(isset($lastImportRun) && $lastImportRun)
        <div id="lexoffice-last-run" style="margin-top:14px;padding:10px 14px;border-radius:6px;font-size:13px;
            @if($lastImportRun->status === 'done') background:#d1fae5;border:1px solid #10b981;color:#065f46;
            @elseif($lastImportRun->status === 'failed') background:#fee2e2;border:1px solid #ef4444;color:#7f1d1d;
            @else background:#fef3c7;border:1px solid #f59e0b;color:#92400e; @endif">
            @if($lastImportRun->status === 'running')
                ⏳ <strong>Import läuft</strong> — gestartet {{ $lastImportRun->started_at->diffForHumans() }}
            @elseif($lastImportRun->status === 'done')
                @php $r = $lastImportRun->result_json; @endphp
                ✅ <strong>Letzter Import:</strong> {{ $lastImportRun->finished_at->format('d.m.Y H:i') }} Uhr
                ({{ $lastImportRun->started_at->diffInSeconds($lastImportRun->finished_at) }}s)
                @if($r)
                — Kontakte: {{ ($r['contacts']['created'] ?? 0) + ($r['contacts']['updated'] ?? 0) }},
                Belege: {{ ($r['vouchers']['created'] ?? 0) + ($r['vouchers']['updated'] ?? 0) }},
                Artikel: {{ ($r['articles']['created'] ?? 0) + ($r['articles']['updated'] ?? 0) }}
                @endif
            @elseif($lastImportRun->status === 'failed')
                ❌ <strong>Letzter Import fehlgeschlagen</strong> ({{ $lastImportRun->started_at->format('d.m.Y H:i') }}):
                {{ $lastImportRun->error_message }}
            @endif
        </div>
        @endif

        <div id="lexoffice-import-overlay" style="display:none;margin-top:14px;padding:12px 16px;background:#eff6ff;border:1px solid #3b82f6;border-radius:6px;color:#1e40af;font-size:13px">
            <span style="display:inline-block;width:14px;height:14px;border:2px solid #3b82f6;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;vertical-align:middle;margin-right:8px"></span>
            <strong>Import läuft</strong> — dies kann mehrere Minuten dauern. Bitte Seite nicht schließen oder neu laden.<br>
            <span style="font-size:12px;color:#3b82f6;margin-top:4px;display:block">
                Falls die Seite trotzdem einen Fehler zeigt: Einfach neu laden — der Import läuft im Hintergrund weiter.
            </span>
        </div>

        <style>
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
        <script>
            document.getElementById('lexoffice-import-all-form').addEventListener('submit', function () {
                document.getElementById('lexoffice-import-all-btn').disabled = true;
                document.getElementById('lexoffice-import-all-btn').textContent = 'Importiere…';
                document.getElementById('lexoffice-import-overlay').style.display = 'block';
                var lastRun = document.getElementById('lexoffice-last-run');
                if (lastRun) lastRun.style.display = 'none';
            });
        </script>

        <hr style="border:none;border-top:1px solid var(--c-border);margin:16px 0">

        <div style="font-weight:600;margin-bottom:4px">Zahlungen (Eingangs-/Ausgangszahlungen)</div>
        <p style="margin-bottom:10px;color:var(--c-muted);font-size:13px">
            Ruft die Zahlungshistorie aus Lexoffice ab und speichert sie in <code>lexoffice_payments</code>.<br>
            Pro Klick werden <strong>30 Belege</strong> verarbeitet (~18 s). Die Meldung zeigt, wie viele noch ausstehen.<br>
            Für den vollständigen Import via CLI: <code>php artisan lexoffice:import-payments</code>
        </p>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.import-payments') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">
                Zahlungen importieren (30 Belege)
            </button>
        </form>
    </div>
</div>

{{-- ── 2. Abgleich ──────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Abgleich</div>
    <div style="padding:20px">
        <p style="margin-bottom:16px;color:var(--c-muted);font-size:13px">
            Verknüpft importierte Lexoffice-Kontakte mit lokalen Kunden und Lieferanten.<br>
            Zuordnung: 1.&nbsp;Lexoffice-ID → 2.&nbsp;E-Mail → 3.&nbsp;K-Nummer → 4.&nbsp;Name.<br>
            Nicht zuordbare Kontakte werden als neue Kunden/Lieferanten angelegt.
        </p>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.reconcile') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">
                Abgleich durchführen
            </button>
        </form>
    </div>
</div>

{{-- ── 3. Von Lexoffice importieren (Pull) ─────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Von Lexoffice importieren</div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:24px">

        {{-- Kunden --}}
        <div>
            <div style="font-weight:600;margin-bottom:4px">Kunden</div>
            <div class="hint" style="margin-bottom:10px">
                Gleicht alle Lexoffice-Kontakte (Rolle: Kunde) mit lokalen Kunden ab.<br>
                Zuordnung: 1.&nbsp;Lexoffice-ID → 2.&nbsp;E-Mail → 3.&nbsp;K-Nummer im Firmennamen (z.B. „Müller GmbH (K1234)").
                Nicht zugeordnete Kontakte werden übersprungen und in der Ergebnismeldung gezählt.
            </div>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.pull.customers') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Kunden abgleichen</button>
            </form>
        </div>

        <hr style="border:none;border-top:1px solid var(--c-border)">

        {{-- Lieferanten --}}
        <div>
            <div style="font-weight:600;margin-bottom:4px">Lieferanten</div>
            <div class="hint" style="margin-bottom:10px">
                Gleicht alle Lexoffice-Kontakte (Rolle: Lieferant) mit lokalen Lieferanten ab.<br>
                Zuordnung: 1.&nbsp;Lexoffice-ID → 2.&nbsp;E-Mail → 3.&nbsp;exakter Firmenname.
            </div>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.pull.suppliers') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Lieferanten abgleichen</button>
            </form>
        </div>

        <hr style="border:none;border-top:1px solid var(--c-border)">

        {{-- Belege --}}
        <div>
            <div style="font-weight:600;margin-bottom:4px">Belege</div>
            <div class="hint" style="margin-bottom:10px">
                Alle Ausgangsrechnungen, Gutschriften, Eingangsrechnungen und Eingangsgutschriften
                aus Lexoffice herunterladen und Kunden/Lieferanten zuordnen.
            </div>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.pull.vouchers') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Belege importieren</button>
            </form>
        </div>

        <hr style="border:none;border-top:1px solid var(--c-border)">

        {{-- Zahlungsstatus --}}
        <div>
            <div style="font-weight:600;margin-bottom:4px">Zahlungsstatus der Rechnungen</div>
            <div class="hint" style="margin-bottom:10px">
                Ruft für alle synchronisierten Rechnungen (mit Lexoffice-Voucher-ID) den aktuellen
                Zahlungsstatus ab und aktualisiert ihn lokal.
            </div>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.pull.payments') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Zahlungsstatus abrufen</button>
            </form>
        </div>

    </div>
</div>

{{-- ── 4. Gefahrenzone: Reset ────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px;border-color:#fca5a5">
    <div class="card-header" style="background:#fef2f2;color:#991b1b">Gefahrenzone</div>
    <div style="padding:20px">
        <div style="font-weight:600;margin-bottom:4px">Alle importierten Daten zurücksetzen</div>
        <div class="hint" style="margin-bottom:12px">
            Löscht alle Kunden, Lieferanten und Belege, die aus Lexoffice importiert wurden.
            Manuell angelegte Datensätze (ohne Lexoffice-ID) bleiben erhalten.
            Danach kann ein frischer Import durchgeführt werden.
        </div>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.reset-imported') }}">
            @csrf
            <button type="submit" class="btn btn-sm"
                    style="background:#dc2626;color:#fff;border-color:#dc2626">
                Importierte Daten zurücksetzen
            </button>
        </form>
    </div>
</div>

{{-- ── 5. Letzte Push-Synchronisierungen ───────────────────────────────── --}}
<div class="card">
    <div class="card-header">Letzte Push-Synchronisierungen (Rechnungen → Lexoffice)</div>
    <div class="table-wrap">
        @if($recentInvoices->isEmpty())
            <p style="padding:16px;color:var(--c-muted)">Noch keine Synchronisierungen.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Rechnungsnummer</th>
                        <th>Lexoffice-Voucher-ID</th>
                        <th>Zahlungsstatus</th>
                        <th>Letzter Sync</th>
                        <th>Fehler</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentInvoices as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td style="font-size:11px;color:var(--c-muted)">{{ $inv->lexoffice_voucher_id ?? '–' }}</td>
                        <td>
                            @if($inv->lexoffice_payment_status === 'paid' || $inv->lexoffice_payment_status === 'paidoff')
                                <span class="badge badge-delivered">bezahlt</span>
                            @elseif($inv->lexoffice_payment_status === 'open')
                                <span class="badge badge-pending">offen</span>
                            @elseif($inv->lexoffice_payment_status)
                                <span class="badge">{{ $inv->lexoffice_payment_status }}</span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td>{{ $inv->lexoffice_synced_at?->format('d.m.Y H:i') ?? '–' }}</td>
                        <td style="color:var(--c-danger);font-size:12px;max-width:300px;word-break:break-word">
                            {{ $inv->lexoffice_sync_error ?? '' }}
                        </td>
                        <td style="text-align:right">
                            <form method="POST"
                                  action="{{ route('admin.integrations.lexoffice.sync', $inv) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">Neu sync</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- ── Bank-Matching Tool ───────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Nicht zugeordnete Umsätze</div>
    <div style="padding:20px">
        <p style="margin-bottom:16px;color:var(--c-muted);font-size:13px">
            Rechnungen in Lexoffice, die noch keinem lokalen Kunden zugeordnet wurden.
            Hier können Zuordnungen vorgenommen, Notizen hinterlegt und Bestätigungen gesetzt werden.
        </p>
        <a href="{{ route('admin.integrations.lexoffice.bank-matching') }}" class="btn btn-primary btn-sm">
            Zum Zuordnungs-Tool →
        </a>
    </div>
</div>

@endsection
