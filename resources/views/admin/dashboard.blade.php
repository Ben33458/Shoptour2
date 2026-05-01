@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1>Dashboard</h1>
</div>

{{-- ── Quick Stats ──────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">

    <a href="{{ route('admin.orders.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
            <div style="font-size:2rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ $stats['orders_today'] }}</div>
            <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Bestellungen heute</div>
        </div>
    </a>

    <a href="{{ route('admin.shifts.reports.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
            <div style="font-size:2rem;font-weight:700;color:{{ $stats['open_shift_reports'] > 0 ? 'var(--c-warning,#d97706)' : 'var(--c-muted,#64748b)' }};">{{ $stats['open_shift_reports'] }}</div>
            <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Offene Schichtberichte</div>
        </div>
    </a>

    <a href="{{ route('admin.vacation.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
            <div style="font-size:2rem;font-weight:700;color:{{ $stats['pending_vacation'] > 0 ? 'var(--c-warning,#d97706)' : 'var(--c-muted,#64748b)' }};">{{ $stats['pending_vacation'] }}</div>
            <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Offene Urlaubsanträge</div>
        </div>
    </a>

    <a href="{{ route('admin.communications.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
            <div style="font-size:2rem;font-weight:700;color:{{ $stats['communications_review'] > 0 ? 'var(--c-warning,#d97706)' : 'var(--c-muted,#64748b)' }};">{{ $stats['communications_review'] }}</div>
            <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Posteingang: Review</div>
        </div>
    </a>

    <div style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
            <div style="font-size:2rem;font-weight:700;color:{{ $stats['open_feedback'] > 0 ? 'var(--c-warning,#d97706)' : 'var(--c-muted,#64748b)' }};">{{ $stats['open_feedback'] }}</div>
            <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Offenes Feedback</div>
        </div>
    </div>

</div>

{{-- ── Gekühlte Kästen — Verkaufsentwicklung ───────────────────────────── --}}
<div class="card" style="margin-bottom:2rem">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>🌡️ Gekühlte Kästen — Verkaufsentwicklung (letzte 12 KW)</span>
        <a href="{{ route('admin.statistics.artikel', '52288') }}" class="btn btn-outline btn-sm">Vollansicht →</a>
    </div>
    <div style="padding:1rem">

        {{-- KPI-Kacheln --}}
        <div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
            <div style="flex:1;min-width:100px;text-align:center;background:var(--c-bg,#f8fafc);border-radius:6px;padding:.75rem">
                <div style="font-size:1.6rem;font-weight:700">{{ number_format($gekuehlteDieseKw, 0, ',', '.') }}</div>
                <div style="font-size:.78rem;color:var(--c-muted)">Menge diese KW</div>
            </div>
            <div style="flex:1;min-width:100px;text-align:center;background:var(--c-bg,#f8fafc);border-radius:6px;padding:.75rem">
                <div style="font-size:1.6rem;font-weight:700">{{ number_format($gekuehlteGesamt, 0, ',', '.') }}</div>
                <div style="font-size:.78rem;color:var(--c-muted)">Gesamt (12 KW)</div>
            </div>
            <div style="flex:1;min-width:100px;text-align:center;background:var(--c-bg,#f8fafc);border-radius:6px;padding:.75rem">
                <div style="font-size:1.6rem;font-weight:700">{{ number_format($gekuehlteUmsatz, 2, ',', '.') }} €</div>
                <div style="font-size:.78rem;color:var(--c-muted)">Umsatz (12 KW)</div>
            </div>
        </div>

        {{-- Sparkline-Balken --}}
        <div style="display:flex;align-items:flex-end;gap:3px;height:64px">
            @foreach($gekuehlteTrend as $row)
                @php $pct = $gekuehlteMax > 0 ? ($row->menge / $gekuehlteMax * 100) : 0; @endphp
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%"
                     title="{{ $row->kwLabel }}: {{ number_format($row->menge, 0, ',', '.') }} Stk">
                    <div style="flex:1;width:100%;display:flex;align-items:flex-end">
                        <div style="width:100%;
                                    height:{{ $row->menge > 0 ? max($pct, 4) : 0 }}%;
                                    background:{{ $row->menge > 0 ? '#6366f1' : 'var(--c-border,#e2e8f0)' }};
                                    border-radius:2px 2px 0 0">
                        </div>
                    </div>
                    <div style="font-size:9px;color:var(--c-muted);margin-top:3px;white-space:nowrap">
                        KW{{ $row->week }}
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>

{{-- ── Mitarbeiter-Feedback ─────────────────────────────────────────────── --}}
@if($feedbackItems->isNotEmpty())
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <strong>Mitarbeiter-Feedback ({{ $feedbackItems->count() }} offen / in Bearbeitung)</strong>
    </div>
    <div class="card-body" style="padding:0;">
        @if(session('success'))
            <div class="alert alert-success" style="margin:.75rem;">{{ session('success') }}</div>
        @endif
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Mitarbeiter</th>
                    <th>Kategorie</th>
                    <th>Betreff</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($feedbackItems as $fb)
                @php
                    $catBadge = match($fb->category) {
                        'bug'         => 'danger',
                        'improvement' => 'primary',
                        default       => 'secondary',
                    };
                    $statusBadge = match($fb->status) {
                        'open'        => 'warning',
                        'in_progress' => 'primary',
                        'done'        => 'success',
                        default       => 'secondary',
                    };
                @endphp
                <tr>
                    <td style="white-space:nowrap;">{{ $fb->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $fb->employee->full_name }}</td>
                    <td><span class="badge badge-{{ $catBadge }}">{{ $fb->categoryLabel() }}</span></td>
                    <td>
                        <strong>{{ $fb->subject }}</strong>
                        @if($fb->body)
                            <details style="margin-top:.2rem;">
                                <summary style="cursor:pointer;font-size:.8rem;color:var(--c-muted);">Details</summary>
                                <div style="font-size:.85rem;padding:.4rem 0;white-space:pre-wrap;color:var(--c-text);">{{ $fb->body }}</div>
                                @if($fb->admin_note)
                                    <div style="font-size:.8rem;color:var(--c-muted);border-top:1px solid var(--c-border);padding-top:.3rem;margin-top:.3rem;">
                                        <strong>Notiz:</strong> {{ $fb->admin_note }}
                                    </div>
                                @endif
                            </details>
                        @endif
                    </td>
                    <td><span class="badge badge-{{ $statusBadge }}">{{ $fb->statusLabel() }}</span></td>
                    <td>
                        <details style="display:inline-block;">
                            <summary class="btn btn-sm btn-secondary" style="cursor:pointer;list-style:none;">Status ändern</summary>
                            <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:280px;">
                                <form method="POST" action="{{ route('admin.feedback.update', $fb) }}">
                                    @csrf @method('PATCH')
                                    <div style="margin-bottom:.5rem;">
                                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.2rem;">Status</label>
                                        <select name="status"
                                                style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                                            <option value="open"        {{ $fb->status === 'open'        ? 'selected' : '' }}>Offen</option>
                                            <option value="in_progress" {{ $fb->status === 'in_progress' ? 'selected' : '' }}>In Bearbeitung</option>
                                            <option value="done"        {{ $fb->status === 'done'        ? 'selected' : '' }}>Erledigt</option>
                                            <option value="wontfix"     {{ $fb->status === 'wontfix'     ? 'selected' : '' }}>Kein Handlungsbedarf</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom:.5rem;">
                                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.2rem;">Notiz (optional)</label>
                                        <textarea name="admin_note" rows="2" maxlength="1000"
                                                  placeholder="Interne Notiz…"
                                                  style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;resize:vertical;">{{ $fb->admin_note }}</textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Sync-Status ──────────────────────────────────────────────────────── --}}
<h2 style="font-size:1.1rem;font-weight:600;margin-bottom:.75rem;">Synchronisierungsstatus</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:2rem;">

    {{-- WAWI --}}
    <div style="background:var(--c-surface,#fff);border:2px solid {{ $wawiOverdue ? 'var(--c-danger,#dc2626)' : 'var(--c-border,#e2e8f0)' }};border-radius:8px;padding:1.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
            @if($wawiOverdue)
                <span style="color:var(--c-danger,#dc2626);font-size:1.2rem;">⚠</span>
            @else
                <span style="color:var(--c-success,#16a34a);font-size:1.2rem;">✓</span>
            @endif
            <strong>JTL WAWI</strong>
            @if($wawiOverdue)
                <span style="background:var(--c-danger,#dc2626);color:#fff;border-radius:10px;padding:1px 8px;font-size:.75rem;margin-left:auto;">ÜBERFÄLLIG</span>
            @else
                <span style="background:var(--c-success,#16a34a);color:#fff;border-radius:10px;padding:1px 8px;font-size:.75rem;margin-left:auto;">OK</span>
            @endif
        </div>
        @if($wawiLast)
            <div style="font-size:.875rem;color:var(--c-muted,#64748b);">
                Letzter Sync: <strong style="color:inherit;">{{ $wawiLast->started_at->format('d.m.Y H:i') }} Uhr</strong><br>
                <span style="font-size:.8rem;">{{ $wawiLast->started_at->diffForHumans() }} · Entity: {{ $wawiLast->entity ?? '—' }} · {{ number_format($wawiLast->records_processed) }} Datensätze</span>
            </div>
        @else
            <div style="font-size:.875rem;color:var(--c-danger,#dc2626);">Noch kein Sync protokolliert.</div>
        @endif
    </div>

    {{-- Ninox --}}
    <div style="background:var(--c-surface,#fff);border:2px solid {{ $ninoxOverdue ? 'var(--c-danger,#dc2626)' : 'var(--c-border,#e2e8f0)' }};border-radius:8px;padding:1.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
            @if($ninoxOverdue)
                <span style="color:var(--c-danger,#dc2626);font-size:1.2rem;">⚠</span>
            @else
                <span style="color:var(--c-success,#16a34a);font-size:1.2rem;">✓</span>
            @endif
            <strong>Ninox</strong>
            @if($ninoxOverdue)
                <span style="background:var(--c-danger,#dc2626);color:#fff;border-radius:10px;padding:1px 8px;font-size:.75rem;margin-left:auto;">ÜBERFÄLLIG</span>
            @else
                <span style="background:var(--c-success,#16a34a);color:#fff;border-radius:10px;padding:1px 8px;font-size:.75rem;margin-left:auto;">OK</span>
            @endif
        </div>
        @if($ninoxLast)
            <div style="font-size:.875rem;color:var(--c-muted,#64748b);">
                Letzter Import: <strong style="color:inherit;">{{ $ninoxLast->started_at->format('d.m.Y H:i') }} Uhr</strong><br>
                <span style="font-size:.8rem;">{{ $ninoxLast->started_at->diffForHumans() }} · {{ number_format($ninoxLast->records_processed) }} Datensätze · {{ $ninoxLast->durationLabel() }}</span>
            </div>
        @else
            <div style="font-size:.875rem;color:var(--c-danger,#dc2626);">Noch kein Import protokolliert.</div>
        @endif
        <div style="margin-top:.75rem;">
            <a href="{{ route('admin.ninox-import.index') }}" style="font-size:.8rem;color:var(--c-primary,#2563eb);">→ Ninox-Import starten</a>
        </div>
    </div>

</div>

{{-- ── Warnungen ────────────────────────────────────────────────────────── --}}
@php
    $hasAlerts = $notClockedIn->isNotEmpty() || $overtime->isNotEmpty() || $missingItems->isNotEmpty();
@endphp

@if($hasAlerts)
<h2 style="font-size:1.1rem;font-weight:600;margin-bottom:.75rem;color:var(--c-danger,#dc2626);">⚠ Handlungsbedarf</h2>
@endif

{{-- Nicht eingestempelt --}}
@if($notClockedIn->isNotEmpty())
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--c-danger,#dc2626);">
    <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
        <span style="color:var(--c-danger,#dc2626);">⚠</span>
        <strong>Nicht eingestempelt ({{ $notClockedIn->count() }})</strong>
        <span style="font-size:.8rem;color:var(--c-muted,#64748b);margin-left:.5rem;">Schicht hätte vor &gt;30 Min beginnen sollen</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Geplanter Start</th>
                    <th>Verspätung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($notClockedIn as $shift)
                @php
                    $lateMin = (int) $shift->planned_start->diffInMinutes(now());
                    $lh = intdiv($lateMin, 60); $lm = str_pad($lateMin % 60, 2, '0', STR_PAD_LEFT);
                @endphp
                <tr>
                    <td>{{ $shift->employee?->full_name ?? '—' }}</td>
                    <td>{{ $shift->planned_start->format('H:i') }} Uhr</td>
                    <td style="color:var(--c-danger,#dc2626);font-weight:600;">+{{ $lh }}:{{ $lm }} h</td>
                    <td>
                        <a href="{{ route('admin.shifts.index') }}" style="font-size:.85rem;">→ Schichtplan</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Überstunden --}}
@if($overtime->isNotEmpty())
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--c-warning,#d97706);">
    <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
        <span style="color:var(--c-warning,#d97706);">⚠</span>
        <strong>Überstunden ({{ $overtime->count() }})</strong>
        <span style="font-size:.8rem;color:var(--c-muted,#64748b);margin-left:.5rem;">Geplantes Ende &gt;30 Min überschritten</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Geplantes Ende</th>
                    <th>Überschreitung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($overtime as $shift)
                @php
                    $overMin = (int) $shift->planned_end->diffInMinutes(now());
                    $oh = intdiv($overMin, 60); $om = str_pad($overMin % 60, 2, '0', STR_PAD_LEFT);
                    $openEntry = $shift->timeEntries->first();
                @endphp
                <tr>
                    <td>{{ $shift->employee?->full_name ?? '—' }}</td>
                    <td>{{ $shift->planned_end->format('H:i') }} Uhr</td>
                    <td style="color:var(--c-warning,#d97706);font-weight:600;">+{{ $oh }}:{{ $om }} h</td>
                    <td>
                        @if($openEntry)
                        <details style="display:inline-block;">
                            <summary class="btn btn-sm btn-secondary" style="cursor:pointer;list-style:none;font-size:.8rem;">Korrigieren</summary>
                            <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:280px;">
                                <form method="POST" action="{{ route('admin.time.correct', $openEntry) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="clocked_in_at" value="{{ $openEntry->clocked_in_at->format('Y-m-d\TH:i') }}">
                                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Ausstempeln auf:</label>
                                    <div style="display:flex;gap:.4rem;align-items:center;">
                                        <input type="datetime-local" name="clocked_out_at"
                                               value="{{ $shift->planned_end->format('Y-m-d\TH:i') }}"
                                               style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                                        <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                    </div>
                                </form>
                            </div>
                        </details>
                        @else
                            <a href="{{ route('admin.time.index') }}" style="font-size:.85rem;">→ Zeiterfassung</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Fehlende Lieferartikel --}}
@if($missingItems->isNotEmpty())
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--c-warning,#d97706);">
    <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
        <span style="color:var(--c-warning,#d97706);">⚠</span>
        <strong>Unvollständige Lieferungen ({{ $missingItems->count() }} Positionen)</strong>
        <span style="font-size:.8rem;color:var(--c-muted,#64748b);margin-left:.5rem;">Bestätigte/versendete Bestellungen mit offenen Artikeln</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Bestellung</th>
                    <th>Artikel</th>
                    <th>Bestellt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($missingItems as $item)
                <tr>
                    <td>
                        <a href="{{ route('admin.orders.show', $item->order) }}">#{{ $item->order->order_number ?? $item->order->id }}</a>
                    </td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->qty }} Stk.</td>
                    <td>
                        <a href="{{ route('admin.orders.show', $item->order) }}" style="font-size:.85rem;">→ Bestellung</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(!$hasAlerts)
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;margin-bottom:2rem;color:var(--c-muted,#64748b);text-align:center;">
    ✓ Keine offenen Warnungen
</div>
@endif

{{-- ── Letzte Bestellungen ──────────────────────────────────────────────── --}}
@php
    $recentOrders = \App\Models\Orders\Order::with('customer')
        ->latest()
        ->limit(5)
        ->get();
@endphp

@if($recentOrders->isNotEmpty())
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <strong>Letzte Bestellungen</strong>
        <a href="{{ route('admin.orders.index') }}" style="font-size:.85rem;">Alle anzeigen →</a>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kunde</th>
                    <th>Status</th>
                    <th>Eingegangen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrders as $order)
                <tr>
                    <td>{{ $order->order_number ?? $order->id }}</td>
                    <td>{{ $order->customer?->display_name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ match($order->status) {
                            'pending'   => 'warning',
                            'confirmed' => 'info',
                            'shipped'   => 'primary',
                            'delivered' => 'success',
                            'cancelled' => 'secondary',
                            default     => 'secondary',
                        } }}">{{ ucfirst($order->status) }}</span>
                    </td>
                    <td>{{ $order->created_at->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('admin.orders.show', $order) }}" style="font-size:.85rem;">→</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
