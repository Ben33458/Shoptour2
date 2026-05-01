@extends('admin.layout')

@section('title', 'Mahnläufe')

@section('actions')
    <a href="{{ route('admin.dunning.create') }}" class="btn btn-primary btn-sm">+ Neuer Mahnlauf</a>
    <a href="{{ route('admin.debtor.index') }}" class="btn btn-outline btn-sm">Offene Posten</a>
@endsection

@section('content')

@if(session('success'))
<div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
    {{ session('success') }}
</div>
@endif

<div class="card">
    <div class="card-header">Alle Mahnläufe ({{ $runs->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Erstellt</th>
                    <th>Erstellt von</th>
                    <th>Status</th>
                    <th>Testmodus</th>
                    <th>Positionen</th>
                    <th>Versendet</th>
                    <th>Fehlgeschlagen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td><code>#{{ $run->id }}</code></td>
                    <td>{{ $run->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $run->createdBy?->name ?? '—' }}</td>
                    <td>
                        @if($run->isDraft())
                            <span class="badge badge-pending">Entwurf</span>
                        @elseif($run->isSent())
                            <span class="badge badge-delivered">Versendet</span>
                        @else
                            <span class="badge badge-cancelled">Abgebrochen</span>
                        @endif
                    </td>
                    <td>
                        @if($run->is_test_mode)
                            <span class="badge" style="background:#fef3c7;color:#92400e">Test</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $run->items()->count() }}</td>
                    <td>{{ $run->sentCount() }}</td>
                    <td>
                        @if($run->failedCount() > 0)
                            <span style="color:#dc2626">{{ $run->failedCount() }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.dunning.show', $run) }}" class="btn btn-outline btn-sm">Detail</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted" style="padding:24px">
                        Noch keine Mahnläufe.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $runs->links() }}

@endsection
