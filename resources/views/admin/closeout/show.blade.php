@extends('admin.layout')

@section('title', 'Abschluss — Bestellung #' . $order->id)

@section('actions')
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline btn-sm">← Bestellung</a>
    <a href="{{ route('admin.orders.invoice', $order) }}" class="btn btn-primary btn-sm">Rechnung</a>
@endsection

@section('content')

{{-- ── Existing adjustments ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        Bisherige Anpassungen
        <span class="badge badge-draft" style="margin-left:auto">
            {{ $adjustments->count() }} Einträge
        </span>
    </div>
    @if($adjustments->isEmpty())
        <div class="card-body text-muted text-center" style="padding:20px">
            Noch keine Anpassungen vorhanden.
        </div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Referenz</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right">Betrag</th>
                    <th>Notiz</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
            @foreach($adjustments as $adj)
                <tr>
                    <td>
                        <span class="badge badge-{{ $adj->adjustment_type }}">
                            {{ strtoupper($adj->adjustment_type) }}
                        </span>
                    </td>
                    <td>{{ $adj->reference_label ?? '—' }}</td>
                    <td class="text-right">{{ $adj->qty }}</td>
                    <td class="text-right">
                        {{ number_format($adj->amount_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-muted" style="font-size:12px">{{ $adj->note ?? '—' }}</td>
                    <td class="text-muted">{{ $adj->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── Add adjustment form ── --}}
<div class="card">
    <div class="card-header">Neue Anpassung hinzufügen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.orders.closeout.store', $order) }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label>Typ <span style="color:var(--c-danger)">*</span></label>
                    <select name="adjustment_type" required>
                        <option value="">— wählen —</option>
                        <option value="leergut" @selected(old('adjustment_type') === 'leergut')>
                            Leergut (Rückgabe / Pfand)
                        </option>
                        <option value="bruch" @selected(old('adjustment_type') === 'bruch')>
                            Bruch / Schwund
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gebinde (optional)</label>
                    <select name="gebinde_id">
                        <option value="">— kein Gebinde —</option>
                        @foreach($gebindeList as $g)
                            <option value="{{ $g->id }}" @selected(old('gebinde_id') == $g->id)>
                                {{ $g->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Produkt (optional)</label>
                    <select name="product_id">
                        <option value="">— kein Produkt —</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>
                                {{ $p->artikelnummer }} — {{ $p->produktname }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Bezeichnung (frei)</label>
                    <input type="text" name="reference_label"
                           value="{{ old('reference_label') }}"
                           placeholder="z.B. Kasten Pils 20×0,5L">
                    <div class="hint">Wird automatisch befüllt, falls Gebinde/Produkt gewählt.</div>
                </div>

                <div class="form-group">
                    <label>Menge <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="qty" min="1" value="{{ old('qty', 1) }}" required>
                </div>

                <div class="form-group">
                    <label>Betrag in € (optional)</label>
                    <input type="number" name="amount_euros" step="0.01" min="-9999" max="9999"
                           value="{{ old('amount_euros', '0.00') }}" placeholder="0.00">
                    <div class="hint">Negativ = Gutschrift, z.&nbsp;B. <code>-5.00</code> für −5,00 €.</div>
                </div>
            </div>

            <div class="form-group">
                <label>Notiz</label>
                <textarea name="note" rows="2"
                          placeholder="Freier Kommentar…">{{ old('note') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Anpassung speichern</button>
        </form>
    </div>
</div>

@endsection
