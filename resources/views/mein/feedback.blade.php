@extends('mein.layout')

@section('title', 'Mein Feedback')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <h1 style="font-size:1.3rem; font-weight:700; margin:0;">Mein Feedback</h1>
</div>

{{-- Filter --}}
<div class="mein-card" style="margin-bottom:1rem; padding:.75rem 1rem;">
    <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
        <span style="font-size:.85rem; color:var(--c-muted); margin-right:.25rem;">Anzeigen:</span>
        @foreach(['shift' => 'Aktuelle Schicht', 'open' => 'Offen', 'in_progress' => 'In Bearbeitung', 'done' => 'Erledigt', 'wontfix' => 'Kein Handlungsbedarf'] as $val => $label)
        <a href="{{ route('mein.feedback.index', ['status' => $val]) }}"
           style="padding:.3rem .75rem; border-radius:20px; font-size:.8rem; text-decoration:none; border:1px solid var(--c-border);
                  background:{{ $status === $val ? 'var(--c-primary)' : 'transparent' }};
                  color:{{ $status === $val ? '#fff' : 'var(--c-text)' }};">
            {{ $label }}
        </a>
        @endforeach
        <a href="{{ route('mein.feedback.index') }}"
           style="padding:.3rem .75rem; border-radius:20px; font-size:.8rem; text-decoration:none; border:1px solid var(--c-border);
                  background:{{ !in_array($status, ['shift','open','in_progress','done','wontfix']) ? 'var(--c-primary)' : 'transparent' }};
                  color:{{ !in_array($status, ['shift','open','in_progress','done','wontfix']) ? '#fff' : 'var(--c-text)' }};">
            Alle
        </a>
    </div>
</div>

@if(session('feedback_sent'))
<div class="alert alert-success" style="margin-bottom:1rem;">Feedback wurde gesendet.</div>
@endif

@if($feedbacks->isEmpty())
<div class="mein-card" style="text-align:center; color:var(--c-muted); padding:2rem;">
    Kein Feedback für diesen Filter vorhanden.
</div>
@else
<div class="mein-card" style="padding:0;">
    @foreach($feedbacks as $fb)
    @php
        $statusColor = match($fb->status) {
            'open'        => '#d97706',
            'in_progress' => '#2563eb',
            'done'        => '#16a34a',
            'wontfix'     => '#64748b',
            default       => '#64748b',
        };
    @endphp
    <div class="fb-item" onclick="toggleFb({{ $fb->id }})"
         style="padding:.9rem 1rem; border-bottom:1px solid var(--c-border); cursor:pointer; transition:background .1s;"
         onmouseover="this.style.background='var(--c-bg)'"
         onmouseout="this.style.background=''">
        <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
            <span style="font-size:.8rem; color:var(--c-muted); white-space:nowrap; flex-shrink:0;">
                {{ $fb->created_at->locale('de')->isoFormat('D. MMM, HH:mm') }}
            </span>
            <span style="font-size:.78rem; padding:.15rem .45rem; border-radius:4px; background:var(--c-bg); border:1px solid var(--c-border); flex-shrink:0;">
                {{ $fb->categoryLabel() }}
            </span>
            <span style="font-weight:600; font-size:.9rem; flex:1; min-width:0;">{{ $fb->subject }}</span>
            <span style="font-size:.8rem; font-weight:600; color:{{ $statusColor }}; flex-shrink:0;">
                {{ $fb->statusLabel() }}
            </span>
            <span id="fb-arrow-{{ $fb->id }}" style="color:var(--c-muted); font-size:.8rem; flex-shrink:0; transition:transform .2s;">▼</span>
        </div>
    </div>

    <div id="fb-detail-{{ $fb->id }}" style="display:none; padding:1rem 1rem 1.2rem; border-bottom:1px solid var(--c-border); background:var(--c-bg);">
        @if($fb->body)
        <div style="margin-bottom:.75rem;">
            <div style="font-size:.75rem; font-weight:700; color:var(--c-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem;">Nachricht</div>
            <div style="font-size:.9rem; color:var(--c-text); white-space:pre-wrap; line-height:1.5;">{{ $fb->body }}</div>
        </div>
        @endif
        @if($fb->admin_note)
        <div style="border-top:1px solid var(--c-border); padding-top:.75rem; margin-top:.75rem;">
            <div style="font-size:.75rem; font-weight:700; color:var(--c-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem;">Admin-Antwort</div>
            <div style="font-size:.9rem; color:var(--c-text); white-space:pre-wrap;">{{ $fb->admin_note }}</div>
        </div>
        @else
        <div style="font-size:.85rem; color:var(--c-muted); font-style:italic;">Noch keine Admin-Antwort.</div>
        @endif
    </div>
    @endforeach
</div>
@endif

@push('scripts')
<script>
function toggleFb(id) {
    const detail = document.getElementById('fb-detail-' + id);
    const arrow  = document.getElementById('fb-arrow-' + id);
    const open   = detail.style.display !== 'none';
    detail.style.display = open ? 'none' : 'block';
    arrow.style.transform = open ? '' : 'rotate(180deg)';
}
</script>
@endpush

@endsection
