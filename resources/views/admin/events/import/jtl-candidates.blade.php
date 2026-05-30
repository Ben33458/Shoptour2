@extends('admin.layout')

@section('title', 'JTL-Angebote zuordnen')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.import.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Import-Übersicht</a>
    <h1 style="margin:4px 0;font-size:1.3rem">JTL-Angebote zuordnen</h1>
    <p style="color:var(--c-muted);font-size:.85rem;margin:0">{{ $candidates->count() }} nicht importierte JTL-Angebote</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($candidates->isEmpty())
    <div class="card" style="padding:24px;text-align:center;color:var(--c-muted)">
        Alle JTL-Angebote wurden bereits importiert.
    </div>
@else

@php
    $autoMatched   = $candidates->filter(fn($c) => $c->auto_match);
    $autoCreate    = $candidates->filter(fn($c) => !$c->auto_match && $c->can_auto_create);
    $manual        = $candidates->filter(fn($c) => !$c->auto_match && !$c->can_auto_create);
@endphp

{{-- ── 1. Vorhandene Veranstaltung gefunden ── --}}
@if($autoMatched->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span style="color:#16a34a;font-weight:600">
            Veranstaltung gefunden — {{ $autoMatched->count() }} Angebote
        </span>
        <form method="POST" action="{{ route('admin.events.import.jtl.bulk') }}"
              onsubmit="return confirm('{{ $autoMatched->count() }} Angebote importieren?')">
            @csrf
            @foreach($autoMatched as $offer)
                <input type="hidden" name="matches[{{ $loop->index }}][k_auftrag]"     value="{{ $offer->kAuftrag }}">
                <input type="hidden" name="matches[{{ $loop->index }}][occurrence_id]" value="{{ $offer->suggestion->id }}">
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm">Alle {{ $autoMatched->count() }} importieren</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Angebotsnr.</th><th>Datum</th><th>Kunde</th>
                <th style="text-align:center">Pos.</th><th>Veranstaltung</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($autoMatched as $offer)
            <tr>
                <td style="font-family:monospace;font-weight:600">{{ $offer->cAuftragsNr }}</td>
                <td style="white-space:nowrap;color:var(--c-muted);font-size:.85rem">{{ \Carbon\Carbon::parse($offer->dErstellt)->format('d.m.Y') }}</td>
                <td>
                    <div style="font-weight:500">{{ trim($offer->kundename) ?: '—' }}</div>
                    <div style="font-size:.8rem;color:var(--c-muted)">{{ $offer->cKundenNr }}</div>
                </td>
                <td style="text-align:center">{{ $offer->position_count }}</td>
                <td>
                    <div style="font-weight:500">{{ $offer->suggestion->title }}</div>
                    <div style="font-size:.8rem;color:var(--c-muted)">
                        {{ $offer->suggestion->customer?->company_name ?? '' }}
                        @if($offer->suggestion->event_start_at)
                            · {{ \Carbon\Carbon::parse($offer->suggestion->event_start_at)->format('d.m.Y') }}
                        @endif
                    </div>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.events.import.jtl.store') }}">
                        @csrf
                        <input type="hidden" name="k_auftrag"     value="{{ $offer->kAuftrag }}">
                        <input type="hidden" name="occurrence_id" value="{{ $offer->suggestion->id }}">
                        <button type="submit" class="btn btn-outline btn-sm" style="font-size:.78rem">Einzeln</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── 2. Ninox-Kunde bekannt → Veranstaltung automatisch anlegen ── --}}
@if($autoCreate->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span style="color:#2563eb;font-weight:600">
            Kunde bekannt, Veranstaltung wird angelegt — {{ $autoCreate->count() }} Angebote
        </span>
        <form method="POST" action="{{ route('admin.events.import.jtl.auto') }}"
              onsubmit="return confirm('{{ $autoCreate->count() }} Veranstaltungen neu anlegen und Angebote importieren?')">
            @csrf
            @foreach($autoCreate as $offer)
                <input type="hidden" name="k_auftraege[]" value="{{ $offer->kAuftrag }}">
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm">Alle {{ $autoCreate->count() }} anlegen & importieren</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Angebotsnr.</th><th>Datum</th><th>Kunde</th>
                <th style="text-align:center">Pos.</th><th>Neue Veranstaltung</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($autoCreate as $offer)
            <tr>
                <td style="font-family:monospace;font-weight:600">{{ $offer->cAuftragsNr }}</td>
                <td style="white-space:nowrap;color:var(--c-muted);font-size:.85rem">{{ \Carbon\Carbon::parse($offer->dErstellt)->format('d.m.Y') }}</td>
                <td>
                    <div style="font-weight:500">{{ trim($offer->kundename) ?: '—' }}</div>
                    <div style="font-size:.8rem;color:var(--c-muted)">{{ $offer->cKundenNr }}</div>
                </td>
                <td style="text-align:center">{{ $offer->position_count }}</td>
                <td style="color:var(--c-muted);font-size:.85rem">
                    @if(!empty($offer->dVoraussichtlichesLieferdatum ?? null) || !empty($offer->dErstellt))
                        @php $evDate = !empty($offer->dVoraussichtlichesLieferdatum ?? null)
                            ? \Carbon\Carbon::parse($offer->dVoraussichtlichesLieferdatum)
                            : \Carbon\Carbon::parse($offer->dErstellt); @endphp
                        Neu · {{ $evDate->format('d.m.Y') }}
                    @else
                        Neu
                    @endif
                    <span style="background:#dbeafe;color:#1e40af;border-radius:4px;padding:1px 6px;font-size:.75rem;margin-left:4px">needs review</span>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.events.import.jtl.auto') }}">
                        @csrf
                        <input type="hidden" name="k_auftraege[]" value="{{ $offer->kAuftrag }}">
                        <button type="submit" class="btn btn-outline btn-sm" style="font-size:.78rem">Einzeln</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── 3. Manuell zuordnen (kein Ninox-Treffer) ── --}}
@if($manual->isNotEmpty())
<div class="card">
    <div class="card-header" style="color:var(--c-muted)">
        Manuell zuordnen — {{ $manual->count() }} Angebote
        <span style="font-size:.8rem;font-weight:400;margin-left:8px">(Kunde nicht in Ninox gefunden)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Angebotsnr.</th><th>Datum</th><th>Kunde</th>
                <th style="text-align:center">Pos.</th><th>Veranstaltung wählen</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($manual as $offer)
            <tr>
                <td style="font-family:monospace;font-weight:600">{{ $offer->cAuftragsNr }}</td>
                <td style="white-space:nowrap;color:var(--c-muted);font-size:.85rem">{{ \Carbon\Carbon::parse($offer->dErstellt)->format('d.m.Y') }}</td>
                <td>
                    <div style="font-weight:500">{{ trim($offer->kundename) ?: '—' }}</div>
                    <div style="font-size:.8rem;color:var(--c-muted)">{{ $offer->cKundenNr }}</div>
                </td>
                <td style="text-align:center">{{ $offer->position_count }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.events.import.jtl.store') }}"
                          style="display:flex;gap:6px;align-items:center">
                        @csrf
                        <input type="hidden" name="k_auftrag" value="{{ $offer->kAuftrag }}">
                        <select name="occurrence_id" required
                                style="flex:1;min-width:220px;font-size:.82rem;padding:4px 6px;border:1px solid var(--c-border);border-radius:4px;background:var(--c-bg)">
                            <option value="">— Veranstaltung wählen —</option>
                            @foreach($occurrences as $occ)
                                <option value="{{ $occ->id }}">
                                    {{ $occ->title }}
                                    — {{ $occ->customer?->company_name ?? ($occ->customer ? trim($occ->customer->first_name.' '.$occ->customer->last_name) : '?') }}
                                    ({{ $occ->event_start_at ? \Carbon\Carbon::parse($occ->event_start_at)->format('d.m.Y') : 'kein Datum' }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;font-size:.82rem">Importieren</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endif

@endsection
