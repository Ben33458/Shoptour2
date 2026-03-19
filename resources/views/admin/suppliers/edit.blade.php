@extends('admin.layout')

@section('title', 'Lieferant bearbeiten — ' . $supplier->name)

@section('actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline btn-sm">← Lieferantenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Lieferantendaten bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            @include('admin.suppliers._form', ['supplier' => $supplier])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

{{-- ── Gefahrenzone ── --}}
<div class="card" style="margin-top:24px;border-color:var(--c-danger)">
    <div class="card-header" style="color:var(--c-danger)">Gefahrenzone</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:24px">

        {{-- Merge --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Duplikat zusammenführen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Geben Sie die ID des Duplikats ein (steht in der URL der Bearbeitungsseite:
                <code>/admin/suppliers/ID/edit</code>). Alle Bestellungen und Belege werden
                übertragen, das Duplikat wird gelöscht.
            </div>
            @if($errors->has('source_supplier_id'))
                <div style="color:var(--c-danger);font-size:13px;margin-bottom:8px">{{ $errors->first('source_supplier_id') }}</div>
            @endif
            <form method="POST" action="{{ route('admin.suppliers.merge', $supplier) }}"
                  onsubmit="return confirm('Wirklich zusammenführen? Das Duplikat wird dauerhaft gelöscht!')">
                @csrf
                <div style="display:flex;gap:8px">
                    <input type="number" name="source_supplier_id"
                           placeholder="ID des Duplikats"
                           style="flex:1;padding:6px 10px;border:1px solid var(--c-border);border-radius:4px"
                           min="1" required>
                    <button type="submit" class="btn btn-sm"
                            style="background:var(--c-danger);color:#fff;border-color:var(--c-danger)">
                        Zusammenführen
                    </button>
                </div>
            </form>
        </div>

        {{-- Delete --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Lieferant löschen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Nur möglich, wenn keine Einkaufsbestellungen vorhanden sind.
            </div>
            <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
                  onsubmit="return confirm('Lieferant wirklich dauerhaft löschen?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm"
                        style="background:var(--c-danger);color:#fff;border-color:var(--c-danger)">
                    Lieferant löschen
                </button>
            </form>
        </div>

    </div>
</div>

@endsection
