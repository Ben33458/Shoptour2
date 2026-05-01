@extends('admin.layout')

@section('title', $communication->subject ?: '(kein Betreff)')

@section('actions')
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($communication->status !== 'archived')
        <form action="{{ route('admin.communications.archive', $communication) }}" method="POST">
            @csrf
            <button class="btn btn-outline">Archivieren</button>
        </form>
        @endif
        @if($communication->status === 'review')
        <form action="{{ route('admin.communications.review', $communication) }}" method="POST">
            @csrf
            <button class="btn btn-primary">Als geprüft markieren</button>
        </form>
        @endif
    </div>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

    {{-- ── Left: Main content ── --}}
    <div>

        {{-- Header info --}}
        <div class="card" style="margin-bottom:16px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:1.1rem;font-weight:600;margin-bottom:6px;">
                        {{ $communication->subject ?: '(kein Betreff)' }}
                    </div>
                    <div style="font-size:.875rem;color:#6b7280;">
                        <strong>Von:</strong> {{ $communication->from_address }}<br>
                        <strong>An:</strong> {{ implode(', ', $communication->to_addresses ?? []) }}<br>
                        @if($communication->cc_addresses)
                        <strong>CC:</strong> {{ implode(', ', $communication->cc_addresses) }}<br>
                        @endif
                        <strong>Empfangen:</strong> {{ $communication->received_at?->format('d.m.Y H:i') ?? '—' }}
                        &nbsp;|&nbsp;
                        <strong>Quelle:</strong> {{ $communication->sourceLabel() }}
                        &nbsp;|&nbsp;
                        <span class="badge {{ $communication->statusBadgeClass() }}">{{ $communication->statusLabel() }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Body tabs --}}
        <div class="card" style="margin-bottom:16px;">
            <div style="border-bottom:1px solid var(--c-border);padding:0 20px;display:flex;gap:0;">
                <button onclick="showTab('text')" id="tab-text"
                    style="padding:12px 16px;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;font-size:.875rem;"
                    class="tab-btn active-tab">Text</button>
                @if($communication->body_html)
                <button onclick="showTab('html')" id="tab-html"
                    style="padding:12px 16px;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;font-size:.875rem;"
                    class="tab-btn">HTML</button>
                @endif
            </div>
            <div id="body-text" style="padding:20px;white-space:pre-wrap;font-size:.875rem;max-height:400px;overflow-y:auto;font-family:monospace;">{{ $communication->body_text ?: '(kein Text-Inhalt)' }}</div>
            @if($communication->body_html)
            <div id="body-html" style="display:none;padding:20px;max-height:400px;overflow-y:auto;">
                <iframe srcdoc="{{ htmlspecialchars($communication->body_html) }}" style="width:100%;height:350px;border:none;"></iframe>
            </div>
            @endif
        </div>

        {{-- Reply form --}}
        <div class="card" style="margin-bottom:16px;">
            <div style="padding:14px 20px;border-bottom:1px solid var(--c-border);font-weight:600;font-size:.9rem;display:flex;align-items:center;gap:8px;">
                ↩ Antwort / Notiz erfassen
            </div>
            <div style="padding:20px;">
                <form action="{{ route('admin.communications.reply', $communication) }}" method="POST">
                    @csrf
                    @if($communication->from_address)
                    <div style="font-size:.8rem;color:#6b7280;margin-bottom:8px;">
                        An: <strong>{{ $communication->from_address }}</strong>
                        &nbsp;·&nbsp; Betreff: <strong>Re: {{ $communication->subject }}</strong>
                    </div>
                    @endif
                    <textarea name="body_text" class="form-control" rows="4"
                              placeholder="Antwort oder interne Notiz …"
                              style="margin-bottom:8px;font-family:inherit;resize:vertical;"
                              required></textarea>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button type="submit" name="type" value="reply" class="btn btn-primary" style="font-size:.875rem;">
                            Als Antwort speichern
                        </button>
                        <button type="submit" name="type" value="note" class="btn btn-outline" style="font-size:.875rem;">
                            Als interne Notiz speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Attachments --}}
        @if($communication->attachments->isNotEmpty())
        <div class="card" style="margin-bottom:16px;padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
                Anhänge ({{ $communication->attachments->count() }})
            </h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($communication->attachments as $att)
                <div style="border:1px solid var(--c-border);border-radius:8px;padding:10px 14px;font-size:.85rem;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:1.2rem;">📎</span>
                    <div>
                        <div style="font-weight:500;">{{ $att->filename }}</div>
                        <div style="color:#9ca3af;font-size:.75rem;">{{ $att->mime_type }} · {{ $att->humanSize() }}</div>
                    </div>
                    <span style="padding:2px 8px;border-radius:10px;font-size:.7rem;
                        background:{{ $att->processing_status === 'processed' ? '#dcfce7' : ($att->processing_status === 'error' ? '#fee2e2' : '#fef3c7') }};
                        color:{{ $att->processing_status === 'processed' ? '#16a34a' : ($att->processing_status === 'error' ? '#dc2626' : '#d97706') }};">
                        {{ $att->processing_status }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Audit log --}}
        <div class="card" style="padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
                Verlauf
            </h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($communication->audits as $audit)
                <div style="display:flex;gap:12px;align-items:flex-start;font-size:.85rem;">
                    <div style="min-width:130px;color:#9ca3af;">
                        {{ $audit->created_at->format('d.m.Y H:i') }}
                    </div>
                    <div>
                        <strong>{{ $audit->eventLabel() }}</strong>
                        @if($audit->user)
                            <span style="color:#6b7280;"> — {{ $audit->user->name }}</span>
                        @else
                            <span style="color:#6b7280;"> — System</span>
                        @endif
                        @if($audit->details_json)
                        <div style="color:#6b7280;font-size:.8rem;margin-top:2px;">
                            @foreach($audit->details_json as $k => $v)
                                @if(!is_array($v))
                                <span><strong>{{ $k }}:</strong> {{ $v }}</span>&nbsp;
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- ── Right: Sidebar ── --}}
    <div>

        {{-- Confidence --}}
        @if($communication->confidence)
        <div class="card" style="margin-bottom:16px;padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 14px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
                Konfidenz
            </h3>
            @php
                $conf = $communication->confidence;
                $dims = [
                    'Kontakt'    => $conf->dim_contact,
                    'Org.'       => $conf->dim_org,
                    'Rolle'      => $conf->dim_role,
                    'Kategorie'  => $conf->dim_category,
                    'Dokument'   => $conf->dim_document,
                    'Aktion'     => $conf->dim_action,
                ];
            @endphp
            @foreach($dims as $label => $score)
            <div style="margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:3px;">
                    <span>{{ $label }}</span>
                    <span>{{ $score }}%</span>
                </div>
                <div style="height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                    <div style="width:{{ $score }}%;height:100%;background:{{ $score >= 80 ? '#22c55e' : ($score >= 50 ? '#f59e0b' : '#9ca3af') }};"></div>
                </div>
            </div>
            @endforeach
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--c-border);display:flex;justify-content:space-between;font-weight:600;font-size:.9rem;">
                <span>Gesamt</span>
                <span style="color:{{ $conf->overall >= 80 ? '#16a34a' : ($conf->overall >= 50 ? '#d97706' : '#dc2626') }};">{{ $conf->overall }}%</span>
            </div>
        </div>
        @endif

        {{-- Assignment --}}
        <div class="card" style="margin-bottom:16px;padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 14px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
                Zuordnung
            </h3>

            @if($communication->communicable)
            <div style="margin-bottom:14px;padding:10px;background:var(--c-surface);border-radius:6px;font-size:.875rem;">
                @if($communication->communicable_type === \App\Models\Pricing\Customer::class)
                    <div style="color:#6b7280;font-size:.75rem;margin-bottom:4px;">Kunde</div>
                    <a href="{{ route('admin.customers.show', $communication->communicable_id) }}" style="font-weight:500;">
                        {{ $communication->communicable->company_name ?: ($communication->communicable->first_name . ' ' . $communication->communicable->last_name) }}
                    </a>
                @else
                    <div style="color:#6b7280;font-size:.75rem;margin-bottom:4px;">Lieferant</div>
                    <a href="{{ route('admin.suppliers.edit', $communication->communicable_id) }}" style="font-weight:500;">
                        {{ $communication->communicable->name }}
                    </a>
                @endif
            </div>
            @endif

            <form action="{{ route('admin.communications.assign', $communication) }}" method="POST"
                  onsubmit="
                    var t=this.querySelector('[name=communicable_type]').value;
                    this.querySelector('#assign-customer').disabled=(t!=='customer');
                    this.querySelector('#assign-supplier').disabled=(t!=='supplier');
                  ">
                @csrf
                <div style="margin-bottom:8px;">
                    <select name="communicable_type" class="form-control" style="margin-bottom:6px;" required
                        onchange="var v=this.value;
                            document.getElementById('assign-customer').style.display=v==='customer'?'block':'none';
                            document.getElementById('assign-supplier').style.display=v==='supplier'?'block':'none';">
                        <option value="">— Typ wählen —</option>
                        <option value="customer" {{ $communication->communicable_type === \App\Models\Pricing\Customer::class ? 'selected':'' }}>Kunde</option>
                        <option value="supplier" {{ $communication->communicable_type === \App\Models\Supplier\Supplier::class ? 'selected':'' }}>Lieferant</option>
                    </select>
                    <select id="assign-customer" name="communicable_id" class="form-control"
                        style="display:{{ $communication->communicable_type === \App\Models\Pricing\Customer::class ? 'block':'none' }};margin-bottom:6px;">
                        <option value="">— Kunde wählen —</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ $communication->communicable_id == $c->id && $communication->communicable_type === \App\Models\Pricing\Customer::class ? 'selected':'' }}>
                            {{ $c->company_name ?: ($c->first_name . ' ' . $c->last_name) }} ({{ $c->customer_number }})
                        </option>
                        @endforeach
                    </select>
                    <select id="assign-supplier" name="communicable_id" class="form-control"
                        style="display:{{ $communication->communicable_type === \App\Models\Supplier\Supplier::class ? 'block':'none' }};margin-bottom:6px;">
                        <option value="">— Lieferant wählen —</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ $communication->communicable_id == $s->id && $communication->communicable_type === \App\Models\Supplier\Supplier::class ? 'selected':'' }}>
                            {{ $s->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" style="width:100%;font-size:.875rem;">Zuordnen</button>
            </form>
        </div>

        {{-- Status --}}
        <div class="card" style="margin-bottom:16px;padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">Status ändern</h3>
            <form action="{{ route('admin.communications.status', $communication) }}" method="POST">
                @csrf
                <select name="status" class="form-control" style="margin-bottom:8px;">
                    <option value="new"      {{ $communication->status === 'new'      ? 'selected':'' }}>Neu</option>
                    <option value="review"   {{ $communication->status === 'review'   ? 'selected':'' }}>Zu prüfen</option>
                    <option value="assigned" {{ $communication->status === 'assigned' ? 'selected':'' }}>Zugeordnet</option>
                    <option value="archived" {{ $communication->status === 'archived' ? 'selected':'' }}>Archiviert</option>
                </select>
                <button class="btn btn-outline" style="width:100%;font-size:.875rem;">Status speichern</button>
            </form>
        </div>

        {{-- Tags --}}
        @if($communication->tags->isNotEmpty())
        <div class="card" style="padding:20px;">
            <h3 style="font-size:.9rem;font-weight:700;margin:0 0 10px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">Tags</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($communication->tags as $tag)
                <span style="background:{{ $tag->color ?? '#e5e7eb' }};color:#1f2937;padding:3px 10px;border-radius:12px;font-size:.8rem;">
                    {{ $tag->name }}
                </span>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
function showTab(tab) {
    document.getElementById('body-text').style.display = tab === 'text' ? 'block' : 'none';
    const html = document.getElementById('body-html');
    if (html) html.style.display = tab === 'html' ? 'block' : 'none';
}
</script>
@endpush
@endsection
