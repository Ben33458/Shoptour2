@extends('admin.layout')

@section('title', 'Wissensassistent Verwaltung')

@section('content')
<div class="page-header">
    <h1>🧠 Wissensassistent Verwaltung</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('admin.knowledge.gaps') }}" class="btn btn-secondary">Wissenslücken →</a>
    </div>
</div>

{{-- Status Cards --}}
<div class="grid grid-4" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Dokumente gesamt</div>
        <div class="stat-value">{{ number_format($totalDocs) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Indexiert</div>
        <div class="stat-value">{{ number_format($indexedDocs) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Chunks in DB</div>
        <div class="stat-value">{{ number_format($totalChunks) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Qdrant Points</div>
        <div class="stat-value">{{ number_format($qdrantPoints) }}</div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Actions --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3>Aktionen</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
            <form method="POST" action="{{ route('admin.knowledge.sync') }}" style="display:inline">
                @csrf
                <input type="hidden" name="source" value="paperless">
                <button class="btn btn-secondary">🔄 Paperless syncen</button>
            </form>
            <form method="POST" action="{{ route('admin.knowledge.sync') }}" style="display:inline">
                @csrf
                <input type="hidden" name="source" value="bookstack">
                <button class="btn btn-secondary">🔄 BookStack syncen</button>
            </form>
            <form method="POST" action="{{ route('admin.knowledge.sync') }}" style="display:inline">
                @csrf
                <input type="hidden" name="source" value="all">
                <button class="btn btn-primary">🔄 Alles syncen</button>
            </form>
            <form method="POST" action="{{ route('admin.knowledge.reindex') }}" style="display:inline"
                  onsubmit="return confirm('Vollständiger Reindex – das kann einige Minuten dauern. Fortfahren?')">
                @csrf
                <button class="btn btn-danger">♻️ Alles neu indexieren</button>
            </form>
            <button class="btn btn-secondary" onclick="testConnections()">🔌 Verbindungen testen</button>
        </div>
        <div id="test-result" style="font-size:13px;color:var(--c-muted)"></div>
    </div>
</div>

{{-- Sources --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3>Quellen</h3></div>
    <table class="data-table">
        <thead>
        <tr>
            <th>Typ</th><th>Name</th><th>Dokumente</th><th>Letzter Sync</th>
        </tr>
        </thead>
        <tbody>
        @foreach($sources as $src)
        <tr>
            <td><span class="badge badge-secondary">{{ $src->source_type }}</span></td>
            <td>{{ $src->name }}</td>
            <td>{{ $src->documents_count }}</td>
            <td>{{ $src->last_synced_at?->diffForHumans() ?? '—' }}</td>
        </tr>
        @endforeach
        @if($sources->isEmpty())
            <tr><td colspan="4" class="text-muted text-center">Noch keine Quellen indexiert.</td></tr>
        @endif
        </tbody>
    </table>
</div>

{{-- Sync runs --}}
<div class="card">
    <div class="card-header"><h3>Letzte Sync-Läufe</h3></div>
    <table class="data-table">
        <thead>
        <tr>
            <th>Quelle</th><th>Status</th><th>Gestartet</th><th>Dauer</th>
            <th>Gesehen</th><th>Indexiert</th><th>Fehler</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lastRuns as $run)
        <tr>
            <td>{{ $run->source }}</td>
            <td>
                <span class="badge badge-{{ $run->status === 'completed' ? 'success' : ($run->status === 'running' ? 'warning' : 'danger') }}">
                    {{ $run->status }}
                </span>
            </td>
            <td>{{ $run->started_at->format('d.m. H:i') }}</td>
            <td>
                @if($run->finished_at)
                    {{ $run->started_at->diffInSeconds($run->finished_at) }}s
                @else
                    läuft…
                @endif
            </td>
            <td>{{ $run->documents_seen }}</td>
            <td>{{ $run->documents_indexed }}</td>
            <td>{{ $run->errors > 0 ? "⚠️ {$run->errors}" : '—' }}</td>
        </tr>
        @endforeach
        @if($lastRuns->isEmpty())
            <tr><td colspan="7" class="text-muted text-center">Noch keine Sync-Läufe.</td></tr>
        @endif
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
function testConnections() {
    const el = document.getElementById('test-result');
    el.textContent = 'Teste Verbindungen…';
    fetch('{{ route('admin.knowledge.test') }}')
        .then(r => r.json())
        .then(data => {
            el.innerHTML = Object.entries(data).map(([k, v]) =>
                `<span style="margin-right:16px">${k}: <strong style="color:${v === true ? 'green' : v === false ? 'red' : 'orange'}">${v === true ? '✅ OK' : v === false ? '❌ FEHLER' : '⚠️ ' + v}</strong></span>`
            ).join('');
        });
}
</script>
@endpush
