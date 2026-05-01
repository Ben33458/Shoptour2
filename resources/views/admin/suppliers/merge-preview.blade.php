@extends('admin.layout')

@section('title', 'Lieferanten zusammenführen — Feldabgleich')

@section('actions')
    <a href="{{ route('admin.suppliers.edit', $target) }}" class="btn btn-outline btn-sm">← Abbrechen</a>
@endsection

@section('content')

@php
    $fields = [
        'name'                             => 'Firmenname',
        'email'                            => 'E-Mail',
        'phone'                            => 'Telefon',
        'address'                          => 'Adresse',
        'contact_name'                     => 'Kontaktperson',
        'currency'                         => 'Währung',
        'lieferanten_nr'                   => 'Lieferanten-Nr.',
        'lexoffice_contact_id'             => 'Lexoffice-Kontakt-ID',
        'ninox_lieferanten_id'             => 'Ninox-ID',
        'bestelltag'                       => 'Bestelltag',
        'liefertag'                        => 'Liefertag',
        'bestell_schlusszeit'              => 'Bestellschlusszeit',
        'lieferintervall'                  => 'Lieferintervall',
        'mindestbestellwert_netto_ek_milli'=> 'Mindestbestellwert Netto-EK',
        'kontrollstufe_default'            => 'Kontrollstufe (Standard)',
    ];

    // For display: format milli-cent fields
    function fmtField(string $key, mixed $val): string {
        if ($val === null || $val === '') return '—';
        if ($key === 'mindestbestellwert_netto_ek_milli') {
            return number_format((int)$val / 1000, 2, ',', '.') . ' €';
        }
        return e((string) $val);
    }
@endphp

<div style="margin-bottom:18px;padding:14px 18px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;font-size:.9rem">
    <strong>Feldabgleich:</strong>
    Wählen Sie pro Feld, welcher Wert im Ziel-Lieferanten übernommen werden soll.
    Alle Bestellungen, Belege und Verknüpfungen des Duplikats werden automatisch auf den Ziel-Lieferanten übertragen.
    Das Duplikat wird anschließend <strong>dauerhaft gelöscht</strong>.
</div>

<form method="POST" action="{{ route('admin.suppliers.merge', $target) }}"
      onsubmit="return confirm('Jetzt zusammenführen? Das Duplikat wird dauerhaft gelöscht!')">
    @csrf
    <input type="hidden" name="source_supplier_id" value="{{ $source->id }}">

    {{-- Header --}}
    <div style="display:grid;grid-template-columns:180px 1fr 48px 1fr;gap:0;border:1px solid var(--c-border);border-radius:8px;overflow:hidden;margin-bottom:24px">

        {{-- Column headers --}}
        <div style="grid-column:1/-1;display:grid;grid-template-columns:180px 1fr 48px 1fr;background:var(--c-bg-alt,#f5f5f5);border-bottom:2px solid var(--c-border);padding:0">
            <div style="padding:10px 14px;font-weight:600;font-size:.85rem;color:var(--c-muted)">Feld</div>
            <div style="padding:10px 14px;font-weight:600;font-size:.9rem;border-left:1px solid var(--c-border)">
                🎯 Ziel (bleibt erhalten)
                <div style="font-size:.78rem;font-weight:400;color:var(--c-muted)">ID {{ $target->id }}</div>
            </div>
            <div style="border-left:1px solid var(--c-border)"></div>
            <div style="padding:10px 14px;font-weight:600;font-size:.9rem;border-left:1px solid var(--c-border)">
                🗑 Duplikat (wird gelöscht)
                <div style="font-size:.78rem;font-weight:400;color:var(--c-muted)">ID {{ $source->id }}</div>
            </div>
        </div>

        {{-- Field rows --}}
        @foreach($fields as $key => $label)
        @php
            $targetVal = $target->{$key};
            $sourceVal = $source->{$key};
            $differs   = $targetVal != $sourceVal;
            $rowBg     = $differs ? 'background:#fffbeb' : '';
        @endphp
        <div style="display:contents">
            {{-- Label --}}
            <div style="padding:10px 14px;font-size:.82rem;color:var(--c-muted);border-top:1px solid var(--c-border);align-self:center;{{ $rowBg }}">
                {{ $label }}
                @if($differs)<span style="color:#d97706;font-size:.75rem;margin-left:4px">⚠ abweichend</span>@endif
            </div>

            {{-- Target value + radio --}}
            <div style="padding:10px 14px;border-top:1px solid var(--c-border);border-left:1px solid var(--c-border);{{ $rowBg }}">
                <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                    <input type="radio" name="fields[{{ $key }}]" value="target"
                           @checked(!$differs || $targetVal)
                           style="margin-top:3px;flex-shrink:0">
                    <span style="font-size:.88rem;{{ !$targetVal ? 'color:var(--c-muted);font-style:italic' : '' }}">
                        {!! fmtField($key, $targetVal) !!}
                    </span>
                </label>
            </div>

            {{-- Arrow --}}
            <div style="padding:10px 4px;border-top:1px solid var(--c-border);border-left:1px solid var(--c-border);text-align:center;color:var(--c-muted);font-size:.8rem;align-self:center;{{ $rowBg }}">
                @if($differs) ↔ @endif
            </div>

            {{-- Source value + radio --}}
            <div style="padding:10px 14px;border-top:1px solid var(--c-border);border-left:1px solid var(--c-border);{{ $rowBg }}">
                <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                    <input type="radio" name="fields[{{ $key }}]" value="source"
                           @checked($differs && !$targetVal && $sourceVal)
                           style="margin-top:3px;flex-shrink:0">
                    <span style="font-size:.88rem;{{ !$sourceVal ? 'color:var(--c-muted);font-style:italic' : '' }}">
                        {!! fmtField($key, $sourceVal) !!}
                    </span>
                </label>
            </div>
        </div>
        @endforeach

    </div>

    {{-- Contacts info --}}
    @if($source->contacts->isNotEmpty())
    <div style="margin-bottom:18px;padding:12px 16px;background:var(--c-bg-alt,#f5f5f5);border-radius:6px;font-size:.85rem">
        <strong>Kontakte des Duplikats ({{ $source->contacts->count() }}):</strong>
        Die Kontakte des Duplikats werden beim Zusammenführen <strong>gelöscht</strong>.
        Bitte übertragen Sie wichtige Kontaktdaten vorher manuell, falls nötig.
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px">
            @foreach($source->contacts as $c)
            <span style="padding:3px 10px;background:#fff;border:1px solid var(--c-border);border-radius:4px;font-size:.82rem">
                {{ $c->name }}@if($c->email) · {{ $c->email }}@endif@if($c->phone) · {{ $c->phone }}@endif
            </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Actions --}}
    <div style="display:flex;gap:12px;align-items:center">
        <button type="submit" class="btn btn-sm"
                style="background:var(--c-danger);color:#fff;border-color:var(--c-danger);padding:8px 20px">
            Jetzt zusammenführen &amp; Duplikat löschen
        </button>
        <a href="{{ route('admin.suppliers.edit', $target) }}" class="btn btn-outline btn-sm">Abbrechen</a>
        <span style="font-size:.8rem;color:var(--c-muted);margin-left:4px">
            Ziel: <strong>{{ $target->name }}</strong> (ID {{ $target->id }}) &nbsp;·&nbsp;
            Duplikat: <strong>{{ $source->name }}</strong> (ID {{ $source->id }})
        </span>
    </div>
</form>

@endsection
