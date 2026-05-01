@extends('admin.layout')

@section('title', 'Primeur – Aufträge')

@section('content')
<div class="page-header">
    <h1 style="margin:0;">Primeur-Aufträge <span style="font-size:.65em;color:var(--c-muted);">Archiv</span></h1>
    <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Aufträge
    </p>
</div>

{{-- ── Filter ───────────────────────────────────────────────────────────── --}}
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Kunde, Beleg-Nr...."
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;flex:1;min-width:200px;">
    <select name="art" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="">Alle Arten</option>
        @foreach($arten as $a)
        <option value="{{ $a }}" @selected(request('art') === $a)>{{ $a }}</option>
        @endforeach
    </select>
    <input type="date" name="von" value="{{ request('von') }}"
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
    <input type="date" name="bis" value="{{ request('bis') }}"
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
    <label style="display:flex;align-items:center;gap:.3rem;font-size:.9rem;white-space:nowrap;">
        <input type="checkbox" name="mit_storno" value="1" @checked(request('mit_storno'))>
        incl. Stornos
    </label>
    <button type="submit" class="btn btn-primary">Filtern</button>
    @if(request()->hasAny(['q','art','von','bis','mit_storno']))
    <a href="{{ route('admin.primeur.orders.index') }}" class="btn btn-outline">Reset</a>
    @endif
</form>

<div style="color:var(--c-muted);font-size:.85rem;margin-bottom:.75rem;">
    {{ number_format($orders->total()) }} Aufträge (Seite {{ $orders->currentPage() }} / {{ $orders->lastPage() }})
</div>

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th><a href="?{{ http_build_query(array_merge(request()->all(), ['sort'=>'belegdatum','dir'=>request('sort')==='belegdatum'&&request('dir')==='asc'?'desc':'asc'])) }}">Datum</a></th>
                    <th>Beleg-Nr.</th>
                    <th>Art</th>
                    <th>Kunde</th>
                    <th>Tour</th>
                    <th style="text-align:right;"><a href="?{{ http_build_query(array_merge(request()->all(), ['sort'=>'endbetrag','dir'=>request('sort')==='endbetrag'&&request('dir')==='asc'?'desc':'asc'])) }}">Betrag</a></th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr @if($o->storno) style="opacity:.55;" @endif>
                    <td style="white-space:nowrap;">{{ $o->belegdatum ? \Carbon\Carbon::parse($o->belegdatum)->format('d.m.Y') : '—' }}</td>
                    <td style="font-family:monospace;">{{ $o->beleg_nr ?? '—' }}</td>
                    <td><span style="font-size:.8rem;background:var(--c-bg,#f8fafc);padding:.1rem .4rem;border-radius:4px;border:1px solid var(--c-border);">{{ $o->auftragsart ?? '—' }}</span></td>
                    <td>
                        @if($o->kunden_id)
                            <a href="{{ route('admin.primeur.customers.show', $o->kunden_id) }}" style="font-size:.9rem;">
                                {{ $o->name1 ?? '' }} {{ $o->name2 ?? '' }}
                            </a>
                            @if($o->kundennummer)
                                <br><span style="font-size:.75rem;color:var(--c-muted);">{{ $o->kundennummer }}</span>
                            @endif
                        @else
                            <span style="color:var(--c-muted);">Laufkunde</span>
                        @endif
                    </td>
                    <td style="font-size:.85rem;">{{ $o->tour ?? '—' }}</td>
                    <td style="text-align:right;font-weight:600;">{{ $o->endbetrag ? number_format($o->endbetrag, 2, ',', '.') . ' €' : '—' }}</td>
                    <td>
                        @if($o->storno) <span style="color:var(--c-danger,#dc2626);font-size:.8rem;">Storno</span>
                        @else <span style="color:var(--c-success,#16a34a);font-size:.8rem;">{{ $o->status ?? 'OK' }}</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.primeur.orders.show', $o->id) }}" class="btn btn-sm btn-outline">Detail</a></td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:2rem;">Keine Aufträge gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="margin-top:1rem;">{{ $orders->links() }}</div>
@endsection
