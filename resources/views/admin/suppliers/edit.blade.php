@extends('admin.layout')

@section('title', 'Lieferant bearbeiten — ' . $supplier->name)

@section('actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline btn-sm">← Lieferantenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Lieferantendaten bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            @include('admin.suppliers._form', ['supplier' => $supplier])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

{{-- ── Kommunikationsverlauf ── --}}
@if($supplier->communications->isNotEmpty())
<div class="card" style="margin-top:24px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Kommunikationsverlauf</span>
        <span style="font-size:.8rem;color:var(--c-muted)">{{ $supplier->communications->count() }} Einträge</span>
    </div>
    <div style="padding:0">
        @foreach($supplier->communications as $comm)
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

{{-- ── Gefahrenzone ── --}}
<div class="card" style="margin-top:24px;border-color:var(--c-danger)">
    <div class="card-header" style="color:var(--c-danger)">Gefahrenzone</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:24px">

        {{-- Merge --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Duplikat zusammenführen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Geben Sie die ID des Duplikats ein (steht in der URL der Bearbeitungsseite:
                <code>/admin/suppliers/ID/edit</code>). Alle Bestellungen und Belege werden
                übertragen, das Duplikat wird gelöscht.
            </div>
            @if($errors->has('source_supplier_id'))
                <div style="color:var(--c-danger);font-size:13px;margin-bottom:8px">{{ $errors->first('source_supplier_id') }}</div>
            @endif
            <form method="GET" action="{{ route('admin.suppliers.merge-preview', $supplier) }}">
                <div style="display:flex;gap:8px">
                    <input type="number" name="source_supplier_id"
                           placeholder="ID des Duplikats"
                           style="flex:1;padding:6px 10px;border:1px solid var(--c-border);border-radius:4px"
                           min="1" required>
                    <button type="submit" class="btn btn-sm"
                            style="background:var(--c-warning,#d97706);color:#fff;border-color:var(--c-warning,#d97706)">
                        Vorschau &amp; Abgleich
                    </button>
                </div>
            </form>
        </div>

        {{-- Delete --}}
        <div>
            <div style="font-weight:600;margin-bottom:6px">Lieferant löschen</div>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
                Nur möglich, wenn keine Einkaufsbestellungen vorhanden sind.
            </div>
            <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
                  onsubmit="return confirm('Lieferant wirklich dauerhaft löschen?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm"
                        style="background:var(--c-danger);color:#fff;border-color:var(--c-danger)">
                    Lieferant löschen
                </button>
            </form>
        </div>

    </div>
</div>

@endsection
