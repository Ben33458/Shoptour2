@extends('admin.layout')

@section('title', 'Kunde: ' . ($customer->company_name ?: trim($customer->first_name . ' ' . $customer->last_name) ?: $customer->customer_number))

@section('actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    <a href="{{ route('admin.debtor.show', $customer) }}" class="btn btn-outline btn-sm">Mahnwesen</a>
    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
@endsection

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif

{{-- ── KPI-Zeile ── --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px">
    <div class="card" style="padding:16px;text-align:center">
        <div class="hint">Kunde seit</div>
        <div style="font-size:1.05rem;font-weight:600">{{ $customer->created_at->format('d.m.Y') }}</div>
        <div style="font-size:.75rem;color:var(--c-muted)">{{ $customer->created_at->diffForHumans() }}</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div class="hint">Bestellungen</div>
        <div style="font-size:1.6rem;font-weight:700">{{ $orderStats['count'] }}</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div class="hint">Gesamtumsatz</div>
        <div style="font-size:1.05rem;font-weight:600">{{ milli_to_eur($orderStats['total_milli']) }}</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div class="hint">Letzte Bestellung</div>
        <div style="font-size:.95rem;font-weight:600">
            {{ $orderStats['last_date'] ? \Carbon\Carbon::parse($orderStats['last_date'])->format('d.m.Y') : '—' }}
        </div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div class="hint">Offener Saldo</div>
        <div style="font-size:1.05rem;font-weight:600;color:{{ $openSaldo > 0 ? 'var(--c-danger)' : 'inherit' }}">
            {{ $openSaldo > 0 ? milli_to_eur((int) $openSaldo) : '—' }}
        </div>
    </div>
</div>

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

        @if($customer->billing_email)
        <div>
            <div class="hint">Rechnungs-E-Mail</div>
            <div>{{ $customer->billing_email }}</div>
        </div>
        @endif

        @if($customer->notification_email)
        <div>
            <div class="hint">Versandbenachrichtigung-E-Mail</div>
            <div>{{ $customer->notification_email }}</div>
        </div>
        @endif

        @if($customer->birth_date)
        <div>
            <div class="hint">Geburtsdatum</div>
            <div>{{ $customer->birth_date->format('d.m.Y') }} <span style="color:var(--c-muted);font-size:.85rem">({{ $customer->birth_date->age }} J.)</span></div>
        </div>
        @endif

        <div>
            <div class="hint">Newsletter</div>
            <div>{{ match($customer->newsletter_consent) { 'all' => 'Alle E-Mails', 'none' => 'Keine', default => 'Nur wichtige Infos' } }}</div>
        </div>

        <div>
            <div class="hint">Versandbenachrichtigung</div>
            <div>{{ $customer->email_notification_shipping ? 'Ja' : 'Nein' }}</div>
        </div>

        @if($customer->kunde_von)
        <div>
            <div class="hint">Herkunft</div>
            <div>{{ $customer->kunde_von === 'kehr' ? 'Getränke Kehr' : 'Kolabri Getränke' }}</div>
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

{{-- ── Liefersperre / Warnstatus ── --}}
@if($customer->delivery_status && $customer->delivery_status !== \App\Models\Pricing\Customer::DELIVERY_NORMAL)
<div class="card" style="margin-bottom:24px;border-left:4px solid {{ $customer->delivery_status === \App\Models\Pricing\Customer::DELIVERY_BLOCKED ? 'var(--c-danger,#dc2626)' : '#f59e0b' }}">
    <div style="padding:14px 20px;display:flex;gap:12px;align-items:flex-start">
        <span style="font-size:1.3rem;line-height:1.4">{{ $customer->delivery_status === \App\Models\Pricing\Customer::DELIVERY_BLOCKED ? '🚫' : '⚠️' }}</span>
        <div>
            <strong>{{ $customer->deliveryStatusLabel() }}</strong>
            @if($customer->delivery_condition)
                · <em>{{ match($customer->delivery_condition) {
                    \App\Models\Pricing\Customer::CONDITION_CASH_ONLY  => 'Nur Barzahlung',
                    \App\Models\Pricing\Customer::CONDITION_PREPAYMENT => 'Vorkasse',
                    \App\Models\Pricing\Customer::CONDITION_STOP_CHECK => 'Lieferstopp geprüft',
                    default => $customer->delivery_condition,
                } }}</em>
            @endif
            @if($customer->delivery_status_note)
                <div style="margin-top:4px;font-size:.9rem;color:var(--c-muted)">{{ $customer->delivery_status_note }}</div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ── Adressen ── --}}
@if($customer->addresses->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Adressen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Typ</th><th>Adresse</th><th>Lieferhinweis</th></tr>
            </thead>
            <tbody>
            @foreach($customer->addresses as $addr)
                <tr>
                    <td>
                        <span style="display:inline-block;padding:2px 7px;border-radius:4px;font-size:.75rem;font-weight:600;
                            {{ $addr->type === 'delivery' ? 'background:#e0f0ff;color:#1a6fb5' : 'background:#f0e0ff;color:#7a1ab5' }}">
                            {{ $addr->type === 'delivery' ? 'Lieferung' : 'Rechnung' }}
                        </span>
                        @if($addr->is_default)
                            <span style="font-size:.7rem;color:#b58a00;margin-left:4px">★ Standard</span>
                        @endif
                        @if($addr->label)
                            <div style="font-size:.78rem;color:var(--c-muted)">{{ $addr->label }}</div>
                        @endif
                    </td>
                    <td>
                        @if($addr->company)<div><strong>{{ $addr->company }}</strong></div>@endif
                        @if($addr->first_name || $addr->last_name)<div>{{ trim($addr->first_name . ' ' . $addr->last_name) }}</div>@endif
                        <div>{{ $addr->street }}{{ $addr->house_number ? ' '.$addr->house_number : '' }}, {{ $addr->zip }} {{ $addr->city }}</div>
                        @if($addr->phone)<div style="color:var(--c-muted);font-size:.85rem">{{ $addr->phone }}</div>@endif
                    </td>
                    <td>{{ $addr->delivery_note ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

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

{{-- ── Sub-User / Team ── --}}
@if($customer->subUsers->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Team-Zugänge (Sub-User)</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>E-Mail</th><th>Status</th><th>Berechtigungen</th></tr>
            </thead>
            <tbody>
            @foreach($customer->subUsers as $su)
            <tr>
                <td>{{ trim(($su->user?->first_name ?? '') . ' ' . ($su->user?->last_name ?? '')) ?: '—' }}</td>
                <td>{{ $su->user?->email ?? '—' }}</td>
                <td>
                    @if($su->active)
                        <span class="badge badge-delivered">aktiv</span>
                    @else
                        <span class="badge badge-cancelled">inaktiv</span>
                    @endif
                </td>
                <td style="font-size:.8rem;color:var(--c-muted)">
                    @php
                        $perms = collect([
                            'bestellen_all'            => 'Bestellen',
                            'bestellen_favoritenliste' => 'Favoritenliste',
                            'rechnungen'               => 'Rechnungen',
                            'adressen'                 => 'Adressen',
                            'sub_users'                => 'Sub-User',
                            'preise_sehen'             => 'Preise',
                        ])->filter(fn($label, $key) => $su->permissions[$key] ?? false)->values()->implode(', ');
                    @endphp
                    {{ $perms ?: '—' }}
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Letzte Bestellungen ── --}}
@if($recentOrders->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Letzte Bestellungen</span>
        <a href="{{ route('admin.orders.index', ['search' => $customer->customer_number]) }}" class="btn btn-outline btn-sm">Alle anzeigen</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Datum</th><th>Bestell-ID</th><th>Status</th><th style="text-align:right">Betrag</th></tr>
            </thead>
            <tbody>
            @foreach($recentOrders as $order)
            <tr>
                <td>{{ $order->created_at->format('d.m.Y') }}</td>
                <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a></td>
                <td><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></td>
                <td style="text-align:right">{{ milli_to_eur($order->total_gross_milli) }}</td>
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
        <div style="display:flex;align-items:center;gap:8px">
            @php $openCount = $vouchers->filter(fn($v) => $v->isOpen())->count(); @endphp
            @if($openCount > 0)
                <span class="badge" style="background:var(--c-danger);color:#fff">{{ $openCount }} offen</span>
                <form method="POST" action="{{ route('admin.debtor.account_statement', $customer) }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Kontoübersicht per E-Mail</button>
                </form>
            @endif
        </div>
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
                    <th>Bezahlt am</th>
                    <th>Zahlungsdauer</th>
                </tr>
            </thead>
            <tbody>
            @foreach($vouchers as $voucher)
                @php $isCr = $voucher->isCreditNote(); @endphp
                <tr>
                    <td style="font-family:monospace;font-size:13px">{{ $voucher->voucher_number ?? '—' }}</td>
                    <td>
                        @if($voucher->voucher_type === 'salesinvoice')
                            <span class="badge">Rechnung</span>
                        @elseif($voucher->voucher_type === 'salescreditnote')
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
                            <strong style="color:{{ $voucher->isCreditNote() ? '#16a34a' : 'var(--c-danger)' }}">
                                {{ $voucher->formattedOpen() }}
                            </strong>
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
                    @php
                        $paidDate = $voucher->payments->max('payment_date');
                    @endphp
                    <td>
                        {{ $paidDate ? \Carbon\Carbon::parse($paidDate)->format('d.m.Y') : '—' }}
                    </td>
                    <td>
                        @if($paidDate && $voucher->voucher_date)
                            @php $days = $voucher->voucher_date->diffInDays(\Carbon\Carbon::parse($paidDate)) @endphp
                            <span style="{{ $days > 30 ? 'color:var(--c-danger)' : '' }}">
                                {{ $days }} Tage
                            </span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            @php
                $sumGesamt = $vouchers->sum(fn($v) => $v->signedTotal());
                $sumOffen  = $vouchers->sum(fn($v) => $v->signedOpen());
            @endphp
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid #e5e7eb">
                    <td colspan="4" style="text-align:right;padding:8px 4px">Summe</td>
                    <td style="text-align:right;padding:8px 4px">
                        {{ number_format($sumGesamt / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td style="text-align:right;padding:8px 4px;color:{{ $sumOffen > 0 ? 'var(--c-danger)' : ($sumOffen < 0 ? '#16a34a' : 'inherit') }}">
                        {{ number_format($sumOffen / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
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

{{-- ── Kommunikationsverlauf ── --}}
@if($customer->communications->isNotEmpty())
<div class="card" style="margin-top:24px;margin-bottom:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Kommunikationsverlauf</span>
        <span style="font-size:.8rem;color:var(--c-muted)">{{ $customer->communications->count() }} Einträge</span>
    </div>
    <div style="padding:0">
        @foreach($customer->communications as $comm)
        <div style="padding:14px 20px;border-bottom:1px solid var(--c-border);display:flex;gap:14px;align-items:flex-start;">
            <div style="min-width:36px;text-align:center;font-size:1.2rem;padding-top:2px;">
                @if($comm->source === 'gmail')   📧
                @elseif($comm->source === 'phone') 📞
                @else                              📝
                @endif
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px;">
                    <a href="{{ route('admin.communications.show', $comm) }}" style="font-weight:500;font-size:.9rem;">
                        {{ $comm->subject ?: '(kein Betreff)' }}
                    </a>
                    <span class="badge {{ $comm->statusBadgeClass() }}" style="font-size:.7rem;">{{ $comm->statusLabel() }}</span>
                    @foreach($comm->tags as $tag)
                        <span style="background:{{ $tag->color ?? '#e5e7eb' }};color:#1f2937;padding:1px 7px;border-radius:10px;font-size:.7rem;">{{ $tag->name }}</span>
                    @endforeach
                </div>
                <div style="font-size:.8rem;color:var(--c-muted);">
                    {{ $comm->sourceLabel() }}
                    @if($comm->from_address) · {{ $comm->from_address }} @endif
                    · {{ $comm->received_at?->format('d.m.Y H:i') ?? '—' }}
                </div>
                @if($comm->snippet)
                <div style="font-size:.8rem;color:var(--c-text);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:600px;">
                    {{ $comm->snippet }}
                </div>
                @endif
            </div>
            <div style="white-space:nowrap;">
                <a href="{{ route('admin.communications.show', $comm) }}" class="btn btn-outline" style="padding:3px 10px;font-size:.75rem;">Details</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Datenquellen ── --}}
<div class="card" style="margin-top:24px;margin-bottom:24px">
    <div class="card-header">Datenquellen</div>
    <div style="padding:16px">
        @php
            $sourceMatches = \App\Models\SourceMatch::where('entity_type', 'customer')
                ->where('local_id', $customer->id)
                ->get()
                ->keyBy('source');
        @endphp

        @foreach(['ninox' => 'Ninox', 'wawi' => 'JTL-WaWi', 'lexoffice' => 'Lexoffice'] as $sourceKey => $sourceLabel)
            @php
                $sm = $sourceMatches->get($sourceKey);
                if ($sourceKey === 'lexoffice') {
                    $isLinked = (bool) $customer->lexoffice_contact_id;
                } elseif ($sourceKey === 'ninox') {
                    $isLinked = (bool) $customer->ninox_kunden_id;
                } else {
                    $isLinked = (bool) $customer->wawi_kunden_id;
                }

                // Fetch last-updated timestamp from the respective source table
                $sourceUpdatedAt = null;
                if ($sourceKey === 'wawi' && $customer->wawi_kunden_id) {
                    $sourceUpdatedAt = \Illuminate\Support\Facades\DB::table('wawi_kunden')
                        ->where('kKunde', $customer->wawi_kunden_id)
                        ->value('updated_at');
                } elseif ($sourceKey === 'ninox' && $customer->ninox_kunden_id) {
                    $sourceUpdatedAt = \Illuminate\Support\Facades\DB::table('ninox_kunden')
                        ->where('ninox_id', $customer->ninox_kunden_id)
                        ->value('ninox_updated_at');
                }
            @endphp
            <div style="border:1px solid var(--c-border);border-radius:6px;padding:12px 16px;margin-bottom:10px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <strong>{{ $sourceLabel }}</strong>
                    @if($isLinked)
                        <span class="badge badge-delivered">verknüpft</span>
                    @else
                        <span class="badge">nicht verknüpft</span>
                    @endif
                </div>

                @if($sourceKey === 'lexoffice' && $customer->lexoffice_contact_id)
                    <div style="font-size:12px;color:var(--c-muted)">ID: {{ $customer->lexoffice_contact_id }}</div>
                @elseif($sourceKey === 'ninox' && $customer->ninox_kunden_id)
                    <div style="font-size:12px;color:var(--c-muted)">Ninox-ID: {{ $customer->ninox_kunden_id }}</div>
                @elseif($sourceKey === 'wawi' && $customer->wawi_kunden_id)
                    <div style="font-size:12px;color:var(--c-muted)">WaWi-ID: {{ $customer->wawi_kunden_id }}</div>
                @endif

                @if($sourceUpdatedAt)
                    <div style="font-size:11px;color:var(--c-muted);margin-top:4px">
                        Zuletzt aktualisiert: {{ \Carbon\Carbon::parse($sourceUpdatedAt)->format('d.m.Y H:i') }}
                    </div>
                @endif

                @if($sm && count($sm->diff_at_match ?? []) > 0)
                    <div style="margin-top:8px">
                        <div style="font-size:12px;color:#f59e0b;font-weight:600;margin-bottom:4px">⚠ Abweichungen bei Verknüpfung:</div>
                        @foreach($sm->diff_at_match as $field => $values)
                            <div style="font-size:12px;padding:3px 0;border-bottom:1px solid var(--c-border)">
                                <span style="color:var(--c-muted)">{{ $field }}:</span>
                                <span style="color:var(--c-text)"> lokal = <em>{{ $values['local'] ?? '—' }}</em></span>
                                <span style="color:var(--c-muted)"> | {{ $sourceLabel }} = <em>{{ $values['source'] ?? '—' }}</em></span>
                            </div>
                        @endforeach
                    </div>
                @elseif($sm && $sm->status === 'confirmed')
                    <div style="font-size:12px;color:#10b981;margin-top:4px">✓ Daten stimmen überein</div>
                @endif

                @if($sm)
                    <div style="font-size:11px;color:var(--c-muted);margin-top:6px">
                        Verknüpft am {{ $sm->confirmed_at?->format('d.m.Y H:i') ?? $sm->created_at->format('d.m.Y H:i') }}
                        @if($sm->matchedBy) · von {{ $sm->matchedBy->name }} @else · automatisch @endif
                    </div>
                @endif

                {{-- Manual WaWi linking form when not yet linked --}}
                @if($sourceKey === 'wawi' && !$isLinked)
                    <form method="POST" action="{{ route('admin.customers.link-wawi', $customer) }}" style="margin-top:12px;display:flex;gap:8px;align-items:flex-end">
                        @csrf
                        <div style="flex:1">
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:4px">WaWi-Kundennummer</label>
                            <input type="text" name="wawi_kunden_nr" value="{{ old('wawi_kunden_nr') }}"
                                placeholder="z.B. K-00123"
                                style="width:100%;padding:6px 10px;border:1px solid var(--c-border);border-radius:4px;font-size:13px;background:var(--c-bg-card);color:var(--c-text)">
                            @error('wawi_kunden_nr')
                                <div style="font-size:11px;color:var(--c-danger);margin-top:3px">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Verknüpfen</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
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
