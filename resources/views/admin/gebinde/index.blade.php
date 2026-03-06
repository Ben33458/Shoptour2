@extends('admin.layout')
@section('title', 'Gebinde')
@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.gebinde.store') }}" style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0;flex:2;min-width:160px">
            <label>Name *</label>
            <input type="text" name="name" required maxlength="150" placeholder="z.B. 0,5L Flasche" value="{{ old('name') }}">
        </div>
        <div class="form-group" style="margin:0;min-width:130px">
            <label>Typ *</label>
            <select name="gebinde_type" required>
                @foreach(['Flasche','Kiste','Dose','Kasten','Sonstige'] as $t)
                    <option value="{{ $t }}" @selected(old('gebinde_type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:120px">
            <label>Material</label>
            <select name="material">
                <option value="">— keins —</option>
                @foreach(['PET','PEC','Glas'] as $m)
                    <option value="{{ $m }}" @selected(old('material') === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:140px">
            <label>Pfandset</label>
            <select name="pfand_set_id">
                <option value="">— kein —</option>
                @foreach($pfandSets as $ps)
                    <option value="{{ $ps->id }}" @selected(old('pfand_set_id') == $ps->id)>{{ $ps->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Anlegen</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Typ</th><th>Material</th><th>Pfandset</th><th style="text-align:center">Aktiv</th><th style="text-align:center">Produkte</th><th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($gebindeList as $g)
                @php
                    $typeOptions    = collect(['Flasche','Kiste','Dose','Kasten','Sonstige'])->map(fn($t) => ['value'=>$t,'label'=>$t])->toJson();
                    $materialOptions = collect([['value'=>'','label'=>'— keins —'],['value'=>'PET','label'=>'PET'],['value'=>'PEC','label'=>'PEC'],['value'=>'Glas','label'=>'Glas']])->toJson();
                    $psOptions      = collect([['value'=>'','label'=>'— kein —']])->merge($pfandSets->map(fn($ps) => ['value'=>$ps->id,'label'=>$ps->name]))->toJson();
                @endphp
                <tr data-ie-url="{{ route('admin.gebinde.update', $g) }}">
                    <td data-ie-field="name" data-ie-type="text" data-ie-value="{{ $g->name }}">{{ $g->name }}</td>
                    <td data-ie-field="gebinde_type" data-ie-type="select" data-ie-value="{{ $g->gebinde_type }}" data-ie-options="{{ $typeOptions }}">{{ $g->gebinde_type }}</td>
                    <td data-ie-field="material" data-ie-type="select" data-ie-value="{{ $g->material ?? '' }}" data-ie-options="{{ $materialOptions }}">{{ $g->material ?? '—' }}</td>
                    <td data-ie-field="pfand_set_id" data-ie-type="select" data-ie-value="{{ $g->pfand_set_id ?? '' }}" data-ie-options="{{ $psOptions }}">{{ $g->pfandSet?->name ?? '—' }}</td>
                    <td style="text-align:center" data-ie-field="active" data-ie-type="checkbox" data-ie-value="{{ $g->active ? '1' : '0' }}" title="Klick zum Umschalten">{{ $g->active ? '✓' : '–' }}</td>
                    <td style="text-align:center"><span class="badge">{{ $g->products_count }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.gebinde.destroy', $g) }}" onsubmit="return confirm('Gebinde löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="color:var(--c-muted);text-align:center">Noch keine Gebinde angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
