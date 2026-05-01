@extends('admin.layout')

@section('title', 'Primeur – Kassenbelege')

@section('content')
<div class="page-header">
    <h1 style="margin:0;">Kassenbelege <span style="font-size:.65em;color:var(--c-muted);">Archiv</span></h1>
    <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Kasse › Belege
    </p>
</div>

<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">
    <input type="date" name="von" value="{{ request('von') }}"
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
    <input type="date" name="bis" value="{{ request('bis') }}"
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
    <input type="number" name="beleg_nr" value="{{ request('beleg_nr') }}" placeholder="Beleg-Nr."
           style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;width:120px;">
    <label style="display:flex;align-items:center;gap:.3rem;font-size:.9rem;">
        <input type="checkbox" name="mit_storno" value="1" @checked(request('mit_storno'))>
        incl. Stornos
    </label>
    <button type="submit" class="btn btn-primary">Filtern</button>
    @if(request()->hasAny(['von','bis','beleg_nr','mit_storno']))
    <a href="{{ route('admin.primeur.cash.receipts') }}" class="btn btn-outline">Reset</a>
    @endif
</form>

<div style="color:var(--c-muted);font-size:.85rem;margin-bottom:.75rem;">
    {{ number_format($receipts->total()) }} Belege (Seite {{ $receipts->currentPage() }} / {{ $receipts->lastPage() }})
</div>

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Beleg-Nr.</th>
                    <th>Kasse</th>
                    <th>Status</th>
                    <th style="text-align:right;">Gesamtbetrag</th>
                    <th style="text-align:right;">Kartenzahlung</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Pfand</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                <tr @if($r->ist_storno) style="opacity:.55;background:var(--c-bg,#f8fafc);" @endif>
                    <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($r->datum)->format('d.m.Y') }}</td>
                    <td style="font-family:monospace;">{{ $r->belegnummer ?? '—' }}</td>
                    <td>{{ $r->kassen_nr ?? '—' }}</td>
                    <td>
                        @if($r->ist_storno)
                            <span style="color:var(--c-danger,#dc2626);font-size:.8rem;">Storno</span>
                        @else
                            <span style="color:var(--c-success,#16a34a);font-size:.8rem;">OK</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-weight:600;">{{ $r->gesamtbetrag ? number_format($r->gesamtbetrag, 2, ',', '.') . ' €' : '—' }}</td>
                    <td style="text-align:right;">{{ $r->kartenzahlung ? number_format($r->kartenzahlung, 2, ',', '.') . ' €' : '—' }}</td>
                    <td style="text-align:right;">{{ $r->barbetrag ? number_format($r->barbetrag, 2, ',', '.') . ' €' : '—' }}</td>
                    <td style="text-align:right;font-size:.8rem;">{{ $r->pfandeinnahmen ? number_format($r->pfandeinnahmen, 2, ',', '.') . ' €' : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:2rem;">Keine Belege gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="margin-top:1rem;">{{ $receipts->links() }}</div>
@endsection
