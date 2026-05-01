@extends('admin.layout')

@section('title', 'Debitorenprofil: ' . $customer->displayName())

@section('actions')
    <a href="{{ route('admin.debtor.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline btn-sm">Kundenkonto</a>
@endsection

@section('content')


@php $blocked = session('dunning_blocked'); @endphp

<style>
.alert-success {
    padding:12px 16px;background:color-mix(in srgb,#10b981 15%,var(--c-surface));
    border:1px solid #10b981;border-radius:6px;color:#10b981;
}
.alert-error {
    padding:12px 16px;background:color-mix(in srgb,#ef4444 15%,var(--c-surface));
    border:1px solid #ef4444;border-radius:6px;color:#ef4444;
}
/* Zeilenhighlight überfällig: subtiler Akzent ohne hardcoded Hellfarbe */
.row-overdue { border-left: 3px solid #f97316; }
.row-overdue td:first-child { padding-left: 9px; }

/* Note-Karten: Typ-Akzent per linkem Rand, kein Hintergrund-Override */
.note-card {
    border: 1px solid var(--c-border);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
}
.note-card.note-dispute   { border-left: 3px solid #f59e0b; }
.note-card.note-warning   { border-left: 3px solid #ef4444; }
.note-card.note-payment_promise { border-left: 3px solid #10b981; }
.note-card.note-done      { opacity: .6; }

/* Hold-Box: Rand statt Hintergrundfarbe */
.hold-active {
    padding: 10px 12px;
    border-left: 3px solid #ef4444;
    border-radius: 4px;
    background: var(--c-bg);
    margin-bottom: 12px;
    color: var(--c-text);
}
</style>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">

{{-- ── Linke Spalte ── --}}
<div>
    {{-- Debitorenprofil-Karte --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Debitorenprofil — {{ $customer->displayName() }} [{{ $customer->customer_number }}]</div>
        <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
            <div>
                <div class="hint">Offen gesamt</div>
                <div style="font-size:20px;font-weight:700;color:#dc2626">
                    {{ number_format($profile['open_total_milli'] / 1_000_000, 2, ',', '.') }} €
                </div>
            </div>
            <div>
                <div class="hint">Offene Rechnungen</div>
                <div style="font-size:20px;font-weight:700">{{ $profile['open_count'] }}</div>
            </div>
            <div>
                <div class="hint">Älteste Fälligkeit</div>
                <div>
                    @if($profile['oldest_due_date'])
                        {{ \Carbon\Carbon::parse($profile['oldest_due_date'])->format('d.m.Y') }}
                        @if($profile['days_overdue'] > 0)
                            <br><span style="color:#dc2626;font-weight:600">{{ $profile['days_overdue'] }} Tage überfällig</span>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="hint">Aktuelle Mahnstufe</div>
                <div>
                    @if($profile['dunning_level'] > 0)
                        <span class="badge badge-pending">Stufe {{ $profile['dunning_level'] }}</span>
                    @else
                        <span class="text-muted">Nicht gemahnt</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="hint">Letzte Mahnung</div>
                <div>
                    @if($profile['last_dunned_at'])
                        {{ $profile['last_dunned_at']->format('d.m.Y') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="hint">Letzter Zahlungseingang</div>
                <div>
                    @if($profile['last_payment_date'])
                        {{ \Carbon\Carbon::parse($profile['last_payment_date'])->format('d.m.Y') }}
                    @else
                        <span class="text-muted">Keine Daten</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Bestellverhalten --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Bestellverhalten</div>
        @php $r = $profile['order_rhythm']; @endphp
        <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><div class="hint">Bestellrhythmus</div><div>{{ $r['label'] }}</div></div>
            <div><div class="hint">Ø Interval</div><div>{{ $r['avg_interval'] !== null ? $r['avg_interval'] . ' Tage' : '—' }}</div></div>
            <div><div class="hint">Letzte Bestellung</div><div>{{ $r['last_order'] ? \Carbon\Carbon::parse($r['last_order'])->format('d.m.Y') : '—' }}</div></div>
            <div>
                <div class="hint">Nächste Bestellung erwartet</div>
                <div>
                    @if($r['next_expected'])
                        {{ \Carbon\Carbon::parse($r['next_expected'])->format('d.m.Y') }}
                        @php $days = $r['days_to_next']; @endphp
                        @if($days !== null)
                            @if($days < 0)
                                <span style="color:#dc2626;font-weight:600"> (überfällig seit {{ abs($days) }} Tagen)</span>
                            @else
                                <span class="text-muted"> (in {{ $days }} Tagen)</span>
                            @endif
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Zahlungsverhalten --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Zahlungsverhalten & Risiko</div>
        @php $p = $profile['payment_behavior']; @endphp
        <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
                <div class="hint">Ø Zahlungsverzögerung</div>
                <div>{{ $p['avg_delay_days'] !== null ? $p['avg_delay_days'] . ' Tage' : '—' }}</div>
            </div>
            <div>
                <div class="hint">Zahlungsmoral</div>
                @php
                    $scoreColor = match($p['score']) {
                        'sehr_gut' => '#10b981', 'gut' => '#34d399',
                        'mittel'   => '#f59e0b', 'schlecht' => '#f97316',
                        default    => '#dc2626'
                    };
                @endphp
                <div><span style="color:{{ $scoreColor }};font-weight:600">{{ $p['score_label'] }}</span></div>
            </div>
            <div>
                <div class="hint">Einzelfall oder Muster</div>
                <div>{{ $p['pattern_label'] }}</div>
            </div>
            <div>
                <div class="hint">Risikoeinschätzung</div>
                @php
                    $riskColor = match($profile['risk_level']) {
                        'niedrig' => '#10b981', 'mittel' => '#f59e0b',
                        'hoch'    => '#f97316', default  => '#dc2626'
                    };
                    $riskLabel = match($profile['risk_level']) {
                        'niedrig' => 'Niedrig', 'mittel' => 'Mittel',
                        'hoch'    => 'Hoch',    default  => 'Kritisch'
                    };
                @endphp
                <div><span style="color:{{ $riskColor }};font-weight:700;font-size:15px">{{ $riskLabel }}</span></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Rechte Spalte: Mahnversand + Hold + Lieferfreigabe ── --}}
<div>

    {{-- Mahnversand --}}
    @php
        $dunningEmail   = $customer->billing_email ?? $customer->email ?? '—';
        $currentLevel   = $profile['dunning_level'] ?? 0;
        $lastDunnedAt   = $profile['last_dunned_at'] ?? null;
        $hasOpenInvoices = $openVouchers->isNotEmpty();
    @endphp
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Mahnversand</div>
        <div style="padding:16px">

            {{-- Key facts --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;font-size:.85rem">
                <div>
                    <div class="hint">Empfänger-E-Mail</div>
                    <div style="word-break:break-all">{{ $dunningEmail }}</div>
                </div>
                <div>
                    <div class="hint">Aktuelle Mahnstufe</div>
                    <div>
                        @if($currentLevel > 0)
                            <span class="badge badge-pending">Stufe {{ $currentLevel }}</span>
                        @else
                            <span class="text-muted">Keine</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="hint">Letzte Mahnung</div>
                    <div>{{ $lastDunnedAt ? $lastDunnedAt->format('d.m.Y') : '—' }}</div>
                </div>
                <div>
                    <div class="hint">Offene Rechnungen</div>
                    <div>{{ $openVouchers->count() }}</div>
                </div>
            </div>

            {{-- Blocked reasons (from previous form submit) --}}
            @if($blocked)
                <div style="margin-bottom:12px;padding:10px 12px;background:color-mix(in srgb,#f59e0b 10%,var(--c-surface));border:1px solid #f59e0b;border-radius:6px;font-size:.85rem">
                    <div style="font-weight:600;margin-bottom:6px;color:#92400e">Sperrhinweise:</div>
                    <ul style="margin:0 0 10px;padding-left:18px;color:var(--c-text)">
                        @if($blocked['hold'])
                            <li>Kunde ist auf <strong>Hold</strong> gesetzt</li>
                        @endif
                        @foreach($blocked['blocking_notes'] as $note)
                            <li><strong>{{ $note['type'] }}</strong>: {{ Str::limit($note['body'], 60) }}</li>
                        @endforeach
                        @if($blocked['all_invoices_blocked'])
                            <li>Alle Rechnungen für Mahnwesen <strong>gesperrt</strong></li>
                        @endif
                        @if($blocked['threshold_not_met'])
                            <li><strong>Sperrfrist</strong> noch nicht erfüllt</li>
                        @endif
                    </ul>
                    @if($blocked['can_force'])
                        <form method="POST" action="{{ route('admin.dunning.send_quick', $customer) }}"
                              style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                            @csrf
                            <input type="hidden" name="force" value="1">
                            <select name="channel" style="flex:1;min-width:0;padding:.3rem .5rem;border:1px solid #f59e0b;border-radius:5px;font-size:.82rem;background:var(--c-surface)">
                                <option value="email">Per E-Mail</option>
                                <option value="post">Per Briefpost (+ E-Mail-Kopie)</option>
                            </select>
                            <label style="display:flex;align-items:center;gap:5px;font-size:.82rem;width:100%;cursor:pointer">
                                <input type="checkbox" name="copy_to_me" value="1">
                                Kopie an mich ({{ auth()->user()->email }})
                            </label>
                            <button type="submit" class="btn btn-sm" style="background:#f59e0b;color:#fff;border:none;white-space:nowrap">
                                Trotzdem senden
                            </button>
                        </form>
                    @else
                        <p style="font-size:.82rem;color:var(--c-muted);margin:0">Kein Mahnversand möglich — keine offenen Rechnungen.</p>
                    @endif
                </div>
            @elseif($hasOpenInvoices)
                <form method="POST" action="{{ route('admin.dunning.send_quick', $customer) }}">
                    @csrf
                    <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;margin-bottom:10px;cursor:pointer">
                        <input type="checkbox" name="copy_to_me" value="1">
                        Kopie an mich ({{ auth()->user()->email }})
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%">
                        Mahnung senden
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.debtor.account_statement', $customer) }}" style="margin-top:8px">
                    @csrf
                    <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;margin-bottom:10px;cursor:pointer">
                        <input type="checkbox" name="copy_to_me" value="1">
                        Auch an mich ({{ auth()->user()->email }})
                    </label>
                    <button type="submit" class="btn btn-outline btn-sm" style="width:100%">
                        Kontoübersicht senden
                    </button>
                </form>
            @else
                <p style="font-size:.85rem;color:var(--c-muted);margin:0">Keine offenen Rechnungen — kein Mahnversand möglich.</p>
            @endif

        </div>
    </div>

    {{-- Hold / Klärfall --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Hold / Klärfall</div>
        <div style="padding:16px">
            @if($customer->debt_hold)
                <div class="hold-active">
                    <strong>Hold aktiv</strong>
                    @if($customer->debt_hold_reason)
                        <p style="margin:4px 0 0">{{ $customer->debt_hold_reason }}</p>
                    @endif
                </div>
            @endif
            <form method="POST" action="{{ route('admin.debtor.hold', $customer) }}">
                @csrf @method('POST')
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <input type="checkbox" name="debt_hold" value="1" @checked($customer->debt_hold)>
                    Hold / Klärfall aktiv (Mahnlauf ausschließen)
                </label>
                <textarea name="debt_hold_reason" class="form-control" rows="2"
                    placeholder="Begründung (optional)" style="margin-bottom:10px">{{ $customer->debt_hold_reason }}</textarea>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </form>
        </div>
    </div>

    {{-- Lieferfreigabe --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Lieferfreigabe</div>
        <div style="padding:16px">
            @php
                $deliveryColor = match($customer->delivery_status ?? 'normal') {
                    'warning' => '#f59e0b', 'blocked' => '#dc2626', default => '#10b981'
                };
            @endphp
            <div style="font-size:16px;font-weight:700;color:{{ $deliveryColor }};margin-bottom:12px">
                {{ $customer->deliveryStatusLabel() }}
            </div>
            @if($customer->delivery_condition)
                <div class="hint" style="margin-bottom:8px">
                    Zahlungshinweis:
                    @php
                        $condLabel = match($customer->delivery_condition) {
                            'cash_only'  => 'Nur Bar/EC',
                            'prepayment' => 'Vorkasse empfohlen',
                            'stop_check' => 'Lieferstopp prüfen',
                            default      => '—'
                        };
                    @endphp
                    {{ $condLabel }}
                </div>
            @endif
            @if($customer->delivery_status_note)
                <p class="text-muted" style="font-size:13px;margin-bottom:12px">{{ $customer->delivery_status_note }}</p>
            @endif

            <form method="POST" action="{{ route('admin.debtor.delivery', $customer) }}">
                @csrf @method('POST')
                <div style="margin-bottom:8px">
                    <select name="delivery_status" class="form-control">
                        <option value="normal"  @selected(($customer->delivery_status ?? 'normal')==='normal')>Freigegeben</option>
                        <option value="warning" @selected($customer->delivery_status==='warning')>Warnhinweis</option>
                        <option value="blocked" @selected($customer->delivery_status==='blocked')>Liefersperre</option>
                    </select>
                </div>
                <div style="margin-bottom:8px">
                    <select name="delivery_condition" class="form-control">
                        <option value="">Kein besonderer Hinweis</option>
                        <option value="cash_only"  @selected($customer->delivery_condition==='cash_only')>Nur Bar/EC</option>
                        <option value="prepayment" @selected($customer->delivery_condition==='prepayment')>Vorkasse empfohlen</option>
                        <option value="stop_check" @selected($customer->delivery_condition==='stop_check')>Lieferstopp prüfen</option>
                    </select>
                </div>
                <div style="margin-bottom:10px">
                    <input type="text" name="delivery_status_note" class="form-control"
                        placeholder="Notiz (optional)"
                        value="{{ $customer->delivery_status_note }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </form>
        </div>
    </div>
</div>

</div>{{-- end grid --}}

{{-- ── Offene Rechnungen & Gutschriften ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Offene Rechnungen &amp; Gutschriften</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Belegnr.</th>
                    <th>Typ</th>
                    <th>Datum</th>
                    <th>Fälligkeit</th>
                    <th class="text-right">Betrag</th>
                    <th class="text-right">Offen</th>
                    <th>Status</th>
                    <th>Mahnstufe</th>
                    <th>Gesperrt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($openVouchers as $voucher)
                @php $isCredit = $voucher->isCreditNote(); @endphp
                <tr @if(!$isCredit && $voucher->daysOverdue() > 0) class="row-overdue" @endif>
                    <td><code>{{ $voucher->voucher_number ?? '—' }}</code></td>
                    <td>
                        @if($isCredit)
                            <span class="badge" style="background:#6366f1;color:#fff">Gutschrift</span>
                        @else
                            <span class="badge">Rechnung</span>
                        @endif
                    </td>
                    <td>{{ $voucher->voucher_date?->format('d.m.Y') ?? '—' }}</td>
                    <td>
                        {{ $voucher->due_date?->format('d.m.Y') ?? '—' }}
                        @if(!$isCredit && $voucher->daysOverdue() > 0)
                            <span style="color:#dc2626;font-size:11px"> +{{ $voucher->daysOverdue() }}d</span>
                        @endif
                    </td>
                    <td class="text-right">{{ $voucher->formattedTotal() }}</td>
                    <td class="text-right" style="font-weight:600;color:{{ $isCredit ? '#16a34a' : '#dc2626' }}">
                        {{ $voucher->formattedOpen() }}
                    </td>
                    <td>
                        <span class="badge badge-{{ $voucher->voucher_status }}">{{ $voucher->voucher_status }}</span>
                    </td>
                    <td>{{ $isCredit ? '—' : $voucher->dunning_level }}</td>
                    <td>
                        @if(!$isCredit && $voucher->is_dunning_blocked)
                            <span style="color:#dc2626" title="{{ $voucher->dunning_block_reason }}">Gesperrt</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap">
                            <a href="{{ route('admin.debtor.voucher.pdf', $voucher) }}" target="_blank"
                               class="btn btn-outline btn-sm">PDF</a>
                            @if(!$isCredit)
                            <form method="POST" action="{{ route('admin.debtor.voucher.block', $voucher) }}" style="display:inline">
                                @csrf @method('POST')
                                <input type="hidden" name="is_dunning_blocked" value="{{ $voucher->is_dunning_blocked ? '0' : '1' }}">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    {{ $voucher->is_dunning_blocked ? 'Entsperren' : 'Sperren' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding:20px">Keine offenen Rechnungen.</td>
                </tr>
            @endforelse
            </tbody>
            @if($openVouchers->isNotEmpty())
            @php
                $sumTotal = $openVouchers->sum(fn($v) => $v->signedTotal());
                $sumOpen  = $openVouchers->sum(fn($v) => $v->signedOpen());
            @endphp
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid #e5e7eb">
                    <td colspan="4" style="text-align:right;padding:8px 4px">Summe</td>
                    <td class="text-right" style="padding:8px 4px">
                        {{ number_format($sumTotal / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right" style="padding:8px 4px;color:{{ $sumOpen >= 0 ? '#dc2626' : '#16a34a' }}">
                        {{ number_format($sumOpen / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ── Notizen / Klärfälle / Zahlungszusagen ── --}}
<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px">

<div class="card">
    <div class="card-header">Notizen, Aufgaben, Klärfälle</div>
    <div style="padding:16px">

        {{-- Note erstellen --}}
        <form method="POST" action="{{ route('admin.debtor.notes.store', $customer) }}"
              style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
            @csrf
            <div>
                <select name="type" class="form-control">
                    @foreach(\App\Models\Debtor\DebtorNote::$types as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="lexoffice_voucher_id" class="form-control">
                    <option value="">Kein Bezug zu Rechnung</option>
                    @foreach($openVouchers as $v)
                        <option value="{{ $v->id }}">{{ $v->voucher_number }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1/-1">
                <textarea name="body" class="form-control" rows="2"
                    placeholder="Text / Beschreibung ..." required></textarea>
            </div>
            <div>
                <input type="date" name="promised_date" class="form-control" placeholder="Zahlungsdatum (optional)">
            </div>
            <div>
                <input type="date" name="due_at" class="form-control" placeholder="Wiedervorlage (optional)">
            </div>
            <div style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>

        {{-- Note-Liste --}}
        @forelse($debtorNotes as $note)
            <div class="note-card note-{{ $note->type }} @if($note->status === 'done') note-done @endif">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div>
                        <span class="badge" style="margin-right:6px">{{ $note->typeName() }}</span>
                        @if($note->voucher)
                            <span class="text-muted" style="font-size:12px">{{ $note->voucher->voucher_number }}</span>
                        @endif
                        @if($note->status === 'done')
                            <span class="badge badge-delivered" style="margin-left:6px">Erledigt</span>
                        @endif
                    </div>
                    <div style="display:flex;gap:6px">
                        @if($note->isOpen())
                        <form method="POST" action="{{ route('admin.debtor.notes.status', $note) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="done">
                            <button type="submit" class="btn btn-outline btn-sm" title="Erledigt">✓</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('admin.debtor.notes.destroy', $note) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" title="Löschen"
                                onclick="return confirm('Eintrag löschen?')">×</button>
                        </form>
                    </div>
                </div>
                <p style="margin:8px 0 4px">{{ $note->body }}</p>
                <div class="text-muted" style="font-size:11px">
                    {{ $note->created_at->format('d.m.Y H:i') }}
                    @if($note->createdBy) · {{ $note->createdBy->name }} @endif
                    @if($note->promised_date) · Zahlung bis {{ $note->promised_date->format('d.m.Y') }} @endif
                    @if($note->due_at) · Wiedervorlage: {{ $note->due_at->format('d.m.Y') }} @endif
                </div>
            </div>
        @empty
            <p class="text-muted">Noch keine Notizen.</p>
        @endforelse
    </div>
</div>

{{-- ── Beleghistorie ── --}}
<div class="card">
    <div class="card-header">Rechnungen &amp; Gutschriften (letzte 20)</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Belegnr.</th>
                    <th>Typ</th>
                    <th>Datum</th>
                    <th class="text-right">Offen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($allVouchers as $v)
                @php $isCr = $v->isCreditNote(); @endphp
                <tr>
                    <td style="font-size:12px">{{ $v->voucher_number ?? '—' }}</td>
                    <td>
                        @if($isCr)
                            <span class="badge" style="background:#6366f1;color:#fff">Gutschrift</span>
                        @else
                            <span class="badge">Rechnung</span>
                        @endif
                    </td>
                    <td style="font-size:12px">{{ $v->voucher_date?->format('d.m.Y') }}</td>
                    <td class="text-right" style="font-size:12px">
                        @if($v->signedOpen() !== 0)
                            <span style="color:{{ $v->signedOpen() > 0 ? '#dc2626' : '#16a34a' }}">
                                {{ $v->formattedOpen() }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $v->voucher_status }}">{{ $v->voucher_status }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
            @if($allVouchers->isNotEmpty())
            @php $sumAllOpen = $allVouchers->sum(fn($v) => $v->signedOpen()); @endphp
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid #e5e7eb">
                    <td colspan="3" style="text-align:right;padding:8px 4px">Summe offen</td>
                    <td class="text-right" style="padding:8px 4px;color:{{ $sumAllOpen >= 0 ? '#dc2626' : '#16a34a' }}">
                        {{ number_format($sumAllOpen / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

</div>

@endsection
