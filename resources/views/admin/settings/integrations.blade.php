@extends('admin.layout')

@section('title', 'Einstellungen — Integrationen & API-Keys')

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:var(--c-success-bg,#d1fae5);border:1px solid var(--c-success,#10b981);border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #ef4444;border-radius:6px;color:#7f1d1d">
        {{ session('error') }}
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.integrations.update') }}">
@csrf

{{-- ── Lexoffice ─────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
        <span>Lexoffice</span>
        <a href="{{ route('admin.integrations.lexoffice') }}" class="btn btn-outline btn-sm">→ Lexoffice Abgleich</a>
    </div>
    <div style="padding:20px">
        <div class="form-group" style="margin-bottom:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="lexoffice_enabled" value="0">
                <input type="checkbox" name="lexoffice_enabled" value="1"
                       @checked($settings['lexoffice']['enabled'])>
                Lexoffice-Sync aktiviert
            </label>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>API-Key</label>
            <input type="password" name="lexoffice_api_key"
                   value="{{ $settings['lexoffice']['api_key'] }}"
                   autocomplete="off"
                   placeholder="Bearer Token aus Lexoffice → Einstellungen → API-Zugriff">
            <div class="hint">Zu finden unter Lexoffice → Einstellungen → API-Zugriff</div>
        </div>
    </div>
</div>

{{-- ── Ninox ─────────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">Ninox</div>
    <div style="padding:20px">
        <div class="form-row">
            <div class="form-group">
                <label>API-Key</label>
                <input type="password" name="ninox_api_key"
                       value="{{ $settings['ninox']['api_key'] }}"
                       autocomplete="off"
                       placeholder="Ninox API-Key (UUID)">
            </div>
            <div class="form-group">
                <label>Team-ID</label>
                <input type="text" name="ninox_team_id"
                       value="{{ $settings['ninox']['team_id'] }}"
                       placeholder="z.B. yzW23724nQbqCQX9R">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Datenbank-ID (Kehr / aktuell)</label>
                <input type="text" name="ninox_db_id_kehr"
                       value="{{ $settings['ninox']['db_id_kehr'] }}"
                       placeholder="z.B. tpwd0lln7f65">
                <div class="hint">Kunden, Mitarbeiter, Veranstaltungen, Kassenbuch</div>
            </div>
            <div class="form-group">
                <label>Datenbank-ID (Alt)</label>
                <input type="text" name="ninox_db_id_alt"
                       value="{{ $settings['ninox']['db_id_alt'] }}"
                       placeholder="z.B. fadrrq8poh9b">
                <div class="hint">ProduktDB, WaWi, Tourenplanung (Legacy)</div>
            </div>
        </div>
    </div>
</div>

{{-- ── GetränkeDB ────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">GetränkeDB</div>
    <div style="padding:20px">
        <div class="form-row">
            <div class="form-group">
                <label>API-URL</label>
                <input type="url" name="getraenkedb_api_url"
                       value="{{ $settings['getraenkedb']['api_url'] }}"
                       placeholder="https://...">
            </div>
            <div class="form-group">
                <label>API-Key</label>
                <input type="password" name="getraenkedb_api_key"
                       value="{{ $settings['getraenkedb']['api_key'] }}"
                       autocomplete="off">
            </div>
        </div>
    </div>
</div>

{{-- ── WaWi (JTL) ───────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">WaWi (JTL)</div>
    <div style="padding:20px">
        <div class="form-group" style="max-width:480px">
            <label>Sync-Token</label>
            <input type="password" name="wawi_sync_token"
                   value="{{ $settings['wawi']['sync_token'] }}"
                   autocomplete="off">
            <div class="hint">Authentifizierungstoken für den WaWi-Sync-Endpunkt</div>
        </div>
    </div>
</div>

<div style="margin-top:8px">
    <button type="submit" class="btn btn-primary">Alle Einstellungen speichern</button>
</div>

</form>

@endsection
