@extends('admin.layout')
@section('title', 'Pfandset: ' . $pfandSet->name)

@section('actions')
    <a href="{{ route('admin.pfand-sets.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- ── Kopf-Info & Bearbeiten ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Pfandset-Daten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.pfand-sets.update', $pfandSet) }}">
            @csrf @method('PATCH')
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
                <div class="form-group" style="margin:0;min-width:200px">
                    <label>Name <span style="color:var(--c-danger)">*</span></label>
                    <input type="text" name="name" required maxlength="150"
                           value="{{ old('name', $pfandSet->name) }}">
                </div>
                <div class="form-group" style="margin:0;display:flex;align-items:center;gap:8px;padding-top:24px">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" id="ps_active"
                           @checked($pfandSet->active)>
                    <label for="ps_active" style="margin:0;cursor:pointer">Aktiv</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">Speichern</button>
            </div>
            <div class="form-group" style="margin:0">
                <label>Beschreibung</label>
                <textarea name="beschreibung" rows="3" placeholder="Optionale Beschreibung"
                          style="resize:vertical">{{ old('beschreibung', $pfandSet->beschreibung) }}</textarea>
            </div>
        </form>
        @if($pfandSet->gebinde->count() > 0)
        <div style="margin-top:10px;font-size:.85em;color:var(--c-muted)">
            Verwendet von: {{ $pfandSet->gebinde->pluck('name')->implode(', ') }}
        </div>
        @endif
    </div>
</div>

{{-- ── Komponenten-Tabelle ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        Komponenten
        <span class="text-muted" style="font-size:.85em">
            ({{ $pfandSet->components->count() }} Positionen)
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Bezeichnung</th>
                    <th style="text-align:center">Menge</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pfandSet->components as $comp)
                <tr>
                    <td>
                        @if($comp->isLeaf())
                            <span class="badge badge-success">Pfandposition</span>
                        @else
                            <span class="badge">Pfandset</span>
                        @endif
                    </td>
                    <td>
                        @if($comp->isLeaf())
                            {{ $comp->pfandItem?->bezeichnung ?? '–' }}
                            <small style="color:var(--c-muted)">
                                ({{ $comp->pfandItem?->pfand_typ }},
                                {{ number_format(($comp->pfandItem?->wert_brutto_milli ?? 0) / 1_000_000, 2, ',', '.') }} €)
                            </small>
                        @else
                            {{ $comp->childPfandSet?->name ?? '–' }}
                        @endif
                    </td>
                    <td style="text-align:center">
                        <strong>{{ $comp->qty }}</strong>×
                    </td>
                    <td>
                        <form method="POST"
                              action="{{ route('admin.pfand-sets.components.destroy', [$pfandSet, $comp]) }}"
                              onsubmit="return confirm('Komponente entfernen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="color:var(--c-muted);text-align:center;padding:20px">
                        Noch keine Komponenten — füge unten eine hinzu.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Neue Komponente hinzufügen ── --}}
<div class="card">
    <div class="card-header">Komponente hinzufügen</div>
    <div class="card-body">
        <form method="POST"
              action="{{ route('admin.pfand-sets.components.store', $pfandSet) }}"
              id="add-component-form">
            @csrf

            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">

                {{-- Typ-Auswahl --}}
                <div class="form-group" style="margin:0;min-width:160px">
                    <label>Typ</label>
                    <select name="component_type" id="component_type"
                            onchange="toggleComponentType(this.value)">
                        <option value="item" @selected(old('component_type','item') === 'item')>Pfandposition</option>
                        <option value="set"  @selected(old('component_type') === 'set')>Pfandset (verschachtelt)</option>
                    </select>
                </div>

                {{-- Menge (vor Pfandposition, wie gewünscht) --}}
                <div class="form-group" style="margin:0;min-width:100px">
                    <label>Menge</label>
                    <input type="number" name="qty" min="1" max="9999" required
                           value="{{ old('qty', 1) }}" style="max-width:100px">
                </div>

                {{-- PfandItem wählen --}}
                <div class="form-group" id="item-picker"
                     style="margin:0;flex:2;min-width:200px;@if(old('component_type') === 'set') display:none @endif">
                    <label>Pfandposition</label>
                    <select name="pfand_item_id">
                        <option value="">— wählen —</option>
                        @foreach($pfandItems as $item)
                        <option value="{{ $item->id }}" @selected(old('pfand_item_id') == $item->id)>
                            {{ $item->bezeichnung }}
                            ({{ $item->pfand_typ }},
                            {{ number_format($item->wert_brutto_milli / 1_000_000, 2, ',', '.') }} €)
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Child-Set wählen --}}
                <div class="form-group" id="set-picker"
                     style="margin:0;flex:2;min-width:200px;@if(old('component_type') !== 'set') display:none @endif">
                    <label>Pfandset</label>
                    <select name="child_set_id">
                        <option value="">— wählen —</option>
                        @foreach($allSets as $s)
                        <option value="{{ $s->id }}" @selected(old('child_set_id') == $s->id)>
                            {{ $s->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="align-self:flex-end">
                    Hinzufügen
                </button>
            </div>

            @if($errors->any())
            <div class="alert alert-error" style="margin-top:10px">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
            @endif
        </form>
    </div>
</div>

<script>
function toggleComponentType(val) {
    document.getElementById('item-picker').style.display = val === 'item' ? '' : 'none';
    document.getElementById('set-picker').style.display  = val === 'set'  ? '' : 'none';
}
</script>
@endsection
