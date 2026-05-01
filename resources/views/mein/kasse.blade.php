@extends('mein.layout')

@section('title', 'Kasse')

@section('content')
@php
    function milliToEur(int $cents): string {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }
    function milliSign(int $cents): string {
        return ($cents >= 0 ? '+' : '') . number_format($cents / 100, 2, ',', '.') . ' €';
    }
@endphp

<div style="margin-bottom:20px">
    <h1 style="font-size:18px;font-weight:700;color:var(--c-text);margin:0">
        Kasse &mdash; {{ $register->name }}
    </h1>
</div>

{{-- Kassensturz-Ergebnis --}}
@if(session('kassensturz'))
@php $ks = session('kassensturz'); @endphp
<div class="mein-card" style="border-left:4px solid {{ abs($ks['ungeklaert_cents']) > 500 ? 'var(--c-danger)' : 'var(--c-success)' }};margin-bottom:16px">
    <div class="mein-card-title">Kassensturz-Ergebnis</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div><div style="font-size:11px;color:var(--c-muted)">Soll-Bestand</div><div style="font-size:18px;font-weight:700">{{ milliToEur($ks['soll_cents']) }}</div></div>
        <div><div style="font-size:11px;color:var(--c-muted)">Gezählt (Ist)</div><div style="font-size:18px;font-weight:700">{{ milliToEur($ks['ist_cents']) }}</div></div>
        <div>
            <div style="font-size:11px;color:var(--c-muted)">Differenz</div>
            <div style="font-size:18px;font-weight:700;color:{{ $ks['diff_cents'] === 0 ? 'var(--c-success)' : 'var(--c-danger)' }}">
                {{ milliSign($ks['diff_cents']) }}
            </div>
        </div>
        @if($ks['trinkgeld_cents'] > 0)
        <div><div style="font-size:11px;color:var(--c-muted)">Trinkgeld</div><div style="font-size:18px;font-weight:700;color:#f59e0b">{{ milliToEur($ks['trinkgeld_cents']) }}</div></div>
        <div>
            <div style="font-size:11px;color:var(--c-muted)">Ungeklärte Diff.</div>
            <div style="font-size:18px;font-weight:700;color:{{ abs($ks['ungeklaert_cents']) > 500 ? 'var(--c-danger)' : 'var(--c-success)' }}">
                {{ milliSign($ks['ungeklaert_cents']) }}
            </div>
        </div>
        @endif
    </div>
    @if(abs($ks['ungeklaert_cents']) > 500)
    <div style="margin-top:10px;font-size:13px;color:var(--c-danger)">
        ⚠ Ungeklärte Differenz über ±5 € — bitte Buchungen prüfen.
    </div>
    @endif
</div>
@endif

{{-- Saldo-Übersicht --}}
<div class="mein-card" style="margin-bottom:16px">
    <div class="mein-card-title">Kassenbestand</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div style="background:var(--c-primary);border-radius:8px;padding:12px 16px">
            <div style="font-size:11px;color:rgba(255,255,255,.8)">Soll-Kassenbestand</div>
            <div style="font-size:22px;font-weight:700;color:#fff">{{ milliToEur($soll_cents) }}</div>
        </div>
        <div style="background:var(--c-bg);border-radius:8px;padding:12px 16px;border:1px solid var(--c-border)">
            <div style="font-size:11px;color:var(--c-muted)">Einnahmen seit letztem Kassensturz</div>
            <div style="font-size:18px;font-weight:700;color:var(--c-success)">+{{ milliToEur($einnahmen) }}</div>
        </div>
        <div style="background:var(--c-bg);border-radius:8px;padding:12px 16px;border:1px solid var(--c-border)">
            <div style="font-size:11px;color:var(--c-muted)">Ausgaben seit letztem Kassensturz</div>
            <div style="font-size:18px;font-weight:700;color:var(--c-danger)">−{{ milliToEur($ausgaben) }}</div>
        </div>
        <div style="background:var(--c-bg);border-radius:8px;padding:12px 16px;border:1px solid var(--c-border)">
            <div style="font-size:11px;color:var(--c-muted)">Letzter Kassensturz</div>
            <div style="font-size:13px;font-weight:600;color:var(--c-text)">
                {{ $last_count ? $last_count->created_at->format('d.m.Y H:i') : '—' }}
            </div>
        </div>
    </div>
</div>

{{-- Buchung anlegen --}}
<div class="mein-card" style="margin-bottom:16px">
    <div class="mein-card-title">Buchung anlegen</div>
    <form method="POST" action="{{ route('mein.kasse.store') }}" id="bookingForm">
        @csrf
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <div style="display:flex;flex-direction:column;gap:4px;min-width:180px;flex:1">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Buchungstyp</label>
                <select name="booking_type" id="bookingType" onchange="toggleSafeField()"
                        style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                    <option value="customer_payment">Kundenzahlung (Einnahme)</option>
                    <option value="supplier_payment">Lieferant bezahlt (Ausgabe)</option>
                    @if($safes->count())
                    <option value="safe_deposit">Tresor-Einzahlung</option>
                    @endif
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;min-width:120px;flex:0 0 140px">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Betrag (EUR)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required
                       placeholder="0,00"
                       style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div id="safeField" style="display:none;flex-direction:column;gap:4px;min-width:160px;flex:1">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Tresor</label>
                <select name="safe_id"
                        style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                    <option value="">— bitte wählen —</option>
                    @foreach($safes as $safe)
                    <option value="{{ $safe->id }}">{{ $safe->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="noteField" style="display:flex;flex-direction:column;gap:4px;min-width:160px;flex:2">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Notiz</label>
                <input type="text" name="note" maxlength="500" placeholder="Optional"
                       style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <button type="submit"
                    style="padding:8px 18px;background:var(--c-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">
                Buchen
            </button>
        </div>
    </form>
</div>

{{-- Kassensturz --}}
<div class="mein-card" style="margin-bottom:16px;border:2px solid var(--c-border)">
    <div class="mein-card-title">Kassensturz durchführen</div>
    <p style="font-size:13px;color:var(--c-muted);margin:0 0 12px">
        Aktueller Soll-Bestand: <strong style="color:var(--c-text)">{{ milliToEur($soll_cents) }}</strong>.
        Geben Sie den tatsächlich gezählten Betrag ein.
    </p>
    <form method="POST" action="{{ route('mein.kasse.kassensturz') }}">
        @csrf
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <div style="display:flex;flex-direction:column;gap:4px;min-width:160px;flex:1">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Gezählter Ist-Betrag (EUR)</label>
                <input type="number" name="ist_betrag" step="0.01" min="0" required placeholder="0,00"
                       style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;min-width:160px;flex:1">
                <label style="font-size:12px;font-weight:500;color:var(--c-text)">Davon Trinkgeld, freiwillig (EUR)</label>
                <input type="number" name="trinkgeld" step="0.01" min="0" placeholder="0,00"
                       style="padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <button type="submit"
                    onclick="return confirm('Kassensturz jetzt durchführen?')"
                    style="padding:8px 18px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">
                Kassensturz durchführen
            </button>
        </div>
    </form>
</div>

{{-- Buchungshistorie --}}
<div class="mein-card">
    <div class="mein-card-title">Buchungshistorie (letzte 50)</div>
    @if($transactions->isEmpty())
        <p style="color:var(--c-muted);font-size:13px;text-align:center;padding:16px 0">Noch keine Buchungen.</p>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="border-bottom:1px solid var(--c-border)">
                <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Datum</th>
                <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Kategorie</th>
                <th style="padding:8px 12px;text-align:right;font-weight:500;color:var(--c-muted)">Betrag</th>
                <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Notiz</th>
            </tr>
        </thead>
        <tbody>
        @foreach($transactions as $tx)
        @php
            $isCashCount = $tx->category === 'cash_count';
            $isDeposit   = $tx->type === 'deposit';
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
                        milliToEur((int)($nd['soll_cents'] ?? 0)),
                        milliToEur((int)($nd['ist_cents'] ?? 0)),
                        milliSign((int)($nd['diff_cents'] ?? 0))
                    );
                    if (!empty($nd['trinkgeld_cents'])) {
                        $noteDisplay .= ' · TG ' . milliToEur((int)$nd['trinkgeld_cents']);
                    }
                }
            }
        @endphp
        <tr style="{{ $isCashCount ? 'background:rgba(59,130,246,.06)' : '' }};border-bottom:1px solid var(--c-border)">
            <td style="padding:8px 12px;color:var(--c-muted);white-space:nowrap;font-size:12px">
                {{ $tx->created_at->format('d.m.Y H:i') }}
            </td>
            <td style="padding:8px 12px">
                @if($isCashCount)
                    <span style="background:#dbeafe;color:#1d4ed8;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600">{{ $catLabel }}</span>
                @else
                    <span style="color:var(--c-muted);font-size:12px">{{ $catLabel }}</span>
                @endif
            </td>
            <td style="padding:8px 12px;text-align:right;font-weight:600;white-space:nowrap;color:{{ $isDeposit ? 'var(--c-success)' : 'var(--c-danger)' }}">
                {{ $isDeposit ? '+' : '−' }}{{ milliToEur($tx->amount_cents) }}
            </td>
            <td style="padding:8px 12px;color:var(--c-muted);font-size:12px">{{ $noteDisplay }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@push('scripts')
<script>
function toggleSafeField() {
    var type = document.getElementById('bookingType').value;
    document.getElementById('safeField').style.display = type === 'safe_deposit' ? 'flex' : 'none';
    document.getElementById('noteField').style.display  = type === 'safe_deposit' ? 'none' : 'flex';
}
</script>
@endpush

@endsection
