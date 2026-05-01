@extends('admin.layout')

@section('title', 'Primeur – Kundenumsatz-Ranking')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Kundenumsatz-Ranking</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Statistik › Kunden
        </p>
    </div>
    <a href="{{ route('admin.primeur.stats.customers.export', request()->all()) }}" class="btn btn-outline">CSV Export</a>
</div>

<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">
    <select name="jahr" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="">Alle Jahre</option>
        @foreach($years as $y)
        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
        @endforeach
    </select>
    <select name="limit" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        @foreach([25, 50, 100, 200] as $l)
        <option value="{{ $l }}" @selected($l == $limit)>Top {{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary">Anzeigen</button>
</form>

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.9rem;">
            <thead>
                <tr>
                    <th style="text-align:center;">#</th>
                    <th>Kundennr.</th>
                    <th>Name</th>
                    <th>Ort</th>
                    <th style="text-align:right;">Aufträge</th>
                    <th style="text-align:right;">Umsatz gesamt</th>
                    <th style="text-align:right;">Ø Bon</th>
                    <th>Erster Kauf</th>
                    <th>Letzter Kauf</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($topCustomers as $i => $c)
                <tr>
                    <td style="text-align:center;color:var(--c-muted);font-size:.85rem;">{{ $i + 1 }}</td>
                    <td style="font-family:monospace;font-size:.85rem;">{{ $c->kundennummer ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.primeur.customers.show', $c->primeur_id) }}" style="font-weight:500;">
                            {{ $c->name1 }} {{ $c->name2 }}
                        </a>
                    </td>
                    <td style="font-size:.85rem;">{{ $c->ort ?? '—' }}</td>
                    <td style="text-align:right;">{{ number_format($c->anzahl_belege) }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($c->umsatz_gesamt, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ $c->avg_bon ? number_format($c->avg_bon, 2, ',', '.') . ' €' : '—' }}</td>
                    <td style="font-size:.8rem;">{{ $c->erster_kauf ? \Carbon\Carbon::parse($c->erster_kauf)->format('d.m.Y') : '—' }}</td>
                    <td style="font-size:.8rem;">{{ $c->letzter_kauf ? \Carbon\Carbon::parse($c->letzter_kauf)->format('d.m.Y') : '—' }}</td>
                    <td><a href="{{ route('admin.primeur.customers.show', $c->primeur_id) }}" class="btn btn-sm btn-outline">Detail</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
