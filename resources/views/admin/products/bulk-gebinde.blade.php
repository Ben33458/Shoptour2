@extends('admin.layout')

@section('title', 'Gebinde / Pfand-Zuweisung')

@section('actions')
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produkte</a>
    <a href="{{ route('admin.gebinde.index') }}" class="btn btn-outline btn-sm">Gebinde verwalten</a>
@endsection

@section('content')

{{-- Stats --}}
<div class="card" style="margin-bottom:16px;padding:12px 16px;display:flex;gap:24px;flex-wrap:wrap;align-items:center">
    <div style="font-size:.9em;color:var(--c-text-muted)">
        <strong>{{ number_format($filterCounts['all']) }}</strong> Produkte gesamt
    </div>
    <div style="font-size:.9em;color:var(--c-danger)">
        <strong>{{ number_format($filterCounts['missing']) }}</strong> ohne Gebinde/Pfand
    </div>
    <div style="font-size:.9em;color:var(--c-success)">
        <strong>{{ number_format($filterCounts['set']) }}</strong> mit Gebinde
    </div>
</div>

{{-- Filter bar --}}
<form method="GET" action="{{ route('admin.products.bulk-gebinde') }}" class="card" style="margin-bottom:16px;padding:14px 16px">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" name="search" value="{{ $search }}"
               class="form-control" style="max-width:240px"
               placeholder="Artikelnummer / Produktname …">

        <select name="wg" class="form-control" style="max-width:220px">
            <option value="">Alle Warengruppen</option>
            @foreach($warengruppen as $wg)
                <option value="{{ $wg->id }}" {{ $wgFilter == $wg->id ? 'selected' : '' }}>
                    {{ $wg->name }}
                </option>
            @endforeach
        </select>

        <div style="display:flex;gap:4px">
            @foreach(['missing' => 'Fehlend ('.$filterCounts['missing'].')', 'set' => 'Gesetzt ('.$filterCounts['set'].')', 'all' => 'Alle ('.$filterCounts['all'].')'] as $val => $label)
                <a href="{{ request()->fullUrlWithQuery(['filter' => $val, 'page' => 1]) }}"
                   class="btn btn-sm {{ $filter === $val ? 'btn-primary' : 'btn-outline' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary btn-sm">Suchen</button>
        @if($search || $wgFilter)
            <a href="{{ route('admin.products.bulk-gebinde') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </div>
</form>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px">{{ session('success') }}</div>
@endif

{{-- ── Auto-Zuweisung aus WaWi ── --}}
<div class="card" style="margin-bottom:12px;padding:14px 16px;background:#f0fdf4;border:1px solid #86efac">
    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <strong style="color:#166534;font-size:.95em">Auto-Zuweisung aus WaWi-Pfandbetrag</strong>
            <p style="font-size:.82em;color:#166534;margin:4px 0 0">
                Liest den <em>Pfandbetrag</em> aus den WaWi-Artikelattributen und ordnet automatisch das
                passende Gebinde zu. Nur Produkte <strong>ohne Gebinde</strong> werden verändert.
            </p>
        </div>
        <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;font-size:.85em">
            <div style="text-align:center">
                <div style="font-size:1.4em;font-weight:700;color:#166534">{{ $autoPreview['assignable'] }}</div>
                <div style="color:#166534">zuweisbar</div>
            </div>
            <div style="text-align:center">
                <div style="font-size:1.4em;font-weight:700;color:#6b7280">{{ $autoPreview['no_pfand'] }}</div>
                <div style="color:#6b7280">kein Pfand (Einweg)</div>
            </div>
            @if(!empty($autoPreview['unmatched']))
            <div style="text-align:center">
                <div style="font-size:1.4em;font-weight:700;color:#b45309">{{ array_sum($autoPreview['unmatched']) }}</div>
                <div style="color:#b45309" title="{{ collect($autoPreview['unmatched'])->map(fn($c,$v)=>"{$c}× {$v}")->implode(', ') }}">
                    kein Gebinde ⚠
                </div>
            </div>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.products.bulk-gebinde.auto-assign') }}"
              onsubmit="return confirm('{{ $autoPreview['assignable'] }} Produkte automatisch mit Gebinde aus WaWi-Pfandbetrag versehen?')">
            @csrf
            <button type="submit" class="btn btn-sm"
                    style="background:#16a34a;color:#fff;border:none;white-space:nowrap"
                    {{ $autoPreview['assignable'] == 0 ? 'disabled' : '' }}>
                Jetzt {{ $autoPreview['assignable'] }} Produkte zuweisen
            </button>
        </form>
    </div>
    @if(!empty($autoPreview['unmatched']))
    <div style="margin-top:8px;font-size:.8em;color:#b45309;padding:6px 10px;background:#fef3c7;border-radius:4px">
        Kein passendes Gebinde für:
        @foreach($autoPreview['unmatched'] as $eur => $cnt)
            <strong>{{ $cnt }}× {{ $eur }}</strong>{{ !$loop->last ? ' · ' : '' }}
        @endforeach
        — bitte Gebinde manuell anlegen oder zuweisen.
    </div>
    @endif
</div>

{{-- Bulk assign bar --}}
<div class="card" style="margin-bottom:12px;padding:12px 16px;background:#fffbeb;border:1px solid #fcd34d">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:.9em">
        <strong style="color:#92400e">Schnellzuweisung:</strong>
        <span style="color:#78350f">Gebinde für alle sichtbaren Produkte auf einmal setzen:</span>
        <select id="bulk-gebinde-select" class="form-control" style="max-width:280px;font-size:.9em">
            <option value="">— Gebinde wählen —</option>
            @foreach($gebindeOptions as $g)
                <option value="{{ $g->id }}">
                    {{ $g->name }}{{ $g->pfandSet ? ' (Pfand: '.$g->pfandSet->name.')' : ' (kein Pfand)' }}
                </option>
            @endforeach
        </select>
        <button type="button" onclick="applyBulkGebinde()"
                class="btn btn-sm" style="background:#f59e0b;color:#fff;border:none">
            Auf alle anwenden
        </button>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.bulk-gebinde.update') }}" id="main-form">
    @csrf

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>{{ $products->total() }} Produkte{{ $products->total() !== $filterCounts['all'] ? ' (gefiltert)' : '' }}</span>
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:110px">Art.-Nr.</th>
                        <th>Produktname</th>
                        <th style="width:150px">Warengruppe</th>
                        <th style="width:300px">Gebinde / Pfand</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr style="{{ $product->gebinde_id ? '' : 'background:#fff7f7' }}">
                            <td style="font-family:monospace;font-size:.85em">{{ $product->artikelnummer }}</td>
                            <td>
                                <a href="{{ route('admin.products.edit', $product->id) }}" target="_blank"
                                   style="color:inherit;text-decoration:none" title="Produkt bearbeiten">
                                    {{ $product->produktname }}
                                </a>
                                @if(!$product->active)
                                    <span style="font-size:.75em;background:#fee2e2;color:#b91c1c;padding:1px 6px;border-radius:4px;margin-left:4px">inaktiv</span>
                                @endif
                                @if(!$product->show_in_shop)
                                    <span style="font-size:.75em;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;margin-left:4px">nicht im Shop</span>
                                @endif
                            </td>
                            <td style="font-size:.85em;color:var(--c-text-muted)">
                                {{ $product->warengruppe?->name ?? '–' }}
                            </td>
                            <td>
                                <select name="gebinde[{{ $product->id }}]"
                                        class="gebinde-select form-control"
                                        style="font-size:.85em;padding:3px 6px;{{ $product->gebinde_id ? 'background:#f0fdf4;' : 'border-color:#fca5a5;' }}">
                                    <option value="">— kein Gebinde —</option>
                                    @foreach($gebindeOptions as $g)
                                        <option value="{{ $g->id }}" {{ $product->gebinde_id == $g->id ? 'selected' : '' }}>
                                            {{ $g->name }}{{ $g->pfandSet ? ' · '.$g->pfandSet->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--c-text-muted);padding:32px">
                                Keine Produkte gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div style="padding:12px 16px;border-top:1px solid var(--c-border)">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif

        <div style="padding:12px 16px;border-top:1px solid var(--c-border);text-align:right">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
function applyBulkGebinde() {
    var val = document.getElementById('bulk-gebinde-select').value;
    if (!val) { alert('Bitte zuerst ein Gebinde auswählen.'); return; }
    var selects = document.querySelectorAll('.gebinde-select');
    selects.forEach(function(s) {
        s.value = val;
        s.style.background = '#f0fdf4';
        s.style.borderColor = '';
    });
}
</script>
@endpush
