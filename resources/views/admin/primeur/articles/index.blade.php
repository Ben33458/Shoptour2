@extends('admin.layout')

@section('title', 'Primeur – Artikel-Übersicht')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Artikel-Übersicht</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Artikel
        </p>
    </div>
    <a href="{{ route('admin.primeur.stats.articles') }}" class="btn btn-outline">Artikel-Ranking →</a>
</div>

{{-- ── Filter ─────────────────────────────────────────────────────────────── --}}
<form method="GET" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">

        {{-- Freitextsuche --}}
        <div style="flex:1;min-width:180px;">
            <label style="display:block;font-size:.8rem;color:var(--c-muted);margin-bottom:.25rem;">Suche</label>
            <input type="text" name="suche" value="{{ $search }}"
                   placeholder="Bezeichnung, Artikelnr. …"
                   style="width:100%;padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;font-size:.9rem;">
        </div>

        {{-- Warengruppe --}}
        <div style="min-width:160px;">
            <label style="display:block;font-size:.8rem;color:var(--c-muted);margin-bottom:.25rem;">Warengruppe</label>
            <select name="warengruppe" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;font-size:.9rem;width:100%;">
                <option value="">Alle</option>
                @foreach($warengruppen as $g)
                <option value="{{ $g }}" @selected($g === $wg)>{{ $g }}</option>
                @endforeach
            </select>
        </div>

        {{-- Warenuntergruppe --}}
        <div style="min-width:160px;">
            <label style="display:block;font-size:.8rem;color:var(--c-muted);margin-bottom:.25rem;">Untergruppe</label>
            <select name="warenuntergruppe" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;font-size:.9rem;width:100%;">
                <option value="">Alle</option>
                @foreach($warenuntergruppen as $ug)
                <option value="{{ $ug }}" @selected($ug === $wug)>{{ $ug }}</option>
                @endforeach
            </select>
        </div>

        {{-- Hersteller --}}
        <div style="min-width:140px;">
            <label style="display:block;font-size:.8rem;color:var(--c-muted);margin-bottom:.25rem;">Hersteller</label>
            <select name="hersteller" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;font-size:.9rem;width:100%;">
                <option value="">Alle</option>
                @foreach($herstellers as $h)
                <option value="{{ $h }}" @selected($h === $hersteller)>{{ $h }}</option>
                @endforeach
            </select>
        </div>

        {{-- MwSt-Satz --}}
        <div style="min-width:110px;">
            <label style="display:block;font-size:.8rem;color:var(--c-muted);margin-bottom:.25rem;">MwSt-Satz</label>
            <select name="mwst" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;font-size:.9rem;width:100%;">
                <option value="">Alle</option>
                @foreach($mwstSaetze as $s)
                <option value="{{ $s }}" @selected((string)$s === (string)$mwst)>{{ number_format($s * 100, 0) }} %</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;gap:.4rem;align-items:flex-end;">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.primeur.articles.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

{{-- ── Aktive Filter-Badges ────────────────────────────────────────────────── --}}
@if($search || $wg || $wug || $hersteller || $mwst !== null && $mwst !== '')
<div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.75rem;font-size:.82rem;">
    @if($search)  <span style="background:#eff6ff;color:#1d4ed8;padding:.2rem .6rem;border-radius:4px;">Suche: {{ $search }}</span> @endif
    @if($wg)      <span style="background:#eff6ff;color:#1d4ed8;padding:.2rem .6rem;border-radius:4px;">Warengruppe: {{ $wg }}</span> @endif
    @if($wug)     <span style="background:#eff6ff;color:#1d4ed8;padding:.2rem .6rem;border-radius:4px;">Untergruppe: {{ $wug }}</span> @endif
    @if($hersteller) <span style="background:#eff6ff;color:#1d4ed8;padding:.2rem .6rem;border-radius:4px;">Hersteller: {{ $hersteller }}</span> @endif
    @if($mwst !== null && $mwst !== '') <span style="background:#eff6ff;color:#1d4ed8;padding:.2rem .6rem;border-radius:4px;">MwSt: {{ number_format($mwst * 100, 0) }} %</span> @endif
</div>
@endif

{{-- ── Ergebnis-Info ────────────────────────────────────────────────────────── --}}
<p style="font-size:.85rem;color:var(--c-muted);margin:.25rem 0 .75rem;">
    {{ number_format($articles->total()) }} Artikel gefunden
    @if($articles->lastPage() > 1) — Seite {{ $articles->currentPage() }} / {{ $articles->lastPage() }} @endif
</p>

{{-- ── Tabelle ──────────────────────────────────────────────────────────────── --}}
@php
    function thLink(string $col, string $label, string $sort, string $dir, string $flip, array $params): string {
        $active = $col === $sort;
        $arrow  = $active ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        $params = array_merge($params, ['sort' => $col, 'dir' => $active ? $flip : 'asc']);
        $url    = request()->url() . '?' . http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        $style  = 'text-align:right;white-space:nowrap;cursor:pointer;user-select:none;' . ($active ? 'color:var(--c-primary,#2563eb);' : '');
        return "<th style=\"{$style}\"><a href=\"{$url}\" style=\"color:inherit;text-decoration:none;\">{$label}{$arrow}</a></th>";
    }
    $p = ['suche' => $search ?: null, 'warengruppe' => $wg, 'warenuntergruppe' => $wug,
          'hersteller' => $hersteller, 'mwst' => ($mwst !== '' ? $mwst : null)];
@endphp

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    @php
                        function th(string $col, string $label, string $align, string $sort, string $dir, string $flip, array $p): string {
                            $active = $col === $sort;
                            $arrow  = $active ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
                            $np     = array_merge($p, ['sort' => $col, 'dir' => $active ? $flip : ($align === 'right' ? 'desc' : 'asc')]);
                            $url    = request()->url() . '?' . http_build_query(array_filter($np, fn($v) => $v !== null && $v !== ''));
                            $st     = "text-align:{$align};white-space:nowrap;" . ($active ? 'color:var(--c-primary,#2563eb);' : '');
                            return "<th style=\"{$st}\"><a href=\"{$url}\" style=\"color:inherit;text-decoration:none;\">{$label}{$arrow}</a></th>";
                        }
                    @endphp
                    {!! th('artikelnummer',   'Art.-Nr.',       'left',  $sort, $dir, $flip, $p) !!}
                    {!! th('bezeichnung',     'Bezeichnung',    'left',  $sort, $dir, $flip, $p) !!}
                    {!! th('warengruppe',     'Warengruppe',    'left',  $sort, $dir, $flip, $p) !!}
                    {!! th('warenuntergruppe','Untergruppe',    'left',  $sort, $dir, $flip, $p) !!}
                    {!! th('hersteller',      'Hersteller',     'left',  $sort, $dir, $flip, $p) !!}
                    {!! th('mwst_dom',        'MwSt',           'right', $sort, $dir, $flip, $p) !!}
                    {!! th('menge_gesamt',    'Menge',          'right', $sort, $dir, $flip, $p) !!}
                    {!! th('umsatz',          'Umsatz',         'right', $sort, $dir, $flip, $p) !!}
                    <th style="text-align:right;white-space:nowrap;">Letzter Verk.</th>
                    <th style="text-align:center;">Aktiv</th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $a)
                <tr>
                    <td style="color:var(--c-muted);font-size:.8rem;white-space:nowrap;">{{ $a->artikelnummer }}</td>
                    <td>
                        <a href="{{ route('admin.primeur.articles.show', $a->primeur_id) }}" style="font-weight:500;">{{ $a->bezeichnung }}</a>
                        @if($a->zusatz)
                        <span style="color:var(--c-muted);font-size:.8rem;"> · {{ $a->zusatz }}</span>
                        @endif
                        @if($a->inhalt && $a->masseinheit)
                        <span style="color:var(--c-muted);font-size:.78rem;display:block;">{{ number_format($a->inhalt, 2, ',', '.') }} {{ $a->masseinheit }}</span>
                        @endif
                    </td>
                    <td style="font-size:.82rem;">
                        @if($a->warengruppe)
                        <a href="{{ route('admin.primeur.articles.warengruppe', urlencode($a->warengruppe)) }}" style="color:inherit;">{{ $a->warengruppe }}</a>
                        @else – @endif
                    </td>
                    <td style="font-size:.82rem;color:var(--c-muted);">
                        @if($a->warengruppe && $a->warenuntergruppe)
                        <a href="{{ route('admin.primeur.articles.untergruppe', [urlencode($a->warengruppe), urlencode($a->warenuntergruppe)]) }}" style="color:inherit;">{{ $a->warenuntergruppe }}</a>
                        @else – @endif
                    </td>
                    <td style="font-size:.82rem;">
                        @if($a->hersteller)
                        <a href="{{ route('admin.primeur.articles.hersteller', urlencode($a->hersteller)) }}" style="color:inherit;">{{ $a->hersteller }}</a>
                        @else – @endif
                    </td>
                    <td style="text-align:right;font-size:.82rem;">
                        @if($a->mwst_dom !== null)
                        <span style="background:{{ $a->mwst_dom == 0.07 ? '#fef9c3' : '#f0fdf4' }};color:{{ $a->mwst_dom == 0.07 ? '#854d0e' : '#166534' }};padding:.1rem .4rem;border-radius:3px;font-size:.78rem;white-space:nowrap;">
                            {{ number_format($a->mwst_dom * 100, 0) }} %
                        </span>
                        @else
                        <span style="color:var(--c-muted);">–</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        @if($a->menge_gesamt > 0)
                        {{ number_format($a->menge_gesamt, 0, ',', '.') }}
                        @else
                        <span style="color:var(--c-muted);">–</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-weight:{{ $a->umsatz > 0 ? '600' : 'normal' }};">
                        @if($a->umsatz > 0)
                        {{ number_format($a->umsatz, 2, ',', '.') }} €
                        @else
                        <span style="color:var(--c-muted);">–</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-size:.8rem;color:var(--c-muted);white-space:nowrap;">
                        {{ $a->letzter_verkauf ? \Carbon\Carbon::parse($a->letzter_verkauf)->format('d.m.Y') : '–' }}
                    </td>
                    <td style="text-align:center;">
                        @if($a->aktiv)
                        <span style="color:var(--c-success,#16a34a);">✓</span>
                        @else
                        <span style="color:var(--c-muted);">–</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;color:var(--c-muted);padding:2rem;">
                        Keine Artikel gefunden.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Pagination ───────────────────────────────────────────────────────────── --}}
@if($articles->lastPage() > 1)
<div style="margin-top:1rem;">{{ $articles->links() }}</div>
@endif

@endsection
