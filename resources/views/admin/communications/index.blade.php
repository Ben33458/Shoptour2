@extends('admin.layout')

@section('title', 'Kommunikation — Posteingang')

@section('actions')
    <details class="actions-dropdown">
        <summary class="btn btn-primary">+ Manuelle Aktivität</summary>
        <div class="actions-menu" style="min-width:340px;padding:16px;">
            <form action="{{ route('admin.communications.manual.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:10px;">
                    <input name="subject" class="form-control" placeholder="Betreff *" required style="margin-bottom:6px;">
                    <textarea name="body_text" class="form-control" rows="3" placeholder="Notiz (optional)" style="margin-bottom:6px;"></textarea>
                    <select name="communicable_type" class="form-control" style="margin-bottom:6px;" onchange="this.nextElementSibling.style.display=this.value?'block':'none'">
                        <option value="">— Keine Zuordnung —</option>
                        <option value="customer">Kunde</option>
                        <option value="supplier">Lieferant</option>
                    </select>
                    <input name="communicable_id" class="form-control" placeholder="ID des Kunden/Lieferanten" style="display:none;">
                </div>
                <button class="btn btn-primary" style="width:100%">Erstellen</button>
            </form>
        </div>
    </details>
@endsection

@section('content')
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
    {{-- Filter --}}
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle Status</option>
            <option value="new"      {{ request('status')=='new'      ? 'selected':'' }}>Neu</option>
            <option value="review"   {{ request('status')=='review'   ? 'selected':'' }}>Zu prüfen</option>
            <option value="assigned" {{ request('status')=='assigned' ? 'selected':'' }}>Zugeordnet</option>
            <option value="archived" {{ request('status')=='archived' ? 'selected':'' }}>Archiviert</option>
        </select>
        <select name="source" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle Quellen</option>
            <option value="gmail"  {{ request('source')=='gmail'  ? 'selected':'' }}>Gmail</option>
            <option value="manual" {{ request('source')=='manual' ? 'selected':'' }}>Manuell</option>
            <option value="phone"  {{ request('source')=='phone'  ? 'selected':'' }}>Telefon</option>
        </select>
        @if($tags->isNotEmpty())
        <select name="tag" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle Tags</option>
            @foreach($tags as $tag)
            <option value="{{ $tag->id }}" {{ request('tag') == $tag->id ? 'selected':'' }}>
                {{ $tag->name }}
            </option>
            @endforeach
        </select>
        @endif
        @if(request()->hasAny(['status','source','tag','unassigned']))
            <a href="{{ route('admin.communications.index') }}" class="btn btn-outline">× Filter</a>
        @endif
    </form>

    @if($reviewCount > 0)
        <a href="{{ route('admin.communications.index', ['status'=>'review']) }}"
           style="background:#f59e0b;color:#fff;padding:6px 14px;border-radius:6px;font-size:.875rem;text-decoration:none;">
            ⚠ {{ $reviewCount }} zu prüfen
        </a>
    @endif
</div>

<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Von</th>
                <th>Betreff</th>
                <th>Quelle</th>
                <th>Status</th>
                <th>Zuordnung</th>
                <th>Konfidenz</th>
                <th>Empfangen</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($communications as $c)
            <tr>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ $c->from_address }}
                </td>
                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <a href="{{ route('admin.communications.show', $c) }}">
                        {{ $c->subject ?: '(kein Betreff)' }}
                    </a>
                    @if($c->attachments_count ?? $c->attachments->count())
                        <span title="Anhänge" style="color:#94a3b8;font-size:.75rem;">📎</span>
                    @endif
                    @if($c->tags->isNotEmpty())
                    <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
                        @foreach($c->tags as $tag)
                        <a href="{{ route('admin.communications.index', array_merge(request()->query(), ['tag'=>$tag->id])) }}"
                           style="background:{{ $tag->color ?? '#e5e7eb' }};color:#1f2937;padding:1px 7px;border-radius:10px;font-size:.7rem;text-decoration:none;white-space:nowrap;">
                            {{ $tag->name }}
                        </a>
                        @endforeach
                    </div>
                    @endif
                </td>
                <td><span style="font-size:.8rem;color:#6b7280;">{{ $c->sourceLabel() }}</span></td>
                <td>
                    <span class="badge {{ $c->statusBadgeClass() }}">{{ $c->statusLabel() }}</span>
                </td>
                <td style="font-size:.8rem;color:#6b7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    @if($c->communicable)
                        @if($c->communicable_type === \App\Models\Pricing\Customer::class)
                            <a href="{{ route('admin.customers.show', $c->communicable_id) }}">
                                {{ $c->communicable->company_name ?: ($c->communicable->first_name . ' ' . $c->communicable->last_name) }}
                            </a>
                        @else
                            <a href="{{ route('admin.suppliers.edit', $c->communicable_id) }}">
                                {{ $c->communicable->name }}
                            </a>
                        @endif
                    @else
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>
                <td>
                    @if($c->overall_confidence !== null)
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                                <div style="width:{{ $c->overall_confidence }}%;height:100%;background:{{ $c->overall_confidence >= 80 ? '#22c55e' : ($c->overall_confidence >= 50 ? '#f59e0b' : '#ef4444') }};"></div>
                            </div>
                            <span style="font-size:.75rem;color:#6b7280;">{{ $c->overall_confidence }}%</span>
                        </div>
                    @else
                        <span style="color:#d1d5db;font-size:.8rem;">—</span>
                    @endif
                </td>
                <td style="font-size:.8rem;color:#6b7280;white-space:nowrap;">
                    {{ $c->received_at?->format('d.m.Y H:i') ?? '—' }}
                </td>
                <td>
                    <a href="{{ route('admin.communications.show', $c) }}" class="btn btn-outline" style="padding:4px 10px;font-size:.8rem;">Details</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;color:#9ca3af;padding:40px;">Keine Kommunikationen gefunden.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">
    {{ $communications->links() }}
</div>
@endsection
