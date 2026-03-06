@extends('admin.layout')

@section('title', 'Neues Fahrer-Token ausstellen')

@section('content')

<div class="card" style="max-width:560px">
    <div class="card-header">Token-Details</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.driver-tokens.store') }}">
            @csrf

            <div class="form-group">
                <label for="label">
                    Bezeichnung <span style="color:var(--c-danger)">*</span>
                </label>
                <input type="text"
                       id="label"
                       name="label"
                       class="form-control @error('label') is-invalid @enderror"
                       value="{{ old('label') }}"
                       maxlength="120"
                       placeholder="z. B. Fahrer Klaus Müller – Tablet 1"
                       required>
                @error('label')
                    <div class="hint" style="color:var(--c-danger)">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="expires_at">
                    Ablaufdatum <span class="text-muted">(optional)</span>
                </label>
                <input type="date"
                       id="expires_at"
                       name="expires_at"
                       class="form-control @error('expires_at') is-invalid @enderror"
                       value="{{ old('expires_at') }}"
                       min="{{ now()->addDay()->toDateString() }}">
                <div class="hint">Leer lassen = läuft nie ab.</div>
                @error('expires_at')
                    <div class="hint" style="color:var(--c-danger)">{{ $message }}</div>
                @enderror
            </div>

            <div class="alert alert-warning" style="margin-top:16px;margin-bottom:16px">
                ⚠️ Der Token wird <strong>nur einmal</strong> nach dem Ausstellen angezeigt.
                Notiere ihn sofort sicher.
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Token ausstellen</button>
                <a href="{{ route('admin.driver-tokens.index') }}" class="btn btn-outline">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
