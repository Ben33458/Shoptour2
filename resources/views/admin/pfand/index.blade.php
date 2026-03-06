@extends('admin.layout')
@section('title', 'Pfandpositionen')
@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.pfand-items.store') }}" style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0;flex:2;min-width:180px">
            <label>Bezeichnung *</label>
            <input type="text" name="bezeichnung" required maxlength="150" placeholder="z.B. 0,5L Mehrwegflasche" value="{{ old('bezeichnung') }}">
        </div>
        <div class="form-group" style="margin:0;min-width:120px">
            <label>Typ *</label>
            <select name="pfand_typ" required>
                <option value="Mehrweg" @selected(old('pfand_typ')!=='Einweg')>Mehrweg</option>
                <option value="Einweg" @selected(old('pfand_typ')==='Einweg')>Einweg</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:120px">
            <label>Wert brutto (€) *</label>
            <input type="number" name="wert_brutto_eur" step="0.01" min="0" required placeholder="0.15" value="{{ old('wert_brutto_eur') }}">
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Anlegen</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bezeichnung</th><th>Typ</th><th>Wert brutto</th><th style="text-align:center">Aktiv</th><th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pfandItems as $item)
                @php
                    $typOptions = collect([['value'=>'Mehrweg','label'=>'Mehrweg'],['value'=>'Einweg','label'=>'Einweg']])->toJson();
                    $eurValue = number_format($item->wert_brutto_milli / 1_000_000, 2, '.', '');
                @endphp
                <tr data-ie-url="{{ route('admin.pfand-items.update', $item) }}">
                    <td data-ie-field="bezeichnung" data-ie-type="text" data-ie-value="{{ $item->bezeichnung }}">{{ $item->bezeichnung }}</td>
                    <td data-ie-field="pfand_typ" data-ie-type="select" data-ie-value="{{ $item->pfand_typ }}" data-ie-options="{{ $typOptions }}">{{ $item->pfand_typ }}</td>
                    <td data-ie-field="wert_brutto_eur" data-ie-type="money" data-ie-value="{{ $eurValue }}"></td>
                    <td style="text-align:center" data-ie-field="active" data-ie-type="checkbox" data-ie-value="{{ $item->active ? '1' : '0' }}">{{ $item->active ? '✓' : '–' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.pfand-items.destroy', $item) }}" onsubmit="return confirm('Pfandposition löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="color:var(--c-muted);text-align:center">Noch keine Pfandpositionen angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
