@extends('admin.layout')

@section('title', 'Lieferrückgaben')

@section('content')

{{-- Filter --}}
<form method="GET" action="{{ route('admin.rental.delivery-returns.index') }}" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0">
        <label style="font-size:12px">Rückgabeart</label>
        <select name="return_type" class="form-control" style="min-width:160px">
            <option value="">Alle Arten</option>
            <option value="full"    {{ request('return_type') == 'full'    ? 'selected' : '' }}>Vollständig</option>
            <option value="partial" {{ request('return_type') == 'partial' ? 'selected' : '' }}>Teilrückgabe</option>
            <option value="damaged" {{ request('return_type') == 'damaged' ? 'selected' : '' }}>Mit Schäden</option>
        </select>
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Filtern</button>
    @if(request('return_type'))
        <a href="{{ route('admin.rental.delivery-returns.index') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header">
        Lieferrückgaben ({{ $deliveryReturns->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th>Fahrer</th>
                    <th>Rückgabe am</th>
                    <th>Rückgabeart</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($deliveryReturns as $return)
                <tr>
                    <td>{{ $return->customer?->name ?? '—' }}</td>
                    <td>{{ $return->driver?->name ?? '—' }}</td>
                    <td>{{ $return->returned_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>
                        @switch($return->return_type)
                            @case('full')    <span class="badge badge-delivered">Vollständig</span> @break
                            @case('partial') <span class="badge badge-pending">Teilrückgabe</span> @break
                            @case('damaged') <span class="badge badge-cancelled">Mit Schäden</span> @break
                            @default <span class="badge">{{ $return->return_type }}</span>
                        @endswitch
                    </td>
                    <td style="text-align:right">
                        <a href="{{ route('admin.rental.delivery-returns.show', $return) }}"
                           class="btn btn-outline btn-sm">Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Lieferrückgaben gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $deliveryReturns->links() }}
@endsection
