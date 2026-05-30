@extends('admin.layout')

@section('title', 'Neue Tour anlegen')

@section('content')

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card" style="max-width:560px">
    <div class="card-header">Neue Fahrertour generieren</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.touren.store') }}">
            @csrf

            <div class="form-group" style="margin-bottom:16px">
                <label>Tour-Template <span style="color:#dc2626">*</span></label>
                <select name="regular_delivery_tour_id" required style="width:100%">
                    <option value="">— Template wählen —</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}" @selected(old('regular_delivery_tour_id') == $t->id)>
                            {{ $t->name }} ({{ $t->day_of_week }}, {{ $t->frequency }})
                        </option>
                    @endforeach
                </select>
                @error('regular_delivery_tour_id')
                    <p style="color:#dc2626;font-size:.8rem;margin-top:4px">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group" style="margin-bottom:20px">
                <label>Datum <span style="color:#dc2626">*</span></label>
                <input type="date" name="tour_date" value="{{ old('tour_date', today()->toDateString()) }}" required style="width:100%">
                @error('tour_date')
                    <p style="color:#dc2626;font-size:.8rem;margin-top:4px">{{ $message }}</p>
                @enderror
            </div>

            <p style="font-size:.82rem;color:var(--c-muted);margin-bottom:16px">
                Es werden automatisch alle Bestellungen für das gewählte Datum und Template als Stopps angelegt.
            </p>

            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">Tour anlegen</button>
                <a href="{{ route('admin.touren.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
