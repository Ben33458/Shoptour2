@extends('admin.layout')

@section('title', 'Kunde: ' . ($customer->company_name ?: trim($customer->first_name . ' ' . $customer->last_name) ?: $customer->customer_number))

@section('actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
@endsection

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif

{{-- ── Stammdaten ── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Stammdaten</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <div>
            <div class="hint">Kundennummer</div>
            <div style="font-family:monospace;font-size:15px">{{ $customer->customer_number }}</div>
        </div>

        @if($customer->company_name)
        <div>
            <div class="hint">Firma</div>
            <div><strong>{{ $customer->company_name }}</strong></div>
        </div>
        @endif

        @if($customer->first_name || $customer->last_name)
        <div>
            <div class="hint">Name</div>
            <div>{{ trim($customer->first_name . ' ' . $customer->last_name) }}</div>
        </div>
        @endif

        <div>
            <div class="hint">E-Mail</div>
            <div>{{ $customer->email ?? '—' }}</div>
        </div>

        <div>
            <div class="hint">Telefon</div>
            <div>{{ $customer->phone ?? '—' }}</div>
        </div>

        <div>
            <div class="hint">Kundengruppe</div>
            <div>{{ $customer->customerGroup?->name ?? '—' }}</div>
        </div>

        <div>
            <div class="hint">Preisanzeige</div>
            <div>{{ $customer->price_display_mode === 'gross' ? 'Brutto' : 'Netto' }}</div>
        </div>

        <div>
            <div class="hint">Status</div>
            <div>
                @if($customer->active)
                    <span class="badge badge-delivered">aktiv</span>
                @else
                    <span class="badge badge-cancelled">inaktiv</span>
                @endif
            </div>
        </div>

        @if($customer->lexoffice_contact_id)
        <div>
            <div class="hint">Lexoffice-Kontakt-ID</div>
            <div style="font-family:monospace;font-size:11px;color:var(--c-muted)">{{ $customer->lexoffice_contact_id }}</div>
        </div>
        @endif

        @if($customer->delivery_address_text)
        <div style="grid-column:1/-1">
            <div class="hint">Lieferadresse</div>
            <div style="white-space:pre-line">{{ $customer->delivery_address_text }}</div>
        </div>
        @endif

        @if($customer->delivery_note)
        <div style="grid-column:1/-1">
            <div class="hint">Lieferhinweis (für Fahrer)</div>
            <div>{{ $customer->delivery_note }}</div>
        </div>
        @endif

    </div>
</div>

{{-- ── Ansprechpartner ── --}}
@if($customer->contacts->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Ansprechpartner</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Rolle</th><th>Telefon</th><th>E-Mail</th></tr>
            </thead>
            <tbody>
            @foreach($customer->contacts as $contact)
                <tr>
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->role ?? '—' }}</td>
                    <td>{{ $contact->phone ?? '—' }}</td>
                    <td>{{ $contact->email ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Lexoffice-Belege ── --}}
@if(isset($vouchers) && $vouchers->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Belege (Lexoffice)</span>
        @php $openCount = $vouchers->filter(fn($v) => $v->isOpen())->count(); @endphp
        @if($openCount > 0)
            <span class="badge" style="background:var(--c-danger);color:#fff">{{ $openCount }} offen</span>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Belegnr.</th>
                    <th>Typ</th>
                    <th>Datum</th>
                    <th>Fälligkeit</th>
                    <th>Gesamt</th>
                    <th>Offen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($vouchers as $voucher)
                <tr>
                    <td style="font-family:monospace;font-size:13px">{{ $voucher->voucher_number ?? '—' }}</td>
                    <td>
                        @if($voucher->voucher_type === 'salesinvoice')
                            <span class="badge">Rechnung</span>
                        @elseif($voucher->voucher_type === 'creditnote')
                            <span class="badge" style="background:#6366f1;color:#fff">Gutschrift</span>
                        @else
                            <span class="badge">{{ $voucher->voucher_type }}</span>
                        @endif
                    </td>
                    <td>{{ $voucher->voucher_date?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $voucher->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td style="text-align:right">{{ $voucher->formattedTotal() }}</td>
                    <td style="text-align:right">
                        @if($voucher->isOpen())
                            <strong style="color:var(--c-danger)">{{ $voucher->formattedOpen() }}</strong>
                        @else
                            {{ $voucher->formattedOpen() }}
                        @endif
                    </td>
                    <td>
                        @if($voucher->isPaid())
                            <span class="badge badge-delivered">bezahlt</span>
                        @elseif($voucher->voucher_status === 'overdue')
                            <span class="badge badge-cancelled">überfällig</span>
                        @elseif($voucher->isOpen())
                            <span class="badge" style="background:#f59e0b;color:#fff">offen</span>
                        @else
                            <span class="badge">{{ $voucher->voucher_status ?? '—' }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Kundenhistorie & Notizen ── --}}
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Kundenhistorie & Notizen</span>
        @php $openDiffs = $customer->notes->where('type', 'lexoffice_diff')->whereNull('reviewed_at')->count(); @endphp
        @if($openDiffs > 0)
            <span class="badge" style="background:var(--c-danger);color:#fff">
                {{ $openDiffs }} ungeprüfte Abweichung{{ $openDiffs > 1 ? 'en' : '' }}
            </span>
        @endif
    </div>

    @if($customer->notes->isEmpty())
        <div style="padding:24px;color:var(--c-muted);text-align:center">Noch keine Einträge.</div>
    @else
        <div style="padding:0">
        @foreach($customer->notes as $note)
            <div style="padding:16px;border-bottom:1px solid var(--c-border);
                        {{ !$note->isReviewed() && $note->type === 'lexoffice_diff' ? 'background:#fef3c7' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                    <div style="flex:1">
                        {{-- Type badge + subject --}}
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            @if($note->type === 'lexoffice_diff')
                                <span class="badge" style="background:#f59e0b;color:#fff;font-size:11px">Lexoffice</span>
                            @elseif($note->type === 'system')
                                <span class="badge" style="font-size:11px">System</span>
                            @else
                                <span class="badge" style="font-size:11px">Notiz</span>
                            @endif
                            <strong style="font-size:14px">{{ $note->subject }}</strong>
                            @if($note->isReviewed())
                                <span style="color:var(--c-muted);font-size:12px">✓ geprüft</span>
                            @endif
                        </div>

                        {{-- Body --}}
                        @if($note->body)
                            <div style="font-size:13px;color:var(--c-text);white-space:pre-line;margin-bottom:6px">{{ $note->body }}</div>
                        @endif

                        {{-- Meta --}}
                        <div style="font-size:11px;color:var(--c-muted)">
                            {{ $note->created_at->format('d.m.Y H:i') }}
                            @if($note->createdBy)
                                · {{ $note->createdBy->name }}
                            @else
                                · System
                            @endif
                            @if($note->isReviewed())
                                · geprüft {{ $note->reviewed_at->format('d.m.Y H:i') }}
                                @if($note->reviewedBy) von {{ $note->reviewedBy->name }}@endif
                            @endif
                        </div>
                    </div>

                    {{-- Resolve button for unreviewed lexoffice diffs --}}
                    @if(!$note->isReviewed() && $note->type === 'lexoffice_diff')
                        <div>
                            <form method="POST"
                                  action="{{ route('admin.customers.notes.resolve', [$customer, $note]) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">
                                    Als geprüft markieren
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        </div>
    @endif
</div>

{{-- ── Zusammenführen & Löschen ── --}}
<div class="card" style="margin-top:24px;border-color:var(--c-danger)">
    <div class="card-header" style="color:var(--c-danger)">Gefahrenzone</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:24px">

        {{-- Merge --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Duplikat zusammenführen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Geben Sie die Kundennummer des Duplikats ein. Alle Bestellungen, Notizen und
                Belege werden auf diesen Kunden übertragen, das Duplikat wird gelöscht.
            </div>
            @if($errors->has('source_customer_number'))
                <div style="color:var(--c-danger);font-size:13px;margin-bottom:8px">{{ $errors->first('source_customer_number') }}</div>
            @endif
            <form method="POST" action="{{ route('admin.customers.merge', $customer) }}"
                  onsubmit="return confirm('Wirklich zusammenführen? Das Duplikat wird dauerhaft gelöscht!')">
                @csrf
                <div style="display:flex;gap:8px">
                    <input type="text" name="source_customer_number"
                           placeholder="Kundennr. des Duplikats"
                           style="flex:1;padding:6px 10px;border:1px solid var(--c-border);border-radius:4px;font-family:monospace"
                           required>
                    <button type="submit" class="btn btn-sm"
                            style="background:var(--c-danger);color:#fff;border-color:var(--c-danger)">
                        Zusammenführen
                    </button>
                </div>
            </form>
        </div>

        {{-- Delete --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Kunden löschen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Nur möglich, wenn keine Bestellungen vorhanden sind.
            </div>
            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                  onsubmit="return confirm('Kunden wirklich dauerhaft löschen?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm"
                        style="background:var(--c-danger);color:#fff;border-color:var(--c-danger)">
                    Kunden löschen
                </button>
            </form>
        </div>

    </div>
</div>

@endsection
