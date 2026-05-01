@extends('mein.layout')

@section('title', 'Urlaub')

@section('content')

{{-- Übertrag-Verfallswarnung (BUrlG §7) --}}
@if($balance->carried_over > 0)
    @if(now()->month >= 4)
        <div style="background:rgba(234,179,8,.15);border:1px solid #ca8a04;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem;">
            ⚠ Dein Urlaubsübertrag von <strong>{{ $balance->carried_over }} Tagen</strong> aus dem Vorjahr ist am 31.03. verfallen (BUrlG §7 Abs. 3).
        </div>
    @else
        <div style="background:rgba(59,130,246,.1);border:1px solid #3b82f6;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem;">
            ℹ Du hast <strong>{{ $balance->carried_over }} Übertragstag(e)</strong> aus dem Vorjahr — diese verfallen zum 31.03. Bitte plane sie rechtzeitig ein.
        </div>
    @endif
@endif

@if(session('success'))
    <div style="background:rgba(22,163,74,.15);border:1px solid #16a34a;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem;font-weight:600;">
        ✓ {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="background:rgba(220,38,38,.1);border:1px solid #dc2626;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem;">
        {{ session('error') }}
    </div>
@endif

{{-- Urlaubskonto --}}
<div class="mein-card">
    <div class="mein-card-title">Urlaubskonto {{ now()->year }}</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px;">
        <div style="text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--c-text);">{{ $balance->total_days + $balance->carried_over }}</div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px;">Gesamt inkl. Übertrag</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--c-danger,#dc2626);">{{ $balance->used_days }}</div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px;">Verbraucht</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:{{ $balance->remaining_days > 0 ? 'var(--c-success,#16a34a)' : 'var(--c-danger,#dc2626)' }};">
                {{ $balance->remaining_days }}
            </div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px;">Verbleibend</div>
        </div>
        @if($balance->carried_over > 0)
        <div style="text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--c-warning,#d97706);">{{ $balance->carried_over }}</div>
            <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px;">Übertrag Vorjahr</div>
        </div>
        @endif
    </div>
</div>

{{-- Neuen Urlaub beantragen --}}
<div class="mein-card">
    <div class="mein-card-title">Urlaub beantragen</div>

    <form method="POST" action="{{ route('mein.urlaub.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Von</label>
                <input type="date" name="start_date" required
                       value="{{ old('start_date') }}"
                       min="{{ now()->format('Y-m-d') }}"
                       style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;">
            </div>
            <div>
                <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Bis (einschließlich)</label>
                <input type="date" name="end_date" required
                       value="{{ old('end_date') }}"
                       min="{{ now()->format('Y-m-d') }}"
                       style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;">
            </div>
        </div>
        @error('end_date')
            <div style="color:var(--c-danger,#dc2626);font-size:.85rem;margin-bottom:8px;">{{ $message }}</div>
        @enderror
        <div style="margin-bottom:14px;">
            <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Notiz (optional)</label>
            <textarea name="notes" rows="2" maxlength="500"
                      placeholder="z.B. Reise gebucht, Familienfeier…"
                      style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;resize:vertical;">{{ old('notes') }}</textarea>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <button type="submit"
                    style="background:var(--c-primary);color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                Beantragen
            </button>
            <span style="font-size:.8rem;color:var(--c-muted);">Gesetzliche Feiertage und Wochenenden werden nicht als Urlaubstage gezählt.</span>
        </div>
    </form>
</div>

{{-- Meine Anträge --}}
<div class="mein-card">
    <div class="mein-card-title">Meine Anträge</div>
    @if($requests->isEmpty())
        <p style="color:var(--c-muted);font-size:.9rem;">Noch keine Urlaubsanträge vorhanden.</p>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
        <thead>
            <tr style="border-bottom:1px solid var(--c-border);">
                <th style="text-align:left;padding:.5rem .25rem;color:var(--c-muted);font-weight:600;font-size:.75rem;text-transform:uppercase;">Zeitraum</th>
                <th style="text-align:left;padding:.5rem .25rem;color:var(--c-muted);font-weight:600;font-size:.75rem;text-transform:uppercase;">Tage</th>
                <th style="text-align:left;padding:.5rem .25rem;color:var(--c-muted);font-weight:600;font-size:.75rem;text-transform:uppercase;">Status</th>
                <th style="text-align:left;padding:.5rem .25rem;color:var(--c-muted);font-weight:600;font-size:.75rem;text-transform:uppercase;">Notiz</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $req)
            <tr style="border-bottom:1px solid var(--c-border);">
                <td style="padding:.6rem .25rem;white-space:nowrap;">
                    {{ $req->start_date->format('d.m.Y') }}
                    @if($req->start_date != $req->end_date) – {{ $req->end_date->format('d.m.Y') }} @endif
                </td>
                <td style="padding:.6rem .25rem;">{{ $req->days_requested }}</td>
                <td style="padding:.6rem .25rem;">
                    @php
                        $badge = match($req->status) {
                            'pending'   => ['warning', 'Ausstehend'],
                            'approved'  => ['success', 'Genehmigt'],
                            'rejected'  => ['danger',  'Abgelehnt'],
                            'cancelled' => ['secondary','Zurückgezogen'],
                            default     => ['secondary', $req->status],
                        };
                    @endphp
                    <span class="badge badge-{{ $badge[0] }}">{{ $badge[1] }}</span>
                    @if($req->decision_notes)
                        <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px;">{{ $req->decision_notes }}</div>
                    @endif
                </td>
                <td style="padding:.6rem .25rem;color:var(--c-muted);">{{ $req->notes ?? '—' }}</td>
                <td style="padding:.6rem .25rem;text-align:right;">
                    @if($req->status === 'pending')
                        <form method="POST" action="{{ route('mein.urlaub.cancel', $req) }}">
                            @csrf
                            <button type="submit"
                                    style="background:none;border:1px solid var(--c-border);border-radius:6px;padding:.2rem .6rem;font-size:.75rem;color:var(--c-muted);cursor:pointer;">
                                Zurückziehen
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

@endsection
