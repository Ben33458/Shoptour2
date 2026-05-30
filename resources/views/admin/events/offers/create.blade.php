@extends('admin.layout')

@section('title', 'Neues Angebot')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.show', $occurrence) }}" style="color:var(--c-muted);font-size:.85rem">&larr; {{ $occurrence->title }}</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Neues Angebot erstellen</h1>
</div>

<form method="POST" action="{{ route('admin.events.offers.store', $occurrence) }}">
    @csrf

    <div class="card" style="max-width:600px">
        <div class="card-header">Angebotsdaten</div>
        <div style="padding:16px;display:grid;gap:16px">

            <div class="form-group">
                <label>Gültig bis</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}">
            </div>

            <div class="form-group">
                <label>Netto gesamt (in Cent-Milli, z.B. 1000000 = 1 €)</label>
                <input type="number" name="net_total_milli" value="{{ old('net_total_milli') }}" placeholder="1500000000">
                <small style="color:var(--c-muted)">1 EUR = 1.000.000</small>
            </div>

            <div class="form-group">
                <label>Brutto gesamt</label>
                <input type="number" name="gross_total_milli" value="{{ old('gross_total_milli') }}" placeholder="1785000000">
            </div>

            <div class="form-group">
                <label>Pfand gesamt</label>
                <input type="number" name="deposit_total_milli" value="{{ old('deposit_total_milli') }}">
            </div>

            <div class="form-group">
                <label>Verleih gesamt</label>
                <input type="number" name="rental_total_milli" value="{{ old('rental_total_milli') }}">
            </div>

            <div class="form-group">
                <label>Lieferung gesamt</label>
                <input type="number" name="delivery_total_milli" value="{{ old('delivery_total_milli') }}">
            </div>

        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Angebot erstellen</button>
        <a href="{{ route('admin.events.show', $occurrence) }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

@endsection
