@extends('admin.layout')

@section('title', 'Primeur – Kunden')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Primeur-Kunden <span style="font-size:.65em;color:var(--c-muted);">Archiv</span></h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Kunden
        </p>
    </div>
</div>

{{-- ── Suche & Filter ───────────────────────────────────────────────────── --}}
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, Kundennr., Ort..."
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;flex:1;min-width:200px;">
    <select name="gruppe" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="">Alle Gruppen</option>
        @foreach($gruppen as $g)
        <option value="{{ $g }}" @selected(request('gruppe') === $g)>{{ $g }}</option>
        @endforeach
    </select>
    <select name="sort" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="name1" @selected(request('sort','name1')==='name1')>Name</option>
        <option value="kundennummer" @selected(request('sort')==='kundennummer')>Kundennr.</option>
        <option value="ort" @selected(request('sort')==='ort')>Ort</option>
        <option value="kundengruppe" @selected(request('sort')==='kundengruppe')>Gruppe</option>
    </select>
    <button type="submit" class="btn btn-primary">Suchen</button>
    @if(request()->hasAny(['q','gruppe','sort']))
    <a href="{{ route('admin.primeur.customers.index') }}" class="btn btn-outline">Zurücksetzen</a>
    @endif
</form>

<div style="color:var(--c-muted);font-size:.85rem;margin-bottom:.75rem;">
    {{ number_format($customers->total()) }} Kunden gefunden (Seite {{ $customers->currentPage() }} / {{ $customers->lastPage() }})
</div>

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Kundennr.</th>
                    <th>Name</th>
                    <th>Ort</th>
                    <th>PLZ</th>
                    <th>Gruppe</th>
                    <th>Telefon</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                <tr>
                    <td style="font-family:monospace;font-size:.85rem;">{{ $c->kundennummer ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.primeur.customers.show', $c->primeur_id) }}" style="font-weight:500;">
                            {{ $c->name1 }} {{ $c->name2 }}
                        </a>
                        @if($c->suchname && $c->suchname !== $c->name1)
                            <br><span style="font-size:.8rem;color:var(--c-muted);">{{ $c->suchname }}</span>
                        @endif
                    </td>
                    <td>{{ $c->ort ?? '—' }}</td>
                    <td>{{ $c->plz ?? '—' }}</td>
                    <td>{{ $c->kundengruppe ?? '—' }}</td>
                    <td style="font-size:.85rem;">{{ $c->telefon ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.primeur.customers.show', $c->primeur_id) }}" class="btn btn-sm btn-outline">Detail</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:var(--c-muted);padding:2rem;">Keine Kunden gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:1rem;">{{ $customers->links() }}</div>
@endsection
