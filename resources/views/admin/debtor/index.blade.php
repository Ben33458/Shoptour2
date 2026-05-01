@extends('admin.layout')

@section('title', 'Offene Posten / Mahnwesen')

@section('actions')
    <a href="{{ route('admin.dunning.create') }}" class="btn btn-primary btn-sm">+ Mahnlauf erstellen</a>
    <a href="{{ route('admin.dunning.index') }}" class="btn btn-outline btn-sm">Mahnläufe</a>
    <a href="{{ route('admin.settings.dunning.edit') }}" class="btn btn-outline btn-sm">Einstellungen</a>
@endsection

@section('content')

@if(session('success'))
<div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
    {{ session('success') }}
</div>
@endif

{{-- ── KPI-Leiste ── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    <div class="card" style="padding:16px">
        <div class="hint">Offene Forderungen gesamt</div>
        <div style="font-size:22px;font-weight:700;color:#dc2626">
            {{ number_format($grandTotalOpen / 1_000_000, 2, ',', '.') }} €
        </div>
    </div>
    <div class="card" style="padding:16px">
        <div class="hint">Kunden mit offenen Posten</div>
        <div style="font-size:22px;font-weight:700">{{ $customers->count() }}</div>
    </div>
    <div class="card" style="padding:16px">
        <div class="hint">Davon im Hold / Klärfall</div>
        <div style="font-size:22px;font-weight:700;color:#f59e0b">
            {{ $customers->where('debt_hold', true)->count() }}
        </div>
    </div>
    <div class="card" style="padding:16px">
        <div class="hint">Mit Liefersperre</div>
        <div style="font-size:22px;font-weight:700;color:#ef4444">
            {{ $customers->where('delivery_status', 'blocked')->count() }}
        </div>
    </div>
</div>

{{-- ── Filter ── --}}
<div class="card" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="">Alle Kunden</option>
            <option value="dunnable" @selected(request('filter')=='dunnable')>Nur mahnfähig</option>
            <option value="hold" @selected(request('filter')=='hold')>Nur Hold / Klärfall</option>
            <option value="b2b" @selected(request('filter')=='b2b')>Nur Gewerblich</option>
            <option value="b2c" @selected(request('filter')=='b2c')>Nur Privat</option>
            <option value="risk_high" @selected(request('filter')=='risk_high')>Hohes/Kritisches Risiko</option>
        </select>

        <select name="level" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="">Alle Mahnstufen</option>
            <option value="0" @selected(request('level')==='0')>Stufe 0 (nicht gemahnt)</option>
            <option value="1" @selected(request('level')==='1')>Stufe 1</option>
            <option value="2" @selected(request('level')==='2')>Stufe 2</option>
        </select>

        <select name="sort" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="open_desc" @selected($sort=='open_desc')>↓ Offener Betrag</option>
            <option value="oldest_due" @selected($sort=='oldest_due')>Älteste Fälligkeit</option>
            <option value="overdue_desc" @selected($sort=='overdue_desc')>↓ Tage überfällig</option>
            <option value="level_desc" @selected($sort=='level_desc')>↓ Mahnstufe</option>
        </select>

        @if(request()->hasAny(['filter','level','sort']))
            <a href="{{ route('admin.debtor.index') }}" class="btn btn-outline btn-sm">Filter zurücksetzen</a>
        @endif
    </form>
</div>

{{-- ── Tabelle ── --}}
<div class="card">
    <div class="card-header">
        Debitoren ({{ $customers->count() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kundennr.</th>
                    <th>Kunde</th>
                    <th>Typ</th>
                    <th class="text-right">Offen gesamt</th>
                    <th class="text-right">Rechnungen</th>
                    <th>Älteste Fälligkeit</th>
                    <th>Tage überfällig</th>
                    <th>Mahnstufe</th>
                    <th>Lieferstatus</th>
                    <th>Hold</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                @php
                    $m = $metrics[$customer->id] ?? [];
                    $openTotal   = $m['open_total_milli'] ?? 0;
                    $openCount   = $m['open_count'] ?? 0;
                    $daysOverdue = $m['days_overdue'] ?? 0;
                    $level       = $m['dunning_level'] ?? 0;
                    $isOverdue   = $daysOverdue > 0;
                @endphp
                <tr @if($isOverdue) style="background:#fef2f2" @endif>
                    <td>
                        <code>{{ $customer->customer_number }}</code>
                    </td>
                    <td>
                        <a href="{{ route('admin.debtor.show', $customer) }}">
                            {{ $customer->displayName() }}
                        </a>
                    </td>
                    <td>
                        @if($customer->isB2B())
                            <span class="badge" style="background:#dbeafe;color:#1e40af">Gewerbe</span>
                        @else
                            <span class="badge" style="background:#f3f4f6;color:#374151">Privat</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight:600;color:#dc2626">
                        {{ number_format($openTotal / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right">{{ $openCount }}</td>
                    <td>
                        @if($m['oldest_due_date'] ?? null)
                            {{ \Carbon\Carbon::parse($m['oldest_due_date'])->format('d.m.Y') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($daysOverdue > 0)
                            <span style="color:#dc2626;font-weight:600">{{ $daysOverdue }} Tage</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($level > 0)
                            <span class="badge" style="background:#fef3c7;color:#92400e">Stufe {{ $level }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($customer->delivery_status === 'blocked')
                            <span class="badge badge-cancelled">Sperre</span>
                        @elseif($customer->delivery_status === 'warning')
                            <span class="badge" style="background:#fef3c7;color:#92400e">Warnung</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($customer->debt_hold)
                            <span class="badge badge-cancelled">Hold</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.debtor.show', $customer) }}"
                           class="btn btn-outline btn-sm">Detail</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted" style="padding:24px">
                        Keine Kunden mit offenen Posten.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
