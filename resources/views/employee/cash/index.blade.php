@extends('employee.layout')

@section('title', 'Kasse')

@section('content')
@php
    function fmtEur(int $cents): string {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }
    function fmtSign(int $cents): string {
        $s = ($cents >= 0 ? '+' : '') . number_format($cents / 100, 2, ',', '.') . ' €';
        return $s;
    }
@endphp

<h1 style="font-size:18px;font-weight:700;color:var(--c-text);margin-bottom:20px">
    Kasse &mdash; {{ $register->name }}
</h1>

{{-- ① Kassensturz-Ergebnis --}}
@if(session('kassensturz'))
@php $ks = session('kassensturz'); @endphp
<div class="card mb" style="border-left:4px solid {{ abs($ks['ungeklaert_cents']) > 500 ? 'var(--c-danger)' : 'var(--c-success)' }}">
    <div class="card-header"><h2>Kassensturz-Ergebnis</h2></div>
    <div style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px">
        <div class="stat-box">
            <div class="stat-label">Soll-Bestand</div>
            <div class="stat-value">{{ fmtEur($ks['soll_cents']) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Gezählt (Ist)</div>
            <div class="stat-value">{{ fmtEur($ks['ist_cents']) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Differenz</div>
            <div class="stat-value" style="color:{{ $ks['diff_cents'] === 0 ? 'var(--c-success)' : 'var(--c-danger)' }}">
                {{ fmtSign($ks['diff_cents']) }}
            </div>
        </div>
        @if($ks['trinkgeld_cents'] > 0)
        <div class="stat-box">
            <div class="stat-label">Davon Trinkgeld</div>
            <div class="stat-value" style="color:var(--c-warning)">{{ fmtEur($ks['trinkgeld_cents']) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Ungeklärte Differenz</div>
            <div class="stat-value" style="color:{{ abs($ks['ungeklaert_cents']) > 500 ? 'var(--c-danger)' : 'var(--c-success)' }}">
                {{ fmtSign($ks['ungeklaert_cents']) }}
            </div>
        </div>
        @endif
    </div>
    @if(abs($ks['ungeklaert_cents']) > 500)
    <div style="padding:0 20px 16px;color:var(--c-danger);font-size:13px">
        ⚠ Ungeklärte Differenz über ±5 € — bitte Buchungen prüfen.
    </div>
    @endif
</div>
@endif

{{-- Success message --}}
@if(session('success'))
<div class="alert alert-success mb">{{ session('success') }}</div>
@endif

{{-- ① Saldo-Übersicht --}}
<div class="card mb">
    <div class="card-header"><h2>Kassenbestand</h2></div>
    <div style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px">
        <div class="stat-box primary">
            <div class="stat-label">Soll-Kassenbestand</div>
            <div class="stat-value" style="font-size:22px">{{ fmtEur($soll_cents) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Einnahmen seit letztem Kassensturz</div>
            <div class="stat-value" style="color:var(--c-success)">+{{ fmtEur($einnahmen) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Ausgaben seit letztem Kassensturz</div>
            <div class="stat-value" style="color:var(--c-danger)">−{{ fmtEur($ausgaben) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Letzter Kassensturz</div>
            <div class="stat-value" style="font-size:13px;color:var(--c-muted)">
                {{ $last_count_at ? \Carbon\Carbon::parse($last_count_at)->format('d.m.Y H:i') : '—' }}
            </div>
        </div>
    </div>
</div>

{{-- ② Buchung anlegen --}}
<div class="card mb">
    <div class="card-header"><h2>Buchung anlegen</h2></div>
    <div style="padding:20px">
        <form method="POST" action="{{ route('employee.cash.store') }}" id="bookingForm">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Buchungstyp</label>
                    <select name="booking_type" id="bookingType" class="form-control" onchange="toggleSafeField()">
                        <option value="customer_payment">Kundenzahlung (Einnahme)</option>
                        <option value="supplier_payment">Lieferant bezahlt (Ausgabe)</option>
                        @if($safes->count())
                        <option value="safe_deposit">Tresor-Einzahlung</option>
                        @endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Betrag (EUR)</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           class="form-control" placeholder="0,00" required>
                </div>
                <div class="form-group" id="safeField" style="display:none">
                    <label class="form-label">Tresor</label>
                    <select name="safe_id" class="form-control">
                        <option value="">— bitte wählen —</option>
                        @foreach($safes as $safe)
                        <option value="{{ $safe->id }}">{{ $safe->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="noteField">
                    <label class="form-label">Notiz</label>
                    <input type="text" name="note" class="form-control" placeholder="Optional" maxlength="500">
                </div>
            </div>
            <button type="submit" class="btn-primary btn-sm" style="margin-top:8px">Buchen</button>
        </form>
    </div>
</div>

{{-- ③ Kassensturz --}}
<div class="card mb" style="border:2px solid var(--c-border)">
    <div class="card-header"><h2>Kassensturz durchführen</h2></div>
    <div style="padding:20px">
        <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
            Aktueller Soll-Bestand: <strong>{{ fmtEur($soll_cents) }}</strong>.
            Geben Sie den tatsächlich gezählten Betrag ein.
        </p>
        <form method="POST" action="{{ route('employee.cash.kassensturz') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Gezählter Ist-Betrag (EUR)</label>
                    <input type="number" name="ist_betrag" step="0.01" min="0"
                           class="form-control" placeholder="0,00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Davon Trinkgeld, freiwillig (EUR)</label>
                    <input type="number" name="trinkgeld" step="0.01" min="0"
                           class="form-control" placeholder="0,00">
                </div>
            </div>
            <button type="submit" class="btn-warning btn-sm" style="margin-top:8px"
                    onclick="return confirm('Kassensturz jetzt durchführen?')">
                Kassensturz durchführen
            </button>
        </form>
    </div>
</div>

{{-- ④ Buchungshistorie --}}
<div class="card">
    <div class="card-header"><h2>Buchungshistorie (letzte 50)</h2></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Kategorie</th>
                <th>Betrag</th>
                <th>Notiz</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
            @php
                $isCashCount = $tx->category === 'cash_count';
                $isDeposit   = $tx->type === 'deposit';
                $rowStyle    = $isCashCount ? 'background:rgba(59,130,246,.07)' : '';

                $catLabels = [
                    'tour_collection'  => 'Tour-Einnahme',
                    'customer_payment' => 'Kundenzahlung',
                    'supplier_payment' => 'Lieferant',
                    'safe_deposit'     => 'Tresor-Einzahlung',
                    'cash_count'       => 'Kassensturz',
                    'adjustment'       => 'Korrektur',
                ];
                $catLabel = $catLabels[$tx->category] ?? ($tx->category ?? '—');

                $noteDisplay = $tx->note;
                if ($isCashCount && $tx->note) {
                    $nd = json_decode($tx->note, true);
                    if ($nd) {
                        $noteDisplay = sprintf(
                            'Soll %s / Ist %s / Diff %s',
                            fmtEur((int)($nd['soll_cents'] ?? 0)),
                            fmtEur((int)($nd['ist_cents'] ?? 0)),
                            fmtSign((int)($nd['diff_cents'] ?? 0))
                        );
                        if (!empty($nd['trinkgeld_cents'])) {
                            $noteDisplay .= ' · Trinkgeld ' . fmtEur((int)$nd['trinkgeld_cents']);
                        }
                    }
                }
            @endphp
            <tr style="{{ $rowStyle }}">
                <td style="font-size:12px;color:var(--c-muted);white-space:nowrap">
                    {{ $tx->created_at->format('d.m.Y H:i') }}
                </td>
                <td>
                    @if($isCashCount)
                        <span class="badge badge-blue">{{ $catLabel }}</span>
                    @else
                        <span style="font-size:12px;color:var(--c-muted)">{{ $catLabel }}</span>
                    @endif
                </td>
                <td style="font-weight:600;color:{{ $isDeposit ? 'var(--c-success)' : 'var(--c-danger)' }};white-space:nowrap">
                    {{ $isDeposit ? '+' : '−' }}{{ fmtEur($tx->amount_cents) }}
                </td>
                <td style="font-size:12px;color:var(--c-muted)">{{ $noteDisplay }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="empty">Noch keine Buchungen.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<style>
.mb { margin-bottom:20px; }
.card { background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;box-shadow:var(--shadow);overflow:hidden; }
.card-header { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--c-border); }
.card-header h2 { font-size:14px;font-weight:600;color:var(--c-text);margin:0; }

.stat-box { background:var(--c-bg);border:1px solid var(--c-border);border-radius:8px;padding:12px 16px; }
.stat-box.primary { background:var(--c-primary);border-color:var(--c-primary); }
.stat-box.primary .stat-label { color:rgba(255,255,255,.8); }
.stat-box.primary .stat-value { color:#fff; }
.stat-label { font-size:11px;color:var(--c-muted);margin-bottom:4px; }
.stat-value { font-size:18px;font-weight:700;color:var(--c-text); }

.form-row { display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end; }
.form-group { display:flex;flex-direction:column;gap:4px;min-width:160px;flex:1; }
.form-label { font-size:12px;font-weight:500;color:var(--c-text); }
.form-control { padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text); }

.btn-sm { padding:7px 14px;border-radius:6px;border:none;font-size:13px;font-weight:500;cursor:pointer; }
.btn-primary { background:var(--c-primary);color:#fff; }
.btn-primary:hover { background:var(--c-primary-h); }
.btn-warning { background:#f59e0b;color:#fff; }
.btn-warning:hover { background:#d97706; }

.data-table { width:100%;border-collapse:collapse;font-size:13px; }
.data-table th { padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted);background:var(--c-bg);border-bottom:1px solid var(--c-border); }
.data-table td { padding:10px 16px;border-bottom:1px solid var(--c-border); }
.data-table tr:last-child td { border-bottom:none; }
.data-table .empty { text-align:center;color:var(--c-muted);padding:24px; }

.badge { display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600; }
.badge-blue { background:#dbeafe;color:#1d4ed8; }
[data-theme="dark"] .badge-blue { background:#1e3a5f;color:#93c5fd; }

.alert { padding:10px 16px;border-radius:8px;font-size:13px;margin-bottom:16px; }
.alert-success { background:#dcfce7;color:#166534;border:1px solid #86efac; }
[data-theme="dark"] .alert-success { background:#14532d;color:#86efac;border-color:#166534; }
</style>

@push('scripts')
<script>
function toggleSafeField() {
    const type = document.getElementById('bookingType').value;
    const safeField = document.getElementById('safeField');
    const noteField = document.getElementById('noteField');
    safeField.style.display = type === 'safe_deposit' ? 'flex' : 'none';
    noteField.style.display  = type === 'safe_deposit' ? 'none' : 'flex';
}
</script>
@endpush

@endsection
