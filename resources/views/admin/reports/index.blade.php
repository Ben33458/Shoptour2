@extends('admin.layout')

@section('title', 'Berichte')

@section('content')
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.reports.index') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label class="label">Von</label>
            <input class="input" type="date" name="from" value="{{ $from }}">
        </div>
        <div>
            <label class="label">Bis</label>
            <input class="input" type="date" name="to" value="{{ $to }}">
        </div>
        <button class="btn btn-primary" type="submit">Anzeigen</button>
    </form>
</div>

{{-- Tab navigation --}}
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #e5e7eb;">
    @foreach(['revenue' => 'Umsatz', 'margin' => 'Deckungsbeitrag', 'deposit' => 'Pfandkonto', 'tours' => 'Touren'] as $key => $label)
        <a href="{{ route('admin.reports.index', ['from' => $from, 'to' => $to, 'tab' => $key]) }}"
           style="padding:8px 16px;text-decoration:none;font-weight:{{ $tab === $key ? '600' : '400' }};
                  border-bottom:{{ $tab === $key ? '3px solid #3b82f6' : '3px solid transparent' }};
                  color:{{ $tab === $key ? '#3b82f6' : '#374151' }};margin-bottom:-2px;">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ── Umsatz ── --}}
@if($tab === 'revenue')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;">Umsatzbericht</h2>
        <a href="{{ route('admin.reports.export', ['type' => 'revenue', 'from' => $from, 'to' => $to]) }}"
           class="btn btn-secondary" style="font-size:13px;">CSV exportieren</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-label">Brutto</div>
            <div class="stat-value">{{ number_format($revenue['total_gross_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Netto</div>
            <div class="stat-value">{{ number_format($revenue['total_net_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">MwSt</div>
            <div class="stat-value">{{ number_format($revenue['total_tax_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rechnungen</div>
            <div class="stat-value">{{ $revenue['invoice_count'] }}</div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Rechnungsnummer</th>
                <th>Datum</th>
                <th class="text-right">Brutto</th>
                <th class="text-right">Netto</th>
                <th class="text-right">MwSt</th>
                <th class="text-right">Pfand</th>
            </tr>
        </thead>
        <tbody>
            @forelse($revenue['rows'] as $row)
            <tr>
                <td>{{ $row['invoice_number'] }}</td>
                <td>{{ $row['finalized_at'] }}</td>
                <td class="text-right">{{ number_format($row['total_gross_milli'] / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($row['total_net_milli']   / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($row['total_tax_milli']   / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($row['total_deposit_milli'] / 1_000_000, 2, ',', '.') }} €</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#6b7280;">Keine Rechnungen im Zeitraum.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ── Deckungsbeitrag ── --}}
@if($tab === 'margin')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;">Deckungsbeitrag</h2>
        <a href="{{ route('admin.reports.export', ['type' => 'margin', 'from' => $from, 'to' => $to]) }}"
           class="btn btn-secondary" style="font-size:13px;">CSV exportieren</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-label">Umsatz netto</div>
            <div class="stat-value">{{ number_format($margin['total_revenue_net_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Einkauf (EK)</div>
            <div class="stat-value">{{ number_format($margin['total_cost_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Deckungsbeitrag</div>
            <div class="stat-value" style="color:{{ $margin['total_margin_milli'] >= 0 ? '#16a34a' : '#dc2626' }};">
                {{ number_format($margin['total_margin_milli'] / 1_000_000, 2, ',', '.') }} €
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Rechnung</th>
                <th>Artikel</th>
                <th class="text-right">Menge</th>
                <th class="text-right">Umsatz netto</th>
                <th class="text-right">EK</th>
                <th class="text-right">DB</th>
            </tr>
        </thead>
        <tbody>
            @forelse($margin['rows'] as $row)
            <tr>
                <td>{{ $row['invoice_number'] ?? '–' }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="text-right">{{ number_format($row['qty'], 3, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['revenue_net_milli'] / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">
                    @if($row['cost_milli'] !== null)
                        {{ number_format($row['cost_milli'] / 1_000_000, 2, ',', '.') }} €
                    @else
                        <span style="color:#9ca3af;">–</span>
                    @endif
                </td>
                <td class="text-right" style="color:{{ ($row['margin_milli'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
                    @if($row['margin_milli'] !== null)
                        {{ number_format($row['margin_milli'] / 1_000_000, 2, ',', '.') }} €
                    @else
                        <span style="color:#9ca3af;">–</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#6b7280;">Keine Produktpositionen im Zeitraum.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ── Pfandkonto ── --}}
@if($tab === 'deposit')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;">Pfandkonto</h2>
        <a href="{{ route('admin.reports.export', ['type' => 'deposit', 'from' => $from, 'to' => $to]) }}"
           class="btn btn-secondary" style="font-size:13px;">CSV exportieren</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-label">Pfand rein</div>
            <div class="stat-value">{{ number_format($deposit['pfand_rein_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pfand raus</div>
            <div class="stat-value">{{ number_format($deposit['pfand_raus_milli'] / 1_000_000, 2, ',', '.') }} €</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Saldo</div>
            <div class="stat-value" style="color:{{ $deposit['saldo_milli'] >= 0 ? '#16a34a' : '#dc2626' }};">
                {{ number_format($deposit['saldo_milli'] / 1_000_000, 2, ',', '.') }} €
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Rechnungsnummer</th>
                <th>Datum</th>
                <th>Typ</th>
                <th>Beschreibung</th>
                <th class="text-right">Betrag</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deposit['rows'] as $row)
            <tr>
                <td>{{ $row['invoice_number'] }}</td>
                <td>{{ $row['finalized_at'] }}</td>
                <td>
                    <span class="badge {{ $row['type'] === 'pfand_rein' ? 'badge-blue' : 'badge-yellow' }}">
                        {{ $row['type'] === 'pfand_rein' ? 'Pfand rein' : 'Pfand raus' }}
                    </span>
                </td>
                <td>{{ $row['description'] }}</td>
                <td class="text-right" style="color:{{ $row['amount_milli'] >= 0 ? '#374151' : '#dc2626' }};">
                    {{ number_format($row['amount_milli'] / 1_000_000, 2, ',', '.') }} €
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Keine Pfandbewegungen im Zeitraum.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ── Touren ── --}}
@if($tab === 'tours')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;">Tourenauswertung</h2>
        <a href="{{ route('admin.reports.export', ['type' => 'tours', 'from' => $from, 'to' => $to]) }}"
           class="btn btn-secondary" style="font-size:13px;">CSV exportieren</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-label">Touren</div>
            <div class="stat-value">{{ $tours['tour_count'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Stopps gesamt</div>
            <div class="stat-value">{{ $tours['total_stops'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Abgeschlossen</div>
            <div class="stat-value">{{ $tours['finished_stops'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Übersprungen</div>
            <div class="stat-value">{{ $tours['skipped_stops'] }}</div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Tour-ID</th>
                <th>Datum</th>
                <th>Status</th>
                <th class="text-right">Stopps</th>
                <th class="text-right">Abgeschlossen</th>
                <th class="text-right">Übersprungen</th>
                <th class="text-right">Ø Stopp</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tours['rows'] as $row)
            <tr>
                <td>{{ $row['tour_id'] }}</td>
                <td>{{ $row['tour_date'] }}</td>
                <td><span class="badge">{{ $row['status'] }}</span></td>
                <td class="text-right">{{ $row['total_stops'] }}</td>
                <td class="text-right">{{ $row['finished_stops'] }}</td>
                <td class="text-right">{{ $row['skipped_stops'] }}</td>
                <td class="text-right">
                    @if($row['avg_stop_min'] !== null)
                        {{ number_format($row['avg_stop_min'], 1, ',', '.') }} min
                    @else
                        –
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#6b7280;">Keine Touren im Zeitraum.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

@endsection
