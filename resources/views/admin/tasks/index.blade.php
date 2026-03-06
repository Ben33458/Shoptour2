@extends('admin.layout')

@section('title', 'Aufgaben-Queue')

@section('content')

{{-- ── Summary badges ── --}}
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
    <a href="{{ route('admin.tasks.index') }}"
       class="btn {{ !$statusFilter ? 'btn-primary' : 'btn-outline' }} btn-sm">
        Alle ({{ array_sum($counts) }})
    </a>
    <a href="{{ route('admin.tasks.index', ['status' => 'pending']) }}"
       class="btn {{ $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline' }} btn-sm">
        Wartend ({{ $counts['pending'] }})
    </a>
    <a href="{{ route('admin.tasks.index', ['status' => 'running']) }}"
       class="btn {{ $statusFilter === 'running' ? 'btn-primary' : 'btn-outline' }} btn-sm">
        Läuft ({{ $counts['running'] }})
    </a>
    <a href="{{ route('admin.tasks.index', ['status' => 'failed']) }}"
       class="btn {{ $statusFilter === 'failed' ? 'btn-primary' : 'btn-outline' }} btn-sm"
       style="{{ $counts['failed'] > 0 ? 'color:var(--c-danger)' : '' }}">
        Fehlgeschlagen ({{ $counts['failed'] }})
    </a>
    <a href="{{ route('admin.tasks.index', ['status' => 'done']) }}"
       class="btn {{ $statusFilter === 'done' ? 'btn-primary' : 'btn-outline' }} btn-sm">
        Erledigt ({{ $counts['done'] }})
    </a>
</div>

@if($tasks->isEmpty())
    <p class="text-muted">Keine Aufgaben gefunden.</p>
@else
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Typ</th>
                <th style="width:110px">Status</th>
                <th style="width:90px">Versuche</th>
                <th>Letzter Fehler</th>
                <th style="width:130px">Ausführen ab</th>
                <th style="width:130px">Erstellt</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
            <tr>
                <td class="text-muted">{{ $task->id }}</td>
                <td><code style="font-size:.85em">{{ $task->type }}</code></td>
                <td>
                    @php
                        $badgeColor = match($task->status) {
                            'pending' => 'var(--c-warning, #e6a817)',
                            'running' => 'var(--c-info, #0ea5e9)',
                            'done'    => 'var(--c-success)',
                            'failed'  => 'var(--c-danger)',
                            default   => '#888',
                        };
                    @endphp
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:.8em;background:{{ $badgeColor }};color:#fff">
                        {{ $task->status }}
                    </span>
                </td>
                <td class="text-center">
                    {{ $task->attempts }} / {{ $task->max_attempts }}
                </td>
                <td style="font-size:.82em;color:var(--c-danger)">
                    @if($task->last_error)
                        <span title="{{ $task->last_error }}">
                            {{ Str::limit($task->last_error, 80) }}
                        </span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-muted" style="font-size:.85em">
                    {{ $task->run_after?->format('d.m.Y H:i') ?? '—' }}
                </td>
                <td class="text-muted" style="font-size:.85em">
                    {{ $task->created_at->format('d.m.Y H:i') }}
                </td>
                <td>
                    @if($task->status === 'failed')
                        <form method="POST"
                              action="{{ route('admin.tasks.retry', $task) }}"
                              style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm">
                                Wiederholen
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:16px">
    {{ $tasks->links() }}
</div>
@endif

@endsection
