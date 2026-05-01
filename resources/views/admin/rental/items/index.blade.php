@extends('admin.layout')

@section('title', 'Leihgeräte')

@section('actions')
    <a href="{{ route('admin.rental.items.create') }}" class="btn btn-primary btn-sm">+ Neues Leihgerät</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        Leihgeräte ({{ $items->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:1%;white-space:nowrap">Artikelnummer</th>
                    <th style="width:1%;white-space:nowrap">Name</th>
                    <th style="width:1%;white-space:nowrap;text-align:center">Status</th>
                    <th style="width:1%;white-space:nowrap"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td style="color:var(--c-muted);font-size:.8125rem">{{ $item->article_number ?? '—' }}</td>
                    <td style="white-space:nowrap"><strong>{{ $item->name }}</strong></td>
                    <td style="text-align:center">
                        @if($item->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.items.show', $item) }}"
                           class="btn btn-outline btn-sm">Details</a>
                        <a href="{{ route('admin.rental.items.edit', $item) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Leihgeräte angelegt.
                        <a href="{{ route('admin.rental.items.create') }}"
                           class="btn btn-primary btn-sm" style="margin-left:12px">
                            + Erstes Leihgerät anlegen
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $items->links() }}
@endsection
