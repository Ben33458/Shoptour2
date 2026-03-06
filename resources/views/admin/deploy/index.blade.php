@extends('admin.layout')

@section('title', 'Deployment')

@section('content')

{{-- ── Release info ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Aktuelle Version</div>
    <div class="card-body" style="font-size:.9em">
        @if(!empty($manifest))
            <table style="border-collapse:collapse;width:auto">
                <tr><td style="padding:3px 16px 3px 0;color:var(--c-muted)">Version</td>
                    <td><strong>{{ $manifest['version'] ?? '?' }}</strong></td></tr>
                <tr><td style="padding:3px 16px 3px 0;color:var(--c-muted)">Git SHA</td>
                    <td><code>{{ $manifest['git_sha'] ?? '?' }}</code></td></tr>
                <tr><td style="padding:3px 16px 3px 0;color:var(--c-muted)">Build</td>
                    <td>{{ $manifest['build_date'] ?? '?' }}</td></tr>
                <tr><td style="padding:3px 16px 3px 0;color:var(--c-muted)">Erstellt von</td>
                    <td>{{ $manifest['built_by'] ?? '?' }}</td></tr>
            </table>
        @else
            <span class="text-muted">Kein Release-Manifest gefunden (storage/app/release.json).</span>
        @endif
    </div>
</div>

{{-- ── Token status ── --}}
@if(!$tokenConfigured)
    <div class="alert alert-error">
        ⚠️ <strong>DEPLOY_TOKEN</strong> ist nicht in der .env gesetzt.
        Deployment-Aktionen sind deaktiviert.
    </div>
@endif

{{-- ── Output from last action ── --}}
@if(session('deploy_output'))
    <div class="alert alert-success">
        <strong>{{ session('deploy_op') }} – Ausgabe:</strong><br>
        <pre style="margin:8px 0 0;font-size:.85em;white-space:pre-wrap;word-break:break-all">{{ session('deploy_output') }}</pre>
    </div>
@endif

{{-- ── Action cards ── --}}
@if($tokenConfigured)

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

        {{-- Migrate --}}
        <div class="card">
            <div class="card-header">🗄️ Migrate</div>
            <div class="card-body">
                <p style="margin-top:0;font-size:.9em">
                    Führt <code>php artisan migrate --force</code> aus.<br>
                    Neue Migrationen werden angewendet.
                </p>
                <form method="POST" action="{{ route('admin.deploy.migrate') }}"
                      onsubmit="return confirm('Migrationen jetzt ausführen?')">
                    @csrf
                    <div class="form-group">
                        <input type="password" name="deploy_token"
                               class="form-control" placeholder="Deploy-Token" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Migrate ausführen</button>
                </form>
            </div>
        </div>

        {{-- Cache rebuild --}}
        <div class="card">
            <div class="card-header">⚡ Cache aufbauen</div>
            <div class="card-body">
                <p style="margin-top:0;font-size:.9em">
                    <code>config:cache · route:cache · view:cache</code><br>
                    Nach jedem Deployment ausführen.
                </p>
                <form method="POST" action="{{ route('admin.deploy.cache') }}"
                      onsubmit="return confirm('Caches neu aufbauen?')">
                    @csrf
                    <div class="form-group">
                        <input type="password" name="deploy_token"
                               class="form-control" placeholder="Deploy-Token" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Caches aufbauen</button>
                </form>
            </div>
        </div>

        {{-- Cache clear --}}
        <div class="card">
            <div class="card-header">🗑️ Cache leeren</div>
            <div class="card-body">
                <p style="margin-top:0;font-size:.9em">
                    <code>cache:clear · config:clear · route:clear · view:clear</code><br>
                    Beim Rollback oder nach Konfigurationsänderungen.
                </p>
                <form method="POST" action="{{ route('admin.deploy.clear') }}"
                      onsubmit="return confirm('Alle Caches leeren?')">
                    @csrf
                    <div class="form-group">
                        <input type="password" name="deploy_token"
                               class="form-control" placeholder="Deploy-Token" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">Caches leeren</button>
                </form>
            </div>
        </div>

        {{-- Backup --}}
        <div class="card">
            <div class="card-header">💾 Backup (On-demand)</div>
            <div class="card-body">
                <p style="margin-top:0;font-size:.9em">
                    DB-Dump + Datei-Archiv sofort erstellen.<br>
                    Immer vor einem Deployment empfohlen.
                </p>
                <form method="POST" action="{{ route('admin.deploy.backup') }}"
                      onsubmit="return confirm('Backup jetzt erstellen?')">
                    @csrf
                    <div class="form-group">
                        <input type="password" name="deploy_token"
                               class="form-control" placeholder="Deploy-Token" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm">Backup erstellen</button>
                </form>
            </div>
        </div>

    </div>

@endif

<div style="margin-top:20px">
    <a href="{{ route('admin.diagnostics') }}" class="btn btn-outline">
        → Diagnose-Seite
    </a>
</div>

@endsection
