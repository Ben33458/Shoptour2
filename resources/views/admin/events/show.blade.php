@extends('admin.layout')

@section('title', $occurrence->title)

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
        <h1 style="margin:4px 0;font-size:1.3rem">{{ $occurrence->title }}</h1>
        <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
            @include('admin.events._partials.offer-status-badge', ['status' => $occurrence->offer_status])
            @include('admin.events._partials.event-status-badge', ['status' => $occurrence->event_status])
            @if($occurrence->needs_review)
                <span style="background:#f59e0b;color:#fff;border-radius:8px;padding:2px 8px;font-size:.75rem;font-weight:600;">Review nötig</span>
            @endif
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.events.edit', $occurrence) }}" class="btn btn-outline">Bearbeiten</a>
        <a href="{{ route('admin.events.offers.create', $occurrence) }}" class="btn btn-primary">+ Angebot</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

{{-- ── Tab navigation ── --}}
<div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:20px;flex-wrap:wrap" id="event-tabs">
    @php
        $tabs = [
            'overview'      => 'Übersicht',
            'offers'        => 'Angebote (' . $occurrence->offerVersions->count() . ')',
            'items'         => 'Positionen',
            'contacts'      => 'Ansprechpartner (' . $occurrence->contacts->count() . ')',
            'logistics'     => 'Logistik (' . $occurrence->logisticsAppointments->count() . ')',
            'rental'        => 'Festbedarf (' . $occurrence->rentalReservations->count() . ')',
            'previous-year' => 'Vorjahr',
            'post-calc'     => 'Nachkalkulation',
            'tasks'         => 'Aufgaben (' . $occurrence->tasks->count() . ')',
            'import'        => 'Importdaten',
            'audit'         => 'Änderungen',
        ];
        $activeTab = request()->query('tab', 'overview');
    @endphp
    @foreach($tabs as $key => $label)
        <a href="?tab={{ $key }}"
           style="padding:8px 16px;font-size:.85rem;font-weight:500;text-decoration:none;border-bottom:2px solid {{ $activeTab === $key ? 'var(--c-primary)' : 'transparent' }};margin-bottom:-2px;color:{{ $activeTab === $key ? 'var(--c-primary)' : 'var(--c-text)' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ── Tab: Übersicht ── --}}
@if($activeTab === 'overview')
<div class="card" style="max-width:800px">
    <div class="card-header">Basisdaten</div>
    <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><strong>Titel</strong><br>{{ $occurrence->title }}</div>
        <div><strong>Art</strong><br>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::eventTypes(), $occurrence->event_type) }}</div>
        <div><strong>Kunde</strong><br>
            @if($occurrence->customer)
                <a href="{{ route('admin.customers.show', $occurrence->customer) }}">{{ $occurrence->customer->company_name }}</a>
            @else
                —
            @endif
        </div>
        <div><strong>Rechnungskunde</strong><br>{{ $occurrence->billingCustomer?->company_name ?? '—' }}</div>
        <div><strong>Beginn</strong><br>{{ $occurrence->event_start_at?->format('d.m.Y H:i') ?? '—' }}</div>
        <div><strong>Ende</strong><br>{{ $occurrence->event_end_at?->format('d.m.Y H:i') ?? '—' }}</div>
        <div><strong>Gäste erwartet</strong><br>{{ $occurrence->expected_guests ?? '—' }}</div>
        <div><strong>Gäste tatsächlich</strong><br>{{ $occurrence->actual_guests ?? '—' }}</div>
        <div><strong>Ort</strong><br>{{ $occurrence->location_name ?? '—' }}</div>
        <div><strong>Adresse</strong><br>
            {{ $occurrence->address_line1 ?? '' }}
            @if($occurrence->address_line2) <br>{{ $occurrence->address_line2 }} @endif
            @if($occurrence->postal_code || $occurrence->city)
                <br>{{ $occurrence->postal_code }} {{ $occurrence->city }}
            @endif
        </div>
        <div><strong>Kanal</strong><br>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::requestChannels(), $occurrence->request_channel) }}</div>
        <div><strong>Quelle</strong><br>{{ $occurrence->source_system ?? '—' }}</div>
        @if($occurrence->series)
            <div><strong>Serie</strong><br>
                <a href="{{ route('admin.events.series.show', $occurrence->series) }}">{{ $occurrence->series->name }}</a>
            </div>
        @endif
        <div><strong>Import-Konfidenz</strong><br>{{ number_format((float)$occurrence->import_confidence * 100, 0) }}%</div>
    </div>
    @if($occurrence->internal_notes)
        <div style="padding:0 16px 16px">
            <strong>Interne Notizen</strong>
            <p style="margin:4px 0;white-space:pre-wrap">{{ $occurrence->internal_notes }}</p>
        </div>
    @endif
    @if($occurrence->customer_visible_notes)
        <div style="padding:0 16px 16px">
            <strong>Kundennotizen</strong>
            <p style="margin:4px 0;white-space:pre-wrap">{{ $occurrence->customer_visible_notes }}</p>
        </div>
    @endif
</div>
@endif

{{-- ── Tab: Angebote ── --}}
@if($activeTab === 'offers')
<div style="margin-bottom:12px">
    <a href="{{ route('admin.events.offers.create', $occurrence) }}" class="btn btn-primary">+ Neues Angebot</a>
</div>
@forelse($occurrence->offerVersions as $version)
    <div class="card" style="margin-bottom:12px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>
                V{{ $version->version_number }}
                @if($version->offer_number)
                    — {{ $version->offer_number }}
                @elseif($version->external_offer_number)
                    — Ext. #{{ $version->external_offer_number }}
                @endif
                &nbsp;
                @include('admin.events._partials.offer-status-badge', ['status' => $version->status])
            </span>
            <div style="display:flex;gap:8px">
                <a href="{{ route('admin.events.offers.show', [$occurrence, $version]) }}" class="btn btn-outline" style="padding:2px 8px;font-size:.8rem">Detail</a>
                @if(!in_array($version->status, ['converted_to_order', 'rejected', 'cancelled']))
                    <form method="POST" action="{{ route('admin.events.offers.accept', [$occurrence, $version]) }}" style="display:inline" onsubmit="return confirm('Angebot annehmen und Bestellung erstellen?')">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="padding:2px 8px;font-size:.8rem">Annehmen</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.events.offers.version', [$occurrence, $version]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="padding:2px 8px;font-size:.8rem">Neue Version</button>
                </form>
            </div>
        </div>
        <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:.85rem">
            <div><strong>Netto</strong><br>{{ $version->net_total_milli !== null ? number_format($version->net_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</div>
            <div><strong>Brutto</strong><br>{{ $version->gross_total_milli !== null ? number_format($version->gross_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</div>
            <div><strong>Gültig bis</strong><br>{{ $version->valid_until?->format('d.m.Y') ?? '—' }}</div>
            <div><strong>Erstellt</strong><br>{{ $version->created_at->format('d.m.Y H:i') }}</div>
            <div><strong>Versendet</strong><br>{{ $version->sent_at?->format('d.m.Y') ?? '—' }}</div>
            <div><strong>Positionen</strong><br>{{ $version->items->count() }}</div>
        </div>
        @if($version->conversion_status === 'needs_review' && $version->conversion_error)
            <div style="padding:8px 12px;background:#fef3c7;border-radius:0 0 6px 6px;font-size:.8rem">
                <strong>Konvertierungsfehler:</strong> {{ implode(', ', $version->conversion_error) }}
            </div>
        @endif
        @if($version->pdf_file_path)
            <div style="padding:8px 12px;font-size:.8rem">PDF: {{ basename($version->pdf_file_path) }}</div>
        @endif
    </div>
@empty
    <p style="color:var(--c-muted)">Noch keine Angebotsversionen vorhanden.</p>
@endforelse
@endif

{{-- ── Tab: Positionen ── --}}
@if($activeTab === 'items')
@php $activeOffer = $occurrence->activeOffer; @endphp
@if($activeOffer)
    <div style="margin-bottom:12px;font-size:.85rem">
        Positionen von <strong>{{ $activeOffer->offer_number ?? 'V' . $activeOffer->version_number }}</strong>
        &nbsp;@include('admin.events._partials.offer-status-badge', ['status' => $activeOffer->status])
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bezeichnung</th>
                        <th>Art</th>
                        <th class="text-right">Menge</th>
                        <th class="text-right">EP netto</th>
                        <th class="text-right">Gesamt netto</th>
                        <th>Opt.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeOffer->items as $item)
                        <tr>
                            <td>{{ $item->sort_order }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::itemTypes(), $item->item_type) }}</td>
                            <td class="text-right">{{ number_format((float)$item->quantity, 3, ',', '.') }} {{ $item->unit }}</td>
                            <td class="text-right">{{ $item->unit_price_net_milli !== null ? number_format($item->unit_price_net_milli / 1000000, 4, ',', '.') . ' €' : '—' }}</td>
                            <td class="text-right">{{ $item->line_total_net_milli !== null ? number_format($item->line_total_net_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</td>
                            <td>{{ $item->is_optional ? 'Opt.' : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;color:var(--c-muted);padding:16px">Keine Positionen</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <p style="color:var(--c-muted)">Kein aktives Angebot vorhanden.</p>
@endif
@endif

{{-- ── Tab: Ansprechpartner ── --}}
@if($activeTab === 'contacts')
<div class="card">
    <div class="card-header">Ansprechpartner</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Rolle</th>
                    <th>Telefon</th>
                    <th>Mobil</th>
                    <th>E-Mail</th>
                    <th>Primär</th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrence->contacts as $contact)
                    <tr>
                        <td>{{ $contact->name }}</td>
                        <td>{{ $contact->role }}</td>
                        <td>{{ $contact->phone ?? '—' }}</td>
                        <td>{{ $contact->mobile ?? '—' }}</td>
                        <td>{{ $contact->email ?? '—' }}</td>
                        <td>{{ $contact->is_primary ? 'Ja' : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--c-muted);padding:16px">Keine Ansprechpartner</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Tab: Logistik ── --}}
@if($activeTab === 'logistics')
<div class="card">
    <div class="card-header">Logistiktermine</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Art</th>
                    <th>Status</th>
                    <th>Datum</th>
                    <th>Uhrzeit</th>
                    <th>Kontakt</th>
                    <th>Flexibilität</th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrence->logisticsAppointments as $appt)
                    <tr>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::logisticsTypes(), $appt->type) }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::logisticsStatuses(), $appt->status) }}</td>
                        <td>{{ \Carbon\Carbon::parse($appt->scheduled_date)->format('d.m.Y') }}</td>
                        <td>{{ $appt->time_from ? substr($appt->time_from, 0, 5) : '—' }}{{ $appt->time_to ? ' – ' . substr($appt->time_to, 0, 5) : '' }}</td>
                        <td>{{ $appt->contact_name ?? '—' }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::timeFlexibility(), $appt->time_flexibility) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--c-muted);padding:16px">Keine Termine</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Tab: Festbedarf ── --}}
@if($activeTab === 'rental')
<div class="card">
    <div class="card-header">Festbedarf-Reservierungen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikel-ID</th>
                    <th>Menge</th>
                    <th>Typ</th>
                    <th>Status</th>
                    <th>Von</th>
                    <th>Bis</th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrence->rentalReservations as $res)
                    <tr @if($res->reservation_status === 'conflict') style="background:#fee2e2" @endif>
                        <td>{{ $res->article_id }}</td>
                        <td>{{ number_format((float)$res->quantity, 0, ',', '.') }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::reservationTypes(), $res->reservation_type) }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::reservationStatuses(), $res->reservation_status) }}</td>
                        <td>{{ \Carbon\Carbon::parse($res->date_from)->format('d.m.Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($res->date_to)->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--c-muted);padding:16px">Keine Reservierungen</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Tab: Vorjahr ── --}}
@if($activeTab === 'previous-year')
@if($previousYear)
    <div class="card" style="max-width:600px">
        <div class="card-header">Vorjahres-Veranstaltung ({{ $previousYear->event_year }})</div>
        <div style="padding:16px">
            <p><strong>Titel:</strong> {{ $previousYear->title }}</p>
            <p><strong>Status:</strong>
                @include('admin.events._partials.event-status-badge', ['status' => $previousYear->event_status])
            </p>
            <p><strong>Gäste:</strong> {{ $previousYear->actual_guests ?? $previousYear->expected_guests ?? '—' }}</p>
            <a href="{{ route('admin.events.show', $previousYear) }}" class="btn btn-outline">Vorjahr öffnen</a>
        </div>
    </div>
@elseif($occurrence->event_series_id)
    <p style="color:var(--c-muted)">Kein Vorjahreseintrag in der Serie gefunden.</p>
@else
    <p style="color:var(--c-muted)">Diese Veranstaltung ist keiner Serie zugeordnet.</p>
@endif
@endif

{{-- ── Tab: Nachkalkulation ── --}}
@if($activeTab === 'post-calc')
<div class="card" style="max-width:700px">
    <div class="card-header">Nachkalkulation</div>
    @if($occurrence->postCalculation)
        @php $pc = $occurrence->postCalculation; @endphp
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><strong>Gäste tatsächlich</strong><br>{{ $pc->actual_guests ?? '—' }}</div>
            <div><strong>Gäste geplant</strong><br>{{ $pc->planned_guests ?? '—' }}</div>
            <div><strong>Umsatz pro Gast</strong><br>{{ $pc->revenue_per_guest_milli !== null ? number_format($pc->revenue_per_guest_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</div>
            <div><strong>Abgeschlossen</strong><br>{{ $pc->completed_at?->format('d.m.Y') ?? 'Nein' }}</div>
        </div>
        @if($pc->recommendation_next_year)
            <div style="padding:0 16px 16px">
                <strong>Empfehlung für nächstes Jahr:</strong>
                <p style="white-space:pre-wrap">{{ $pc->recommendation_next_year }}</p>
            </div>
        @endif
    @else
        <div style="padding:16px;color:var(--c-muted)">Noch keine Nachkalkulation vorhanden.</div>
    @endif
</div>
@endif

{{-- ── Tab: Aufgaben ── --}}
@if($activeTab === 'tasks')
<div class="card">
    <div class="card-header">Aufgaben</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Typ</th>
                    <th>Status</th>
                    <th>Fällig</th>
                    <th>Zugewiesen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrence->tasks as $task)
                    <tr>
                        <td>{{ $task->title }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::taskTypes(), $task->task_type) }}</td>
                        <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::taskStatuses(), $task->status) }}</td>
                        <td>{{ $task->due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $task->assignedUser?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:16px">Keine Aufgaben</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Tab: Importdaten ── --}}
@if($activeTab === 'import')
<div class="card">
    <div class="card-header">Import-Links</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>System</th>
                    <th>Tabelle</th>
                    <th>Datensatz-ID</th>
                    <th>Importiert am</th>
                    <th>Zuletzt gesehen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrence->importLinks as $link)
                    <tr>
                        <td>{{ $link->source_system }}</td>
                        <td>{{ $link->source_table ?? '—' }}</td>
                        <td>{{ $link->source_record_id }}</td>
                        <td>{{ $link->imported_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $link->last_seen_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:16px">Keine Import-Links</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Tab: Änderungsverlauf ── --}}
@if($activeTab === 'audit')
<div class="card">
    <div class="card-header">Änderungsverlauf</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Aktion</th>
                    <th>Benutzer</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>
                            @if($log->meta_json)
                                <details><summary style="cursor:pointer;font-size:.75rem">Details</summary>
                                    <pre style="font-size:.7rem;margin:4px 0">{{ json_encode($log->meta_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--c-muted);padding:16px">Keine Einträge</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
