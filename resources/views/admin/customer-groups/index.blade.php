@extends('admin.layout')
@section('title', 'Kundengruppen')

@section('actions')
    <details class="actions-dropdown">
        <summary class="btn btn-outline btn-sm">Aktionen ▾</summary>
        <div class="actions-menu">
            <a href="{{ route('admin.imports.customer-groups') }}">CSV importieren</a>
        </div>
    </details>
@endsection

@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.customer-groups.store') }}" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.5rem;margin-bottom:1.5rem;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0">
            <label>Name *</label>
            <input type="text" name="name" required maxlength="150" placeholder="z.B. Gastro" value="{{ old('name') }}">
        </div>
        <div class="form-group" style="margin:0">
            <label>Preisanpassung</label>
            <select name="price_adjustment_type">
                <option value="none" @selected(old('price_adjustment_type','none')==='none')>Keine</option>
                <option value="fixed" @selected(old('price_adjustment_type')==='fixed')>Fest (€)</option>
                <option value="percent" @selected(old('price_adjustment_type')==='percent')>Prozent (BP)</option>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label>Fest-Betrag (€)</label>
            <input type="number" name="price_adjustment_fixed_eur" step="0.01" placeholder="0.00" value="{{ old('price_adjustment_fixed_eur') }}">
        </div>
        <div class="form-group" style="margin:0">
            <label>Basispunkte</label>
            <input type="number" name="price_adjustment_percent_bp" placeholder="0" value="{{ old('price_adjustment_percent_bp') }}">
            <div class="hint">100 BP = 1 %</div>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Anlegen</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Preisanpassung</th><th style="text-align:center">Gewerblich</th><th style="text-align:center">PfandFrei</th><th style="text-align:center">Aktiv</th><th style="text-align:center">Kunden</th><th style="text-align:center">Standard</th><th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $g)
                @php
                    $adjOptions = collect([['value'=>'none','label'=>'Keine'],['value'=>'fixed','label'=>'Fest (€)'],['value'=>'percent','label'=>'Prozent (BP)']])->toJson();
                    $fixedEur = number_format($g->price_adjustment_fixed_milli / 1_000_000, 2, '.', '');
                    $adjDisplay = match($g->price_adjustment_type) {
                        'fixed'   => milli_to_eur($g->price_adjustment_fixed_milli),
                        'percent' => ($g->price_adjustment_percent_basis_points / 10_000) . ' %',
                        default   => '—',
                    };
                @endphp
                <tr data-ie-url="{{ route('admin.customer-groups.update', $g) }}">
                    <td data-ie-field="name" data-ie-type="text" data-ie-value="{{ $g->name }}">{{ $g->name }}</td>
                    <td data-ie-field="price_adjustment_type" data-ie-type="select" data-ie-value="{{ $g->price_adjustment_type }}" data-ie-options="{{ $adjOptions }}">{{ $adjDisplay }}</td>
                    <td style="text-align:center" data-ie-field="is_business" data-ie-type="checkbox" data-ie-value="{{ $g->is_business ? '1' : '0' }}">{{ $g->is_business ? '✓' : '–' }}</td>
                    <td style="text-align:center" data-ie-field="is_deposit_exempt" data-ie-type="checkbox" data-ie-value="{{ $g->is_deposit_exempt ? '1' : '0' }}">{{ $g->is_deposit_exempt ? '✓' : '–' }}</td>
                    <td style="text-align:center" data-ie-field="active" data-ie-type="checkbox" data-ie-value="{{ $g->active ? '1' : '0' }}">{{ $g->active ? '✓' : '–' }}</td>
                    <td style="text-align:center"><span class="badge">{{ $g->customers_count }}</span></td>
                    <td style="text-align:center">
                        @if($g->id === $defaultGroupId)
                            <span title="Standard-Kundengruppe" style="font-size:18px">⭐</span>
                        @else
                            <form method="POST" action="{{ route('admin.customer-groups.set-default', $g) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm" title="Als Standard setzen">Als Standard</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.customer-groups.destroy', $g) }}" onsubmit="return confirm('Kundengruppe löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="color:var(--c-muted);text-align:center">Noch keine Kundengruppen angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
