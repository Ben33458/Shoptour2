@extends('admin.layout')

@section('title', 'Bestellung #' . $order->id . ' bearbeiten')

@section('actions')
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')

@if(session('success'))<div class="alert alert-success" style="margin-bottom:12px">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" style="margin-bottom:12px">{{ $errors->first() }}</div>@endif

{{-- ── Order meta ── --}}
<div class="meta-grid" style="margin-bottom:16px">
    <div class="meta-item">
        <label>Bestell-ID</label>
        <div class="val">#{{ $order->id }}</div>
    </div>
    <div class="meta-item">
        <label>Status</label>
        <div class="val"><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></div>
    </div>
    <div class="meta-item">
        <label>Kunde</label>
        <div class="val">
            {{ $order->customer?->first_name }} {{ $order->customer?->last_name }}
            <span class="text-muted" style="font-size:12px"> · {{ $order->customer?->customer_number }}</span>
        </div>
    </div>
</div>

{{-- ── Artikel bearbeiten ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="font-weight:600">Positionen bearbeiten</div>
    <form method="POST" action="{{ route('admin.orders.items.update', $order) }}">
        @csrf
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:32px"><input type="checkbox" id="check-all" title="Alle markieren"></th>
                        <th>Artikel-Nr.</th>
                        <th>Bezeichnung</th>
                        <th style="text-align:right">EP (brutto)</th>
                        <th style="text-align:center;width:110px">Menge</th>
                        <th style="text-align:right">Gesamt</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td>
                            <input type="checkbox" name="remove[]" value="{{ $item->id }}" class="remove-check">
                        </td>
                        <td><code>{{ $item->artikelnummer_snapshot }}</code></td>
                        <td>{{ $item->product_name_snapshot }}</td>
                        <td style="text-align:right">
                            {{ number_format($item->unit_price_gross_milli / 1_000_000, 2, ',', '.') }} €
                        </td>
                        <td style="text-align:center">
                            <input type="number"
                                   name="qty[{{ $item->id }}]"
                                   value="{{ $item->qty }}"
                                   min="0" max="9999"
                                   style="width:70px;text-align:center;padding:3px 6px">
                        </td>
                        <td style="text-align:right">
                            {{ number_format(($item->unit_price_gross_milli * $item->qty) / 1_000_000, 2, ',', '.') }} €
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:var(--c-muted);text-align:center">Keine Positionen.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;border-top:2px solid var(--c-border)">
                        <td colspan="5" style="text-align:right;padding-right:8px">Gesamt brutto:</td>
                        <td style="text-align:right">
                            {{ number_format($order->total_gross_milli / 1_000_000, 2, ',', '.') }} €
                            @if($order->total_pfand_brutto_milli > 0)
                                <br><span class="text-muted" style="font-weight:normal;font-size:12px">
                                    + {{ number_format($order->total_pfand_brutto_milli / 1_000_000, 2, ',', '.') }} € Pfand
                                </span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="display:flex;gap:.5rem;align-items:center;justify-content:space-between">
            <p class="text-muted" style="margin:0;font-size:12px">
                Menge = 0 oder Checkbox ✓ → Position wird entfernt. Änderungen werden sofort gespeichert.
            </p>
            <button type="submit" class="btn btn-primary">Änderungen speichern</button>
        </div>
    </form>
</div>

{{-- ── Artikel hinzufügen ── --}}
<div class="card">
    <div class="card-header" style="font-weight:600">Artikel hinzufügen</div>
    <form method="POST" action="{{ route('admin.orders.items.add', $order) }}"
          style="padding:12px 16px;display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div class="form-group" style="margin:0;flex:3;min-width:220px">
            <label>Produkt *</label>
            <select name="product_id" required>
                <option value="">— Produkt wählen —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->artikelnummer }} · {{ $p->produktname }}
                        ({{ number_format($p->base_price_gross_milli / 1_000_000, 2, ',', '.') }} €)
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;width:100px">
            <label>Menge *</label>
            <input type="number" name="qty" value="1" min="1" max="9999" required style="text-align:center">
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Hinzufügen</button>
    </form>
</div>

@endsection

@push('scripts')
<script>
// "Alle markieren" Checkbox
document.getElementById('check-all').addEventListener('change', function() {
    document.querySelectorAll('.remove-check').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
