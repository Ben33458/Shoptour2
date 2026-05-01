@extends('admin.layout')

@section('title', 'Rückgabescheine')

@section('content')

{{-- Filter --}}
<form method="GET" action="{{ route('admin.rental.return-slips.index') }}" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0">
        <label style="font-size:12px">Status</label>
        <select name="status" class="form-control" style="min-width:160px">
            <option value="">Alle Status</option>
            <option value="open"     {{ request('status') == 'open'     ? 'selected' : '' }}>Offen</option>
            <option value="reviewed" {{ request('status') == 'reviewed' ? 'selected' : '' }}>Geprüft</option>
            <option value="charged"  {{ request('status') == 'charged'  ? 'selected' : '' }}>Verbucht</option>
        </select>
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Filtern</button>
    @if(request('status'))
        <a href="{{ route('admin.rental.return-slips.index') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header">
        Rückgabescheine ({{ $returnSlips->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bestellnummer</th>
                    <th>Kunde</th>
                    <th>Fahrer</th>
                    <th>Rückgabe am</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($returnSlips as $slip)
                <tr>
                    <td>
                        @if($slip->order)
                            <a href="{{ route('admin.orders.show', $slip->order) }}">
                                {{ $slip->order->order_number ?? '#' . $slip->order->id }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $slip->order?->customer?->name ?? '—' }}</td>
                    <td>{{ $slip->driver?->name ?? '—' }}</td>
                    <td>{{ $slip->returned_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>
                        @switch($slip->status)
                            @case('open')     <span class="badge badge-pending">offen</span> @break
                            @case('reviewed') <span class="badge">geprüft</span> @break
                            @case('charged')  <span class="badge badge-delivered">verbucht</span> @break
                            @default <span class="badge">{{ $slip->status }}</span>
                        @endswitch
                    </td>
                    <td style="text-align:right">
                        <a href="{{ route('admin.rental.return-slips.show', $slip) }}"
                           class="btn btn-outline btn-sm">Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Rückgabescheine gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $returnSlips->links() }}
@endsection
