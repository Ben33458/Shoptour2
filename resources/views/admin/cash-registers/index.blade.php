@extends('admin.layout')

@section('title', 'Kassenverwaltung')

@section('content')

{{-- Kassen-Liste --}}
<div class="card mb-6">
    <div class="card-header">
        <h2>Kassen</h2>
        <form method="POST" action="{{ route('admin.cash-registers.store') }}" class="inline-form">
            @csrf
            <input type="text" name="name" placeholder="Kassenname" required
                   style="padding:6px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            <button type="submit" class="btn-sm btn-primary">+ Neu</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Mitarbeiter</th>
                <th>Umsatz heute</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($registers as $reg)
            @php
                $assignedEmployee = $employees->firstWhere('cash_register_id', $reg->id);
                $todayNet = $reg->transactions
                    ->where('created_at', '>=', today())
                    ->reduce(function($carry, $tx) {
                        return $carry + ($tx->type === 'deposit' ? $tx->amount_cents : -$tx->amount_cents);
                    }, 0);
            @endphp
            <tr>
                <td class="font-medium">{{ $reg->name }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.cash-registers.toggle', $reg) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="status-badge {{ $reg->is_active ? 'status-active' : 'status-inactive' }}">
                            {{ $reg->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.cash-registers.assign-employee', $reg) }}" class="inline-form">
                        @csrf
                        <select name="employee_id" onchange="this.form.submit()"
                                style="font-size:12px;padding:4px 6px;border:1px solid var(--c-border);border-radius:6px;background:var(--c-surface);color:var(--c-text)">
                            <option value="">— keiner —</option>
                            @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @selected($assignedEmployee?->id === $emp->id)>
                                {{ $emp->full_name }}
                            </option>
                            @endforeach
                        </select>
                    </form>
                </td>
                <td style="font-size:13px">
                    <span style="color:{{ $todayNet >= 0 ? 'var(--c-success)' : 'var(--c-danger)' }};font-weight:600">
                        {{ $todayNet >= 0 ? '+' : '' }}{{ number_format($todayNet / 100, 2, ',', '.') }} €
                    </span>
                </td>
                <td>
                    <a href="{{ route('admin.cash-registers.transactions', $reg) }}"
                       style="font-size:12px;color:var(--c-primary)">Buchungen</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="empty">Noch keine Kassen angelegt.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Fahrer-Einstellungen --}}
<div class="card">
    <div class="card-header"><h2>Fahrer-Einstellungen</h2></div>
    <div style="padding:20px">
        <form method="POST" action="{{ route('admin.cash-registers.save-settings') }}">
            @csrf
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <label style="font-size:14px;font-weight:500;color:var(--c-text)">
                    Verzögerungswarnung ab:
                </label>
                <input type="number" name="delay_threshold_percent"
                       value="{{ $delayThreshold }}" min="5" max="300"
                       style="width:80px;padding:6px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:14px;background:var(--c-surface);color:var(--c-text)">
                <span style="font-size:14px;color:var(--c-muted)">% über dem Kundendurchschnitt</span>
                <button type="submit" class="btn-sm btn-primary">Speichern</button>
            </div>
            <p style="margin-top:8px;font-size:12px;color:var(--c-muted)">
                Zeigt dem Fahrer eine Warnung, wenn er bei einem Kunden deutlich länger als im Durchschnitt bleibt.
            </p>
        </form>
    </div>
</div>

<style>
.card { background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px; }
.card-header { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--c-border); }
.card-header h2 { font-size:14px;font-weight:600;color:var(--c-text);margin:0; }
.inline-form { display:flex;gap:8px;align-items:center; }
.data-table { width:100%;border-collapse:collapse;font-size:13px; }
.data-table th { padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted);background:var(--c-bg);border-bottom:1px solid var(--c-border); }
.data-table td { padding:10px 16px;border-bottom:1px solid var(--c-border); }
.data-table tr:last-child td { border-bottom:none; }
.data-table .font-medium { font-weight:500;color:var(--c-text); }
.data-table .empty { text-align:center;color:var(--c-muted);padding:24px; }
.btn-sm { padding:6px 12px;border-radius:6px;border:none;font-size:12px;font-weight:500;cursor:pointer; }
.btn-primary { background:var(--c-primary);color:#fff; }
.btn-primary:hover { background:var(--c-primary-h); }
.status-badge { font-size:12px;padding:2px 8px;border-radius:10px;border:none;cursor:pointer;font-weight:600; }
.status-active  { background:#dcfce7;color:#166534; }
.status-inactive { background:var(--c-bg);color:var(--c-muted); }
[data-theme="dark"] .status-active  { background:#14532d;color:#86efac; }
[data-theme="dark"] .status-inactive { background:var(--c-bg);color:var(--c-muted); }
</style>
@endsection
