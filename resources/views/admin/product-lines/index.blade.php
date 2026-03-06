@extends('admin.layout')

@section('title', 'Produktlinien')

@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.product-lines.store') }}" style="display:flex;gap:.5rem;margin-bottom:1.5rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div class="form-group" style="margin:0;flex:2;min-width:160px">
            <label>Neue Produktlinie *</label>
            <input type="text" name="name" placeholder="z.B. Zwickl" required maxlength="150" value="{{ old('name') }}">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px">
            <label>Marke <span style="color:var(--c-danger)">*</span></label>
            <select name="brand_id" required>
                <option value="">— wählen —</option>
                @foreach($brands as $b)
                    <option value="{{ $b->id }}" @selected(old('brand_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px">
            <label>Gebinde</label>
            <select name="gebinde_id">
                <option value="">— kein —</option>
                @foreach($gebindeList as $g)
                    <option value="{{ $g->id }}" @selected(old('gebinde_id') == $g->id)>{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px">
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
                    <th>Name</th>
                    <th>Marke</th>
                    <th>Gebinde</th>
                    <th>Pfandset</th>
                    <th style="text-align:center">Produkte</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($productLines as $pl)
                @php
                    $brandOptions   = $brands->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()->toJson();
                    $gebindeOptions = collect([['value'=>'','label'=>'— kein —']])->merge($gebindeList->map(fn($g) => ['value'=>$g->id,'label'=>$g->name]))->toJson();
                    $pfandOptions   = collect([['value'=>'','label'=>'— kein —']])->merge($pfandSets->map(fn($ps) => ['value'=>$ps->id,'label'=>$ps->name]))->toJson();
                @endphp
                <tr data-ie-url="{{ route('admin.product-lines.update', $pl) }}">
                    <td data-ie-field="name" data-ie-type="text" data-ie-value="{{ $pl->name }}">{{ $pl->name }}</td>
                    <td data-ie-field="brand_id"
                        data-ie-type="select"
                        data-ie-value="{{ $pl->brand_id }}"
                        data-ie-options="{{ $brandOptions }}">{{ $pl->brand?->name ?? '—' }}</td>
                    <td data-ie-field="gebinde_id"
                        data-ie-type="select"
                        data-ie-value="{{ $pl->gebinde_id ?? '' }}"
                        data-ie-options="{{ $gebindeOptions }}">{{ $pl->gebinde?->name ?? '—' }}</td>
                    <td data-ie-field="pfand_set_id"
                        data-ie-type="select"
                        data-ie-value="{{ $pl->pfand_set_id ?? '' }}"
                        data-ie-options="{{ $pfandOptions }}">{{ $pl->pfandSet?->name ?? '—' }}</td>
                    <td style="text-align:center"><span class="badge">{{ $pl->products_count }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.product-lines.destroy', $pl) }}"
                              onsubmit="return confirm('Produktlinie löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="color:var(--c-muted);text-align:center">Noch keine Produktlinien angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
