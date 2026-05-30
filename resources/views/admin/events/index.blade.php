@extends('admin.layout')

@section('title', 'Veranstaltungen & Angebote')

@section('content')

{{-- ── Tab navigation ── --}}
<style>
.ev-tabs { display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid var(--c-border,#e5e7eb); }
.ev-tab  { padding:8px 18px; font-size:14px; text-decoration:none; border-bottom:2px solid transparent;
           margin-bottom:-2px; color:var(--c-muted,#6b7280); font-weight:400; white-space:nowrap; }
.ev-tab:hover { color:var(--c-text); }
.ev-tab.active { color:var(--c-text); font-weight:600; border-bottom-color:var(--c-primary,#2563eb); }
</style>
<div class="ev-tabs">
    <a href="{{ route('admin.events.index') }}"        class="ev-tab {{ $activeTab === 'upcoming'     ? 'active' : '' }}">Kommende Veranstaltungen</a>
    <a href="{{ route('admin.events.open-offers') }}"  class="ev-tab {{ $activeTab === 'open-offers'  ? 'active' : '' }}">Offene Angebote</a>
    <a href="{{ route('admin.events.todo-offers') }}"  class="ev-tab {{ $activeTab === 'todo-offers'  ? 'active' : '' }}">Angebote Todo</a>
    <a href="{{ route('admin.events.billing-open') }}" class="ev-tab {{ $activeTab === 'billing-open' ? 'active' : '' }}">Abrechnung offen</a>
</div>

{{-- ── Filter bar (only on Tab 1) ── --}}
@if($activeTab === 'upcoming')
<form method="GET" action="{{ route('admin.events.index') }}">
    <div class="filter-bar">
        <div class="form-group">
            <label>Angebotsstatus</label>
            <select name="offer_status">
                <option value="">— Alle —</option>
                @foreach($offerStatuses as $s => $label)
                    <option value="{{ $s }}" @selected(request('offer_status') === $s)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Veranstaltungsstatus</label>
            <select name="event_status">
                <option value="">— Alle —</option>
                @foreach($eventStatuses as $s => $label)
                    <option value="{{ $s }}" @selected(request('event_status') === $s)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Jahr</label>
            <input type="number" name="year" value="{{ request('year') }}" placeholder="{{ date('Y') }}" style="width:90px">
        </div>
        <div class="form-group">
            <label>Art</label>
            <select name="event_type">
                <option value="">— Alle —</option>
                @foreach($eventTypes as $t => $label)
                    <option value="{{ $t }}" @selected(request('event_type') === $t)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Quelle</label>
            <select name="source">
                <option value="">— Alle —</option>
                <option value="ninox" @selected(request('source') === 'ninox')>Ninox</option>
                <option value="shoptour2" @selected(request('source') === 'shoptour2')>Manuell</option>
                <option value="jtl" @selected(request('source') === 'jtl')>JTL</option>
            </select>
        </div>
        <div class="form-group">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Titel, Ort…">
        </div>
        <div class="form-group" style="align-self:flex-end">
            <label style="display:flex;gap:6px;align-items:center;cursor:pointer">
                <input type="checkbox" name="needs_review" value="1" @checked(request()->boolean('needs_review'))>
                Review nötig
            </label>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>
@else
{{-- Simple search for other tabs --}}
<form method="GET" action="{{ request()->url() }}" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:center">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Titel, Ort…" class="form-control" style="max-width:280px">
        <button type="submit" class="btn btn-primary">Suchen</button>
        @if(request('search'))
            <a href="{{ request()->url() }}" class="btn btn-outline">Zurücksetzen</a>
        @endif
    </div>
</form>
@endif

{{-- ── Actions ── --}}
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div>
        <a href="{{ route('admin.events.create') }}" class="btn btn-primary">+ Neue Veranstaltung</a>
        <a href="{{ route('admin.events.calendar') }}" class="btn btn-outline" style="margin-left:8px">Kalender</a>
        <a href="{{ route('admin.events.import.index') }}" class="btn btn-outline" style="margin-left:8px">Import</a>
        <a href="{{ route('admin.events.series.index') }}" class="btn btn-outline" style="margin-left:8px">Serien</a>
    </div>
    @if($reviewCount > 0)
        <span style="background:#f59e0b;color:#fff;border-radius:6px;padding:4px 10px;font-size:.8rem;font-weight:600;">
            {{ $reviewCount }} Review nötig
        </span>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

{{-- ── Table ── --}}
<div class="card">
    <div class="card-header">
        @if($activeTab === 'upcoming')     Kommende Veranstaltungen
        @elseif($activeTab === 'open-offers')  Offene Angebote
        @elseif($activeTab === 'todo-offers')  Angebote Todo
        @elseif($activeTab === 'billing-open') Abrechnung offen
        @endif
        ({{ $occurrences->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Titel</th>
                    <th>Kunde</th>
                    <th>Art</th>
                    <th>Gäste</th>
                    <th>Angebotsstatus</th>
                    <th>Veranstaltungsstatus</th>
                    <th>Quelle</th>
                    <th>Angebotsnr.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrences as $occ)
                    <tr @if($occ->needs_review) style="background:#fffbeb" @endif>
                        <td data-sort="{{ $occ->event_start_at?->format('Y-m-d') ?? ($occ->event_year ?? '') }}">
                            {{ $occ->event_start_at?->format('d.m.Y') ?? ($occ->event_year ?? '—') }}
                        </td>
                        <td>
                            <a href="{{ route('admin.events.show', $occ) }}">{{ $occ->title }}</a>
                            @if($occ->needs_review)
                                <span style="background:#f59e0b;color:#fff;border-radius:8px;padding:1px 6px;font-size:.65rem;margin-left:4px;">Review</span>
                            @endif
                        </td>
                        <td>{{ $occ->customer?->company_name ?? '—' }}</td>
                        <td>{{ $occ->event_type }}</td>
                        <td>{{ $occ->expected_guests ?? '—' }}</td>
                        <td>
                            @include('admin.events._partials.offer-status-badge', ['status' => $occ->offer_status])
                        </td>
                        <td>
                            @include('admin.events._partials.event-status-badge', ['status' => $occ->event_status])
                        </td>
                        <td>{{ $occ->source_system ?? '—' }}</td>
                        <td>{{ $occ->currentOffer?->offer_number ?? $occ->currentOffer?->external_offer_number ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.events.show', $occ) }}" class="btn btn-outline" style="padding:2px 8px;font-size:.75rem">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="padding:40px 24px;text-align:center;color:var(--c-muted)">
                            Keine Einträge gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:16px">
    {{ $occurrences->links() }}
</div>

@endsection
