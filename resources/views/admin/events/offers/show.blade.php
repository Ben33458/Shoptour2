@extends('admin.layout')

@section('title', 'Angebot ' . ($version->offer_number ?? 'V' . $version->version_number))

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <a href="{{ route('admin.events.show', $occurrence) . '?tab=offers' }}" style="color:var(--c-muted);font-size:.85rem">&larr; Angebote</a>
        <h1 style="margin:4px 0;font-size:1.3rem">
            {{ $version->offer_number ?? 'V' . $version->version_number }}
            &nbsp;
            @include('admin.events._partials.offer-status-badge', ['status' => $version->status])
        </h1>
        <span style="font-size:.85rem;color:var(--c-muted)">{{ $occurrence->title }}</span>
    </div>
    <div style="display:flex;gap:8px">
        @if(!in_array($version->status, ['converted_to_order', 'rejected', 'cancelled']))
            <form method="POST" action="{{ route('admin.events.offers.accept', [$occurrence, $version]) }}" onsubmit="return confirm('Angebot annehmen und Bestellung erstellen?')">
                @csrf
                <button type="submit" class="btn btn-primary">Angebot annehmen</button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">
    <div class="card">
        <div class="card-header">Angebotsdaten</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.85rem">
            <div><strong>Angebotsnr.</strong><br>{{ $version->offer_number ?? '—' }}</div>
            <div><strong>Externe Nr.</strong><br>{{ $version->external_offer_number ?? '—' }}</div>
            <div><strong>Version</strong><br>V{{ $version->version_number }}</div>
            <div><strong>Quelle</strong><br>{{ $version->source_system }}</div>
            <div><strong>Gültig bis</strong><br>{{ $version->valid_until?->format('d.m.Y') ?? '—' }}</div>
            <div><strong>Erstellt</strong><br>{{ $version->created_at->format('d.m.Y H:i') }}</div>
            <div><strong>Versendet</strong><br>{{ $version->sent_at?->format('d.m.Y') ?? '—' }}</div>
            <div><strong>Angenommen</strong><br>{{ $version->accepted_at?->format('d.m.Y') ?? '—' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Beträge</div>
        <div style="padding:16px;display:grid;gap:8px;font-size:.85rem">
            <div style="display:flex;justify-content:space-between">
                <span>Netto gesamt</span>
                <strong>{{ $version->net_total_milli !== null ? number_format($version->net_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span>Brutto gesamt</span>
                <strong>{{ $version->gross_total_milli !== null ? number_format($version->gross_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span>Pfand</span>
                <strong>{{ $version->deposit_total_milli !== null ? number_format($version->deposit_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span>Verleih</span>
                <strong>{{ $version->rental_total_milli !== null ? number_format($version->rental_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span>Lieferung</span>
                <strong>{{ $version->delivery_total_milli !== null ? number_format($version->delivery_total_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- PDF Upload ── --}}
<div class="card" style="max-width:900px;margin-top:16px">
    <div class="card-header">PDF</div>
    <div style="padding:16px">
        @if($version->pdf_file_path)
            <p>Aktuelles PDF: <strong>{{ basename($version->pdf_file_path) }}</strong></p>
        @endif
        <form method="POST" action="{{ route('admin.events.offers.pdf.upload', [$occurrence, $version]) }}" enctype="multipart/form-data">
            @csrf
            <div style="display:flex;gap:8px;align-items:flex-end">
                <div class="form-group" style="margin:0">
                    <label>PDF hochladen</label>
                    <input type="file" name="pdf" accept=".pdf">
                </div>
                <button type="submit" class="btn btn-outline">Hochladen</button>
            </div>
        </form>
    </div>
</div>

{{-- Positionen ── --}}
<div class="card" style="max-width:900px;margin-top:16px">
    <div class="card-header">Positionen ({{ $version->items->count() }})</div>
    @if($version->items->isNotEmpty())
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bezeichnung</th>
                        <th>Art</th>
                        <th class="text-right">Menge</th>
                        <th>Einheit</th>
                        <th class="text-right">EP netto</th>
                        <th class="text-right">Gesamt netto</th>
                        <th>Opt.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($version->items as $item)
                        <tr>
                            <td>{{ $item->sort_order }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ \App\Constants\EventLabels::label(\App\Constants\EventLabels::itemTypes(), $item->item_type) }}</td>
                            <td class="text-right">{{ number_format((float)$item->quantity, 3, ',', '.') }}</td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-right">{{ $item->unit_price_net_milli !== null ? number_format($item->unit_price_net_milli / 1000000, 4, ',', '.') . ' €' : '—' }}</td>
                            <td class="text-right">{{ $item->line_total_net_milli !== null ? number_format($item->line_total_net_milli / 1000000, 2, ',', '.') . ' €' : '—' }}</td>
                            <td>{{ $item->is_optional ? 'Opt.' : '' }}{{ $item->is_free_text ? 'FT' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="padding:16px;color:var(--c-muted)">Keine Positionen erfasst.</div>
    @endif
</div>

{{-- Konvertierungsfehler ── --}}
@if($version->conversion_status === 'needs_review' && $version->conversion_error)
    <div class="card" style="max-width:900px;margin-top:16px;border:1px solid #fbbf24">
        <div class="card-header" style="background:#fef3c7">Konvertierungsfehler (Review nötig)</div>
        <ul style="padding:16px 16px 16px 32px;margin:0">
            @foreach($version->conversion_error as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@endsection
