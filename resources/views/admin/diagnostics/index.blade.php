@extends('admin.layout')

@section('title', 'System-Diagnose')

@section('actions')
    <a href="{{ route('admin.deploy.index') }}" class="btn btn-outline btn-sm">
        Deployment
    </a>
@endsection

@section('content')

@php
    $ok  = '<span style="color:var(--c-success)">✓</span>';
    $err = '<span style="color:var(--c-danger)">✗</span>';
    $warn = '<span style="color:var(--c-warning,#e6a817)">⚠</span>';
@endphp

{{-- ── Version ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">📦 Anwendungsversion</div>
    <div class="card-body">
        @if(!empty($manifest))
            <strong>v{{ $manifest['version'] ?? '?' }}</strong>
            &nbsp;·&nbsp; <code>{{ $manifest['git_sha'] ?? '' }}</code>
            &nbsp;·&nbsp; {{ $manifest['build_date'] ?? '' }}
            &nbsp;·&nbsp; {{ $manifest['built_by'] ?? '' }}
        @else
            <span class="text-muted">Kein release.json gefunden – Entwicklungs-Build.</span>
        @endif
        <br><small class="text-muted">APP_ENV: <code>{{ config('app.env') }}</code></small>
    </div>
</div>

{{-- ── Checks grid ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:20px">

    {{-- DB --}}
    <div class="card">
        <div class="card-header">🗄️ Datenbank</div>
        <div class="card-body diag-list">
            <div>{!! $dbOk ? $ok : $err !!} Verbindung
                @if($dbOk) <span class="text-muted">({{ $dbName }})</span>
                @else <span style="color:var(--c-danger);font-size:.85em">{{ $dbError }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Storage --}}
    <div class="card">
        <div class="card-header">📁 Dateisystem</div>
        <div class="card-body diag-list">
            <div>{!! $storageWritable ? $ok : $err !!} storage/app schreibbar</div>
            <div>{!! $logsWritable    ? $ok : $err !!} storage/logs schreibbar</div>
            <div>{!! $cacheWritable   ? $ok : $err !!} bootstrap/cache schreibbar</div>
            <div>{!! $storageFreeGb > 1 ? $ok : $warn !!}
                Freier Speicher: <strong>{{ $storageFreeGb }} GB</strong>
                / {{ $storageTotalGb }} GB
            </div>
        </div>
    </div>

    {{-- Security --}}
    <div class="card">
        <div class="card-header">🔒 Sicherheit</div>
        <div class="card-body diag-list">
            <div>{!! $debugOff      ? $ok : $err !!} APP_DEBUG=false</div>
            <div>{!! $envProduction ? $ok : $warn !!} APP_ENV=production</div>
            <div>{!! $sessionSecure ? $ok : $warn !!} SESSION_SECURE_COOKIE</div>
            <div>{!! $deployTokenSet ? $ok : $warn !!} DEPLOY_TOKEN konfiguriert</div>
        </div>
    </div>

    {{-- Runtime --}}
    <div class="card">
        <div class="card-header">⚙️ Laufzeitumgebung</div>
        <div class="card-body diag-list">
            <div>{!! $phpOk ? $ok : $err !!} PHP {{ $phpVersion }}</div>
            <div>{{ $ok }} Session: <code>{{ $sessionDriver }}</code></div>
            <div>{{ $ok }} Cache: <code>{{ $cacheDriver }}</code></div>
            <div>{{ $ok }} Queue: <code>{{ $queueConn }}</code></div>
        </div>
    </div>

    {{-- PHP extensions --}}
    <div class="card">
        <div class="card-header">🧩 PHP-Erweiterungen</div>
        <div class="card-body diag-list">
            @foreach($extensions as $ext => $loaded)
                <div>{!! $loaded ? $ok : $err !!} {{ $ext }}</div>
            @endforeach
        </div>
    </div>

    {{-- Backups --}}
    <div class="card">
        <div class="card-header">💾 Backups</div>
        <div class="card-body diag-list">
            @if($latestDbBackup)
                <div>{{ $ok }} DB: <code>{{ $latestDbBackup['name'] }}</code><br>
                    <small class="text-muted">{{ $latestDbBackup['mtime'] }} · {{ $latestDbBackup['size_kb'] }} KB</small>
                </div>
            @else
                <div>{!! $warn !!} Kein DB-Backup gefunden</div>
            @endif

            @if($latestFilesBackup)
                <div>{{ $ok }} Dateien: <code>{{ $latestFilesBackup['name'] }}</code><br>
                    <small class="text-muted">{{ $latestFilesBackup['mtime'] }} · {{ $latestFilesBackup['size_kb'] }} KB</small>
                </div>
            @else
                <div>{!! $warn !!} Kein Datei-Backup gefunden</div>
            @endif

            <div>Gesamt Backups: <strong>{{ $backupCount }}</strong></div>
        </div>
    </div>

</div>

{{-- ── Deferred tasks (WP-18) ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        ⏳ Aufgaben-Queue
        &nbsp;·&nbsp;
        <a href="{{ route('admin.tasks.index') }}" style="font-size:.85em;font-weight:normal">Alle anzeigen →</a>
    </div>
    <div class="card-body diag-list">
        <div>
            {!! $pendingTasks > 0 ? $warn : $ok !!}
            Wartend: <strong>{{ $pendingTasks }}</strong>
        </div>
        <div>
            {!! $failedTasks > 0 ? $err : $ok !!}
            Fehlgeschlagen: <strong>{{ $failedTasks }}</strong>
        </div>

        @if($recentErrors->isNotEmpty())
            <div style="margin-top:8px;font-size:.85em">
                <strong>Letzte Fehler:</strong>
                @foreach($recentErrors as $errTask)
                    <div style="margin-top:4px;color:var(--c-danger)">
                        #{{ $errTask->id }} <code>{{ $errTask->type }}</code>:
                        {{ Str::limit($errTask->last_error ?? '', 100) }}
                        <span class="text-muted">({{ $errTask->updated_at->format('d.m. H:i') }})</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- ── Overall result ── --}}
@php
    $allOk = $dbOk && $storageWritable && $cacheWritable && $logsWritable && $debugOff && $phpOk;
@endphp
<div class="alert alert-{{ $allOk ? 'success' : 'error' }}">
    @if($allOk)
        ✅ Alle kritischen Prüfungen bestanden. Das System ist betriebsbereit.
    @else
        ❌ Mindestens eine kritische Prüfung fehlgeschlagen – bitte oben beheben.
    @endif
</div>

<style>
.diag-list > div { padding: 3px 0; font-size: .9em; }
</style>

@endsection
