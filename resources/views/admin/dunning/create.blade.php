@extends('admin.layout')

@section('title', 'Neuer Mahnlauf')

@section('actions')
    <a href="{{ route('admin.dunning.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')

@if($proposals->isEmpty())
    <div class="card" style="padding:32px;text-align:center;color:var(--c-muted)">
        Keine mahnfähigen Kunden gefunden.<br>
        <span style="font-size:13px">Alle Kunden haben entweder Hold, Klärfall, gesperrte Rechnungen oder die Fälligkeitsgrenzen sind noch nicht erreicht.</span>
    </div>
@else

<form method="POST" action="{{ route('admin.dunning.store') }}">
@csrf

<div class="card" style="margin-bottom:16px;padding:16px 20px">
    <div style="display:flex;gap:16px;align-items:center">
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="test_mode" value="1"> Testmodus (keine echten E-Mails)
        </label>
        <input type="text" name="notes" class="form-control" style="max-width:320px"
            placeholder="Interne Notiz (optional)">
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Mahnvorschläge ({{ $proposals->count() }})</span>
        <div style="display:flex;gap:8px">
            <button type="button" onclick="toggleAll(true)" class="btn btn-outline btn-sm">Alle auswählen</button>
            <button type="button" onclick="toggleAll(false)" class="btn btn-outline btn-sm">Alle abwählen</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th>Kunde</th>
                    <th>Typ</th>
                    <th>Kanal</th>
                    <th>Vorgeschlagene Stufe</th>
                    <th class="text-right">Offen</th>
                    <th class="text-right">Zinsen</th>
                    <th class="text-right">Pauschale</th>
                    <th>Empfänger</th>
                    <th>Rechnungen</th>
                </tr>
            </thead>
            <tbody>
            @foreach($proposals as $p)
                @php $c = $p['customer']; @endphp
                <tr>
                    <td>
                        <input type="checkbox" name="customer_ids[]" value="{{ $c->id }}" checked class="proposal-cb">
                    </td>
                    <td>
                        <a href="{{ route('admin.debtor.show', $c) }}" target="_blank">
                            {{ $c->displayName() }}
                        </a><br>
                        <span class="text-muted" style="font-size:11px">{{ $c->customer_number }}</span>
                    </td>
                    <td>
                        @if($c->isB2B())
                            <span style="color:#1e40af">B2B</span>
                        @else
                            <span class="text-muted">B2C</span>
                        @endif
                    </td>
                    <td>
                        @if($p['channel'] === 'post')
                            <span class="badge" style="background:#dbeafe;color:#1e40af">Briefpost</span>
                        @else
                            <span class="badge" style="background:#d1fae5;color:#065f46">E-Mail</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:#fef3c7;color:#92400e">Stufe {{ $p['proposed_level'] }}</span>
                    </td>
                    <td class="text-right" style="color:#dc2626;font-weight:600">
                        {{ number_format($p['open_total_milli'] / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right">
                        @if($p['interest_milli'] > 0)
                            {{ number_format($p['interest_milli'] / 1_000_000, 2, ',', '.') }} €
                        @elseif($p['proposed_level'] === 1)
                            <span class="text-muted" title="Keine Zinsen bei Stufe 1">—</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($p['flat_fee_milli'] > 0)
                            {{ number_format($p['flat_fee_milli'] / 1_000_000, 2, ',', '.') }} €
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-size:12px">{{ $p['recipient_email'] ?? '—' }}</td>
                    <td>
                        {{ $p['vouchers']->count() }} Rechnung(en)
                        @if(!empty($p['interest_breakdown']))
                            <details style="margin-top:4px;font-size:11px">
                                <summary class="text-muted" style="cursor:pointer">Zinsaufschlüsselung</summary>
                                <table style="width:100%;margin-top:4px;border-collapse:collapse">
                                    @foreach($p['interest_breakdown'] as $b)
                                    <tr>
                                        <td style="padding:2px 4px">{{ $b['voucher_number'] }}</td>
                                        <td style="padding:2px 4px" class="text-muted">{{ $b['days_overdue'] }}d</td>
                                        <td style="padding:2px 4px;text-align:right">{{ number_format($b['interest_milli']/1_000_000,2,',','.') }} €</td>
                                    </tr>
                                    @endforeach
                                </table>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div style="display:flex;gap:12px;align-items:center">
    <button type="submit" class="btn btn-primary">
        Mahnlauf als Entwurf erstellen →
    </button>
    <span class="text-muted" style="font-size:13px">
        Der Mahnlauf wird zunächst als Entwurf gespeichert. E-Mails werden erst nach dem Ausführen versendet.
    </span>
</div>

</form>

@endif

<script>
function toggleAll(checked) {
    document.querySelectorAll('.proposal-cb').forEach(cb => cb.checked = checked);
}
</script>

@endsection
