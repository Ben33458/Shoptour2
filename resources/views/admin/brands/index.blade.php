@extends('admin.layout')

@section('title', 'Marken & Hersteller')

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
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    {{-- Schnell-Anlegen --}}
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
                    <th>Ort</th>
                    <th style="text-align:center">Adresse</th>
                    <th style="text-align:center">Produkte</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                @php
                    $adresseKomplett = $brand->adresse && $brand->plz && $brand->ort;
                @endphp
                <tr>
                    <td>{{ $brand->name }}</td>
                    <td style="color:var(--c-muted);font-size:.9rem">
                        {{ $brand->ort ? $brand->plz . ' ' . $brand->ort : '—' }}
                    </td>
                    <td style="text-align:center">
                        @if($adresseKomplett)
                            <span class="badge badge-success" title="{{ $brand->adresse }}, {{ $brand->plz }} {{ $brand->ort }}">✓</span>
                        @else
                            <span class="badge" style="color:var(--c-muted)">fehlt</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        <span class="badge">{{ $brand->products_count }}</span>
                    </td>
                    <td style="display:flex;gap:.25rem">
                        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-outline btn-sm" title="Bearbeiten">✏</a>
                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}"
                              onsubmit="return confirm('Marke {{ addslashes($brand->name) }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Löschen">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="color:var(--c-muted);text-align:center">Noch keine Marken angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
