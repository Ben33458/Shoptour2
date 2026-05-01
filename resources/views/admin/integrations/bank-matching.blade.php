@extends('admin.layout')

@section('title', 'Nicht zugeordnete Umsätze')

@section('actions')
    <a href="{{ route('admin.integrations.lexoffice') }}" class="btn btn-outline btn-sm">← Lexoffice</a>
@endsection

@push('styles')
<style>
.bm-table { width:100%; border-collapse:collapse; font-size:13px; }
.bm-thead { background:var(--c-bg); border-bottom:2px solid var(--c-border); }
.bm-th    { padding:10px 14px; text-align:left; font-weight:600; color:var(--c-muted); white-space:nowrap; }
.bm-th-r  { padding:10px 14px; text-align:right; font-weight:600; color:var(--c-muted); white-space:nowrap; }
.bm-th-c  { padding:10px 14px; text-align:center; font-weight:600; color:var(--c-muted); white-space:nowrap; }
.bm-row   { border-bottom:1px solid var(--c-border); background:var(--c-surface); vertical-align:middle; }
.bm-row.confirmed { background:color-mix(in srgb, #059669 8%, var(--c-surface)); }
.bm-row:hover { background:var(--c-bg) !important; }
.bm-td    { padding:9px 14px; color:var(--c-text); }
.bm-td-muted { padding:9px 14px; color:var(--c-muted); white-space:nowrap; }
.bm-td-r  { padding:9px 14px; text-align:right; white-space:nowrap; color:var(--c-text); }
.bm-td-c  { padding:9px 14px; text-align:center; white-space:nowrap; }
.bm-detail { background:var(--c-bg); border-bottom:2px solid var(--c-border); }

.bm-select, .bm-input, .bm-textarea {
    background: var(--c-surface);
    color: var(--c-text);
    border: 1px solid var(--c-border);
    border-radius: 5px;
    font-size: 12px;
    font-family: inherit;
}
.bm-select  { padding:3px 6px; }
.bm-input   { padding:3px 8px; }
.bm-textarea { padding:6px 8px; resize:vertical; width:100%; }
.bm-select:focus, .bm-input:focus, .bm-textarea:focus {
    outline:none; border-color:var(--c-primary,#3b82f6);
}

.bm-action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    padding:3px 8px;
    border:1px solid var(--c-border);
    border-radius:5px;
    font-size:11px;
    color:var(--c-text);
    background:var(--c-surface);
    cursor:pointer;
    text-decoration:none;
    white-space:nowrap;
    transition:background .1s;
}
.bm-action-btn:hover { background:var(--c-bg); }
.bm-action-btn.note-set { border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,var(--c-surface)); color:#92400e; }
.bm-action-btn.confirmed-btn { border-color:#059669; background:color-mix(in srgb,#059669 12%,var(--c-surface)); color:#059669; }

.bm-badge-suggested { font-size:10px; padding:1px 5px; border-radius:999px; background:color-mix(in srgb,#f59e0b 18%,var(--c-surface)); color:#92400e; white-space:nowrap; }

.bm-meta-table td { padding:2px 10px 2px 0; font-size:12px; }
.bm-meta-label { color:var(--c-muted); }
</style>
@endpush

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,#059669 12%,var(--c-surface));border:1px solid #059669;border-radius:6px;color:#059669;font-size:13px">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,#ef4444 12%,var(--c-surface));border:1px solid #ef4444;border-radius:6px;color:#ef4444;font-size:13px">{{ session('error') }}</div>
@endif

{{-- ── Toolbar ────────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:16px">
    <div style="padding:12px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:space-between">

        <div style="display:flex;gap:20px;font-size:13px">
            <span style="color:#d97706;font-weight:600">{{ $stats['unconfirmed'] }} ausstehend</span>
            <span style="color:#059669;font-weight:600">{{ $stats['confirmed'] }} bestätigt</span>
            <span style="color:var(--c-muted)">{{ $stats['total'] }} Einträge · {{ $year }}</span>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <form method="GET" action="{{ route('admin.integrations.lexoffice.bank-matching') }}"
                  style="display:flex;gap:6px;align-items:center">
                <select name="year" onchange="this.form.submit()" class="bm-select" style="font-size:13px;padding:4px 8px">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()" class="bm-select" style="font-size:13px;padding:4px 8px">
                    <option value="unconfirmed" {{ $status === 'unconfirmed' ? 'selected' : '' }}>Ausstehend</option>
                    <option value="confirmed"   {{ $status === 'confirmed'   ? 'selected' : '' }}>Bestätigt</option>
                    <option value="all"         {{ $status === 'all'         ? 'selected' : '' }}>Alle</option>
                </select>
            </form>
            <form method="POST" action="{{ route('admin.integrations.lexoffice.bank-matching.pull') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">↻ Aus Lexoffice laden</button>
            </form>
        </div>
    </div>
</div>

{{-- ── Table ──────────────────────────────────────────────────────────────── --}}
<div class="card" style="overflow:hidden">
<table class="bm-table">
<thead class="bm-thead">
    <tr>
        <th class="bm-th">Datum</th>
        <th class="bm-th">Fällig</th>
        <th class="bm-th">Rechnungsnr.</th>
        <th class="bm-th">Kontaktname (Lexoffice)</th>
        <th class="bm-th-r">Gesamt</th>
        <th class="bm-th-r">Offen</th>
        <th class="bm-th-c">Status</th>
        <th class="bm-th">Kundenzuordnung</th>
        <th class="bm-th-c">Aktionen</th>
    </tr>
</thead>
<tbody>
@forelse($vouchers as $voucher)
    @php
        $confirmed  = $voucher->manually_confirmed_at !== null;
        $suggestion = $suggestions[$voucher->id] ?? null;
        $raw        = $voucher->raw_json ?? [];
        $openAmount = isset($raw['openAmount']) ? number_format((float)$raw['openAmount'], 2, ',', '.') . ' €' : '—';
        $isOverdue  = $voucher->voucher_status === 'overdue';
        $statusLabel = match($voucher->voucher_status) {
            'open'    => ['label' => 'Offen',       'bg' => 'color-mix(in srgb,#f59e0b 18%,var(--c-surface))', 'color' => '#d97706'],
            'overdue' => ['label' => 'Überfällig',  'bg' => 'color-mix(in srgb,#ef4444 15%,var(--c-surface))', 'color' => '#dc2626'],
            'paid'    => ['label' => 'Bezahlt',     'bg' => 'color-mix(in srgb,#059669 15%,var(--c-surface))', 'color' => '#059669'],
            'paidoff' => ['label' => 'Ausgeglichen','bg' => 'color-mix(in srgb,#059669 15%,var(--c-surface))', 'color' => '#059669'],
            default   => ['label' => $voucher->voucher_status, 'bg' => 'var(--c-bg)', 'color' => 'var(--c-muted)'],
        };
    @endphp

    {{-- Main row --}}
    <tr id="voucher-{{ $voucher->id }}"
        class="bm-row {{ $confirmed ? 'confirmed' : '' }}">

        <td class="bm-td-muted">{{ $voucher->voucher_date?->format('d.m.Y') ?? '–' }}</td>

        <td class="bm-td-muted" style="{{ $isOverdue ? 'color:#dc2626;font-weight:600' : '' }}">
            {{ $voucher->due_date?->format('d.m.Y') ?? '–' }}
        </td>

        <td class="bm-td" style="font-weight:600">{{ $voucher->voucher_number ?? '–' }}</td>

        <td class="bm-td" style="font-weight:500;max-width:200px">{{ $voucher->contact_name ?? '–' }}</td>

        <td class="bm-td-r" style="font-weight:600">{{ $voucher->formattedTotal() }}</td>

        <td class="bm-td-r" style="{{ $isOverdue ? 'color:#dc2626;font-weight:700' : '' }}">
            {{ $openAmount }}
        </td>

        <td class="bm-td-c">
            <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:{{ $statusLabel['bg'] }};color:{{ $statusLabel['color'] }};font-weight:600">
                {{ $statusLabel['label'] }}
            </span>
        </td>

        {{-- Customer picker --}}
        <td class="bm-td" style="min-width:220px"
            x-data="customerPicker({{ $voucher->id }}, {{ $suggestion ? $suggestion['customer']->id : 'null' }})">
            <div style="display:flex;align-items:center;gap:6px">
                @if($suggestion)
                    <span class="bm-badge-suggested">{{ $suggestion['confidence'] }}%</span>
                @endif
                <select x-model="selectedId" @change="save()"
                        class="bm-select" style="flex:1;min-width:160px;font-size:12px">
                    <option value="">– Kunden wählen –</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                            {{ ($suggestion && $suggestion['customer']->id === $c->id) ? 'selected' : '' }}>
                            {{ $c->company_name ?: trim($c->first_name . ' ' . $c->last_name) }}{{ $c->customer_number ? ' [' . $c->customer_number . ']' : '' }}
                        </option>
                    @endforeach
                </select>
                <span x-show="saving" style="font-size:11px;color:var(--c-muted)">…</span>
                <span x-show="saved && !saving" style="font-size:11px;color:#059669" x-cloak>✓</span>
            </div>
        </td>

        {{-- Actions --}}
        <td class="bm-td-c">
            <div style="display:flex;align-items:center;justify-content:center;gap:4px">

                <a href="{{ route('admin.debtor.voucher.pdf', $voucher) }}" target="_blank"
                   class="bm-action-btn" title="PDF herunterladen">PDF</a>

                @if($voucher->lexoffice_voucher_id)
                    <a href="https://app.lexoffice.de/voucher#/edit/{{ $voucher->lexoffice_voucher_id }}"
                       target="_blank" rel="noopener"
                       class="bm-action-btn" title="In Lexoffice öffnen">LO ↗</a>
                @endif

                <button type="button" class="bm-action-btn {{ $voucher->assignment_note ? 'note-set' : '' }}"
                        onclick="toggleRow({{ $voucher->id }})"
                        title="Notiz / Details">
                    {{ $voucher->assignment_note ? '📝' : '…' }}
                </button>

                <button type="button" id="confirm-btn-{{ $voucher->id }}"
                        class="bm-action-btn {{ $confirmed ? 'confirmed-btn' : '' }}"
                        onclick="toggleConfirm({{ $voucher->id }})"
                        title="{{ $confirmed ? 'Bestätigung zurücksetzen' : 'Als in Lexoffice bestätigt markieren' }}">
                    {{ $confirmed ? '✓ OK' : '○ OK' }}
                </button>
            </div>

            @if($confirmed)
                <div style="font-size:10px;color:#059669;margin-top:3px;text-align:center">
                    {{ $voucher->manually_confirmed_at->format('d.m. H:i') }}
                </div>
            @endif
        </td>
    </tr>

    {{-- Expandable detail row --}}
    <tr id="detail-{{ $voucher->id }}" class="bm-detail" style="display:none">
        <td colspan="9" style="padding:14px 16px">
            <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">

                {{-- Note --}}
                <div x-data="noteSaver({{ $voucher->id }}, {{ json_encode($voucher->assignment_note ?? '') }})"
                     style="flex:1;min-width:280px">
                    <div style="font-size:11px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">Notiz</div>
                    <div style="display:flex;gap:6px;align-items:flex-start">
                        <textarea x-model="note" @input="schedule()" rows="2"
                                  placeholder="Notiz zur Zuordnung…"
                                  class="bm-textarea"></textarea>
                        <div style="padding-top:4px;font-size:12px">
                            <span x-show="saving" style="color:var(--c-muted)">…</span>
                            <span x-show="saved && !saving" style="color:#059669" x-cloak>✓</span>
                        </div>
                    </div>
                </div>

                {{-- Meta --}}
                <div style="min-width:200px">
                    <div style="font-size:11px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">Details</div>
                    <table class="bm-meta-table">
                        <tr>
                            <td class="bm-meta-label">Rechnungsdatum</td>
                            <td style="font-weight:500;color:var(--c-text)">{{ $voucher->voucher_date?->format('d.m.Y') ?? '–' }}</td>
                        </tr>
                        <tr>
                            <td class="bm-meta-label">Fälligkeitsdatum</td>
                            <td style="font-weight:500;color:{{ $isOverdue ? '#dc2626' : 'var(--c-text)' }}">{{ $voucher->due_date?->format('d.m.Y') ?? '–' }}</td>
                        </tr>
                        <tr>
                            <td class="bm-meta-label">Gesamtbetrag</td>
                            <td style="font-weight:600;color:var(--c-text)">{{ $voucher->formattedTotal() }}</td>
                        </tr>
                        <tr>
                            <td class="bm-meta-label">Offener Betrag</td>
                            <td style="font-weight:{{ $isOverdue ? '700' : '500' }};color:{{ $isOverdue ? '#dc2626' : 'var(--c-text)' }}">{{ $openAmount }}</td>
                        </tr>
                        @if($voucher->lexoffice_voucher_id)
                        <tr>
                            <td class="bm-meta-label">Lexoffice-ID</td>
                            <td style="font-family:monospace;font-size:11px;color:var(--c-muted)">{{ substr($voucher->lexoffice_voucher_id, 0, 18) }}…</td>
                        </tr>
                        @endif
                        @if($confirmed)
                        <tr>
                            <td class="bm-meta-label">Bestätigt am</td>
                            <td style="color:#059669;font-weight:500">{{ $voucher->manually_confirmed_at->format('d.m.Y H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>

            </div>
        </td>
    </tr>

@empty
    <tr>
        <td colspan="9" style="padding:40px;text-align:center;color:var(--c-muted)">
            Keine Einträge für die gewählten Filter.
        </td>
    </tr>
@endforelse
</tbody>
</table>
</div>

@if($vouchers->hasPages())
    <div style="margin-top:16px">{{ $vouchers->links() }}</div>
@endif

@push('scripts')
<script>
function toggleRow(id) {
    const row = document.getElementById('detail-' + id);
    row.style.display = (row.style.display === 'none' || !row.style.display) ? 'table-row' : 'none';
}

function toggleConfirm(id) {
    fetch('/admin/integrations/lexoffice/bank-matching/' + id + '/confirm', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        const row = document.getElementById('voucher-' + id);
        const btn = document.getElementById('confirm-btn-' + id);
        if (data.confirmed) {
            row.classList.add('confirmed');
            btn.classList.add('confirmed-btn');
            btn.textContent = '✓ OK';
        } else {
            row.classList.remove('confirmed');
            btn.classList.remove('confirmed-btn');
            btn.textContent = '○ OK';
        }
    });
}

function customerPicker(voucherId, suggestedId) {
    return {
        selectedId: suggestedId ? String(suggestedId) : '',
        saving: false,
        saved: false,
        save() {
            this.saving = true; this.saved = false;
            fetch('/admin/integrations/lexoffice/bank-matching/' + voucherId + '/link', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_id: this.selectedId || null }),
            }).then(() => { this.saving = false; this.saved = true; });
        },
    };
}

function noteSaver(voucherId, initialNote) {
    return {
        note: initialNote || '',
        saving: false,
        saved: false,
        timer: null,
        schedule() {
            this.saved = false;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.saveNote(), 1000);
        },
        saveNote() {
            this.saving = true;
            fetch('/admin/integrations/lexoffice/bank-matching/' + voucherId + '/note', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ note: this.note }),
            }).then(() => { this.saving = false; this.saved = true; });
        },
    };
}
</script>
@endpush

@endsection
