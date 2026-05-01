@extends('admin.layout')

@section('title', 'Primeur – Artikel-Ranking')

@section('content')
<div class="page-header">
    <h1 style="margin:0;">Artikel-Ranking (Kassenbelege)</h1>
    <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Statistik › Artikel
    </p>
</div>

<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">
    <select name="jahr" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="">Alle Jahre</option>
        @foreach($years as $y)
        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
        @endforeach
    </select>
    <select name="limit" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        @foreach([25, 50, 100] as $l)
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
                    <th>Artikel</th>
                    <th style="text-align:right;">Menge</th>
                    <th style="text-align:right;">Umsatz</th>
                    <th style="text-align:right;">Belege</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topArticles as $i => $a)
                <tr>
                    <td style="text-align:center;color:var(--c-muted);font-size:.85rem;">{{ $i + 1 }}</td>
                    <td>
                        @if($a->artikel_id)
                        <a href="{{ route('admin.primeur.articles.show', $a->artikel_id) }}">{{ $a->artikel_bezeichnung ?? 'Unbekannt' }}</a>
                        @else {{ $a->artikel_bezeichnung ?? 'Unbekannt' }}
                        @endif
                    </td>
                    <td style="text-align:right;">{{ number_format($a->menge_gesamt, 2, ',', '.') }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($a->umsatz, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($a->anzahl_belege) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
