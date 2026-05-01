@extends('admin.layout')

@section('title', 'Verpackungseinheiten (VPE)')

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

{{-- Liste --}}
<div>
    <form method="GET" action="{{ route('admin.bestandsaufnahme.verpackungseinheiten.index') }}" style="margin-bottom:12px">
        <select name="product_id" onchange="this.form.submit()">
            <option value="">Alle Artikel</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}" @selected($productId == $p->id)>{{ $p->artikelnummer }} – {{ $p->produktname }}</option>
            @endforeach
        </select>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Artikel</th>
                <th>Bezeichnung</th>
                <th>Faktor</th>
                <th>Bestellbar</th>
                <th>Zählbar</th>
                <th>Sortierung</th>
                <th>Aktiv</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($vpes as $vpe)
            <tr>
                <td><small>{{ $vpe->product->artikelnummer }}</small><br>{{ $vpe->product->produktname }}</td>
                <td>{{ $vpe->bezeichnung }}</td>
                <td>{{ number_format($vpe->faktor_basiseinheit, 3, ',', '.') }}</td>
                <td>{{ $vpe->ist_bestellbar ? '✓' : '—' }}</td>
                <td>{{ $vpe->ist_zaehlbar ? '✓' : '—' }}</td>
                <td>{{ $vpe->sortierung }}</td>
                <td>{{ $vpe->aktiv ? '✓' : '—' }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.bestandsaufnahme.verpackungseinheiten.destroy', $vpe) }}"
                          onsubmit="return confirm('Löschen?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Löschen</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-muted text-center">Keine VPE gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $vpes->links() }}
</div>

{{-- Formular --}}
<div class="card" style="padding:16px">
    <h3 style="margin-top:0">Neue VPE anlegen</h3>
    <form method="POST" action="{{ route('admin.bestandsaufnahme.verpackungseinheiten.store') }}">
        @csrf

        <div class="form-group">
            <label>Artikel <span class="text-danger">*</span></label>
            <select name="product_id" required>
                <option value="">— Artikel wählen —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(old('product_id', $productId) == $p->id)>{{ $p->artikelnummer }} – {{ $p->produktname }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Bezeichnung <span class="text-danger">*</span></label>
            <input type="text" name="bezeichnung" value="{{ old('bezeichnung') }}" placeholder="24er Kasten 0,33l" required class="form-control">
        </div>

        <div class="form-group">
            <label>Faktor zur Basiseinheit <span class="text-danger">*</span></label>
            <input type="number" name="faktor_basiseinheit" value="{{ old('faktor_basiseinheit', 1) }}" step="0.001" min="0.001" required class="form-control">
            <small class="text-muted">z. B. 24 wenn 1 Kasten = 24 Flaschen</small>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:12px">
            <label><input type="checkbox" name="ist_bestellbar" value="1" @checked(old('ist_bestellbar', '1'))> Bestellbar</label>
            <label><input type="checkbox" name="ist_zaehlbar" value="1" @checked(old('ist_zaehlbar', '1'))> Zählbar</label>
        </div>

        <div class="form-group">
            <label>Sortierung</label>
            <input type="number" name="sortierung" value="{{ old('sortierung', 0) }}" min="0" class="form-control" style="width:80px">
        </div>

        <button type="submit" class="btn btn-primary">Anlegen</button>
    </form>
</div>

</div>

@endsection
