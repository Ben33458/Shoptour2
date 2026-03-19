@extends('admin.layout')

@section('title', 'Marken')

@section('actions')
    <span style="color:var(--c-muted);font-size:.85rem">{{ $brands->count() }} Einträge</span>
    <details class="actions-dropdown">
        <summary class="btn btn-outline btn-sm">Aktionen ▾</summary>
        <div class="actions-menu">
            <a href="{{ route('admin.imports.brands') }}">CSV importieren</a>
        </div>
    </details>
@endsection

@section('content')
<div class="card">
    {{-- Schnell-Anlegen --}}
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.brands.store') }}" style="display:flex;gap:.5rem;margin-bottom:1.5rem;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0;flex:1">
            <label>Neue Marke</label>
            <input type="text" name="name" placeholder="z.B. Paulaner" required maxlength="150"
                   value="{{ old('name') }}">
            @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Anlegen</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="text-align:center">Produkte</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                <tr data-ie-url="{{ route('admin.brands.update', $brand) }}">
                    <td data-ie-field="name"
                        data-ie-type="text"
                        data-ie-value="{{ $brand->name }}">{{ $brand->name }}</td>
                    <td style="text-align:center">
                        <span class="badge">{{ $brand->products_count }}</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}"
                              onsubmit="return confirm('Marke {{ addslashes($brand->name) }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Löschen">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="color:var(--c-muted);text-align:center">Noch keine Marken angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
