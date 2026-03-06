@extends('admin.layout')
@section('title', 'Pfandsets')
@section('content')

<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    {{-- ── Schnell-Anlegen ── --}}
    <form method="POST" action="{{ route('admin.pfand-sets.store') }}"
          style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0;min-width:200px">
            <label>Name <span style="color:var(--c-danger)">*</span></label>
            <input type="text" name="name" required maxlength="150"
                   placeholder="z.B. 0,5L Flasche Pfandset"
                   value="{{ old('name') }}">
        </div>
        <div class="form-group" style="margin:0;flex:2;min-width:200px">
            <label>Beschreibung</label>
            <textarea name="beschreibung" rows="1"
                      placeholder="Optionale Beschreibung"
                      style="resize:vertical">{{ old('beschreibung') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Anlegen</button>
    </form>

    {{-- ── Tabelle ── --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="text-align:center">Komponenten</th>
                    <th style="text-align:center">Gebinde</th>
                    <th style="text-align:center">Aktiv</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pfandSets as $ps)
                <tr data-ie-url="{{ route('admin.pfand-sets.update', $ps) }}">
                    <td data-ie-field="name" data-ie-type="text" data-ie-value="{{ $ps->name }}">{{ $ps->name }}</td>
                    <td style="text-align:center">
                        <span class="badge">{{ $ps->components_count }}</span>
                    </td>
                    <td style="text-align:center">
                        <span class="badge {{ $ps->gebinde_count > 0 ? 'badge-success' : '' }}">{{ $ps->gebinde_count }}</span>
                    </td>
                    <td style="text-align:center"
                        data-ie-field="active" data-ie-type="checkbox"
                        data-ie-value="{{ $ps->active ? '1' : '0' }}"
                        title="Klick zum Umschalten">
                        {{ $ps->active ? '✓' : '–' }}
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.pfand-sets.show', $ps) }}"
                           class="btn btn-outline btn-sm" title="Komponenten verwalten">
                            Bearbeiten
                        </a>
                        <form method="POST" action="{{ route('admin.pfand-sets.destroy', $ps) }}"
                              style="display:inline"
                              onsubmit="return confirm('Pfandset {{ addslashes($ps->name) }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="color:var(--c-muted);text-align:center;padding:24px">
                        Noch keine Pfandsets angelegt.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
