@extends('admin.layout')

@section('title', 'Kategorien')

@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.categories.store') }}" style="display:flex;gap:.5rem;margin-bottom:1.5rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div class="form-group" style="margin:0;flex:2;min-width:180px">
            <label>Neue Kategorie</label>
            <input type="text" name="name" placeholder="z.B. Bier" required maxlength="150" value="{{ old('name') }}">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label>Übergeordnet (optional)</label>
            <select name="parent_id">
                <option value="">— keine —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('parent_id') == $c->id)>{{ $c->name }}</option>
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
                    <th>Übergeordnet</th>
                    <th style="text-align:center">Produkte</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                @php
                    $parentOptions = collect([['value' => '', 'label' => '— keine —']])
                        ->merge($categories->where('id', '!=', $cat->id)->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values())
                        ->toJson();
                @endphp
                <tr data-ie-url="{{ route('admin.categories.update', $cat) }}">
                    <td data-ie-field="name" data-ie-type="text" data-ie-value="{{ $cat->name }}">{{ $cat->name }}</td>
                    <td data-ie-field="parent_id"
                        data-ie-type="select"
                        data-ie-value="{{ $cat->parent_id ?? '' }}"
                        data-ie-options="{{ $parentOptions }}">{{ $cat->parent?->name ?? '—' }}</td>
                    <td style="text-align:center"><span class="badge">{{ $cat->products_count }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}"
                              onsubmit="return confirm('Kategorie löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="color:var(--c-muted);text-align:center">Noch keine Kategorien angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
