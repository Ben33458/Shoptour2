@extends('admin.layout')

@section('title', 'Wissenslücken')

@section('content')
<div class="page-header">
    <h1>📭 Wissenslücken & Feedback</h1>
    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-secondary">← Verwaltung</a>
</div>

{{-- Top Questions without Sources --}}
@if($topQuestions->isNotEmpty())
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3>Häufige Fragen ohne gute Quelle</h3></div>
    <table class="data-table">
        <thead><tr><th>Frage</th><th>Gestellt</th></tr></thead>
        <tbody>
        @foreach($topQuestions as $q)
        <tr>
            <td>{{ $q->question }}</td>
            <td>{{ $q->cnt }}×</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Gaps --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3>Gemeldete Wissenslücken</h3></div>
    <table class="data-table">
        <thead>
        <tr><th>Frage</th><th>Kommentar</th><th>Status</th><th>Von</th><th>Wann</th><th>Aktion</th></tr>
        </thead>
        <tbody>
        @foreach($gaps as $gap)
        <tr>
            <td>{{ Str::limit($gap->question, 80) }}</td>
            <td>{{ Str::limit($gap->comment, 60) }}</td>
            <td>
                <span class="badge badge-{{ $gap->status === 'resolved' ? 'success' : ($gap->status === 'open' ? 'warning' : 'secondary') }}">
                    {{ $gap->status }}
                </span>
            </td>
            <td>{{ $gap->user?->name ?? '—' }}</td>
            <td>{{ $gap->created_at->diffForHumans() }}</td>
            <td>
                @if($gap->status !== 'resolved')
                <form method="POST" action="{{ route('admin.knowledge.gaps.resolve', $gap) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="resolved">
                    <button class="btn btn-sm btn-success">✓ Lösen</button>
                </form>
                <form method="POST" action="{{ route('admin.knowledge.gaps.resolve', $gap) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="ignored">
                    <button class="btn btn-sm btn-secondary">Ignorieren</button>
                </form>
                @endif
            </td>
        </tr>
        @endforeach
        @if($gaps->isEmpty())
            <tr><td colspan="6" class="text-muted text-center">Keine offenen Wissenslücken.</td></tr>
        @endif
        </tbody>
    </table>
    {{ $gaps->links() }}
</div>

{{-- Negative Feedback --}}
<div class="card">
    <div class="card-header"><h3>Negatives Feedback (falsch / veraltet / kritisch)</h3></div>
    <table class="data-table">
        <thead>
        <tr><th>Frage</th><th>Reaktion</th><th>Kommentar</th><th>Von</th><th>Wann</th></tr>
        </thead>
        <tbody>
        @foreach($negativeFeedback as $fb)
        <tr>
            <td>{{ Str::limit($fb->query?->question ?? '—', 80) }}</td>
            <td><span class="badge badge-danger">{{ $fb->reaction }}</span></td>
            <td>{{ Str::limit($fb->comment, 60) }}</td>
            <td>{{ $fb->user?->name ?? '—' }}</td>
            <td>{{ $fb->created_at->diffForHumans() }}</td>
        </tr>
        @endforeach
        @if($negativeFeedback->isEmpty())
            <tr><td colspan="5" class="text-muted text-center">Kein negatives Feedback.</td></tr>
        @endif
        </tbody>
    </table>
</div>
@endsection
