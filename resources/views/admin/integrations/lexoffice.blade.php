@extends('admin.layout')

@section('title', 'Lexoffice Integration')

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

{{-- ── 1. Einstellungen ──────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Einstellungen</div>
    <div style="padding:20px">
        <form method="POST" action="{{ route('admin.integrations.lexoffice.update') }}">
            @csrf
            <div class="form-group" style="margin-bottom:12px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1"
                           {{ $settings['enabled'] ? 'checked' : '' }}>
                    Lexoffice-Sync aktiviert
                </label>
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label>API-Key</label>
                <input type="password" name="api_key" class="form-control"
                       value="{{ $settings['api_key'] }}" autocomplete="off"
                       placeholder="Lexoffice API-Key (Bearer Token)">
                <div class="hint">Zu finden unter Lexoffice → Einstellungen → API-Zugriff</div>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>

{{-- ── 2. Von Lexoffice importieren (Pull) ─────────────────────────────── --}}
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
                <button type="submit" class="btn btn-outline btn-sm"
                        onclick="return confirm('Kunden-Abgleich mit Lexoffice starten?')">
                    Kunden abgleichen
                </button>
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
                <button type="submit" class="btn btn-outline btn-sm"
                        onclick="return confirm('Lieferanten-Abgleich mit Lexoffice starten?')">
                    Lieferanten abgleichen
                </button>
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
                <button type="submit" class="btn btn-outline btn-sm"
                        onclick="return confirm('Alle Belege aus Lexoffice importieren?')">
                    Belege importieren
                </button>
            </form>
        </div>

        <hr style="border:none;border-top:1px solid var(--c-border)">

        {{-- Zahlungsstatus --}}
        <div>
            <div style="font-weight:600;margin-bottom:4px">Zahlungsstatus der Rechnungen</div>
            <div class="hint" style="margin-bottom:10px">
                Ruft für alle Rechnungen mit Lexoffice-Voucher-ID den aktuellen Zahlungsstatus ab
                und aktualisiert das Feld „Zahlungsstatus" lokal.<br>
                Nur offene und noch nicht abgerufene Rechnungen werden geprüft.
            </div>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.pull.payments') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm"
                        onclick="return confirm('Zahlungsstatus aller synchronisierten Rechnungen von Lexoffice abrufen?')">
                    Zahlungsstatus abrufen
                </button>
            </form>
        </div>

    </div>
</div>

{{-- ── 3. Gefahrenzone: Reset ────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px;border-color:#fca5a5">
    <div class="card-header" style="background:#fef2f2;color:#991b1b">Gefahrenzone</div>
    <div style="padding:20px">
        <div style="font-weight:600;margin-bottom:4px">Alle importierten Daten zurücksetzen</div>
        <div class="hint" style="margin-bottom:12px">
            Löscht alle Kunden, Lieferanten und Belege, die aus Lexoffice importiert wurden.
            Manuell angelegte Datensätze (ohne Lexoffice-ID) bleiben erhalten.
            Danach kann ein frischer Import durchgeführt werden.
        </div>
        <form method="POST" action="{{ route('admin.integrations.lexoffice.reset-imported') }}"
              onsubmit="return confirm('Wirklich alle Lexoffice-importierten Kunden, Lieferanten und Belege löschen? Diese Aktion kann nicht rückgängig gemacht werden.')">
            @csrf
            <button type="submit" class="btn btn-sm"
                    style="background:#dc2626;color:#fff;border-color:#dc2626">
                Importierte Daten zurücksetzen
            </button>
        </form>
    </div>
</div>

{{-- ── 4. Letzte Push-Synchronisierungen ───────────────────────────────── --}}
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

@endsection
