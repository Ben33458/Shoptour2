@extends('admin.layout')

@section('title', 'Lexoffice Integration')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Lexoffice Integration</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Settings form --}}
    <div class="card mb-4">
        <div class="card-header">Einstellungen</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.integrations.lexoffice.update') }}">
                @csrf
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="enabled" name="enabled"
                           value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="enabled">Lexoffice-Sync aktiviert</label>
                </div>
                <div class="mb-3">
                    <label for="api_key" class="form-label">API-Key</label>
                    <input type="password" class="form-control" id="api_key" name="api_key"
                           value="{{ $settings['api_key'] }}" autocomplete="off"
                           placeholder="Lexoffice API-Key (Bearer Token)">
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>
    </div>

    {{-- Recent sync status --}}
    <div class="card">
        <div class="card-header">Letzte Synchronisierungen</div>
        <div class="card-body p-0">
            @if($recentInvoices->isEmpty())
                <p class="p-3 text-muted mb-0">Noch keine Synchronisierungen.</p>
            @else
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Rechnungsnummer</th>
                            <th>Lexoffice-Voucher-ID</th>
                            <th>Letzter Sync</th>
                            <th>Fehler</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentInvoices as $inv)
                        <tr>
                            <td>{{ $inv->invoice_number }}</td>
                            <td>{{ $inv->lexoffice_voucher_id ?? '–' }}</td>
                            <td>{{ $inv->lexoffice_synced_at?->format('d.m.Y H:i') ?? '–' }}</td>
                            <td class="{{ $inv->lexoffice_sync_error ? 'text-danger small' : '' }}">
                                {{ $inv->lexoffice_sync_error ?? '' }}
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('admin.integrations.lexoffice.sync', $inv) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Neu sync
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
