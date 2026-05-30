@extends('admin.layout')

@section('title', 'Fahrertouren')

@section('content')

{{-- Filter bar: GET-Form + POST-Form nebeneinander, nicht verschachtelt --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('admin.touren.index') }}" style="display:contents">
        <div class="form-group">
            <label>Datum</label>
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">— Alle —</option>
                <option value="planned"     @selected($status === 'planned')>Geplant</option>
                <option value="in_progress" @selected($status === 'in_progress')>Unterwegs</option>
                <option value="done"        @selected($status === 'done')>Fertig</option>
                <option value="cancelled"   @selected($status === 'cancelled')>Storniert</option>
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <a href="{{ route('admin.touren.index') }}" class="btn btn-outline">Zurücksetzen</a>
            <a href="{{ route('admin.touren.create') }}" class="btn btn-primary">+ Neue Tour</a>
        </div>
    </form>
    <form method="POST" action="{{ route('admin.touren.ninox-pull') }}" style="align-self:flex-end;padding-bottom:0">
        @csrf
        <button type="submit" class="btn btn-outline">↓ Aus Ninox importieren</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        @if($defaultMode)
            Nächste nicht abgeschlossene Touren ({{ $tours->count() }})
        @else
            Touren am {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }} ({{ $tours->count() }})
        @endif
    </div>
    <div class="table-wrap">
        <table id="touren-table">
            <thead>
                <tr>
                    <th>Tour-Name</th>
                    <th>Datum</th>
                    <th>Tour / Template</th>
                    <th>Fahrer</th>
                    <th style="text-align:center">Stopps</th>
                    <th style="text-align:center">VPE</th>
                    <th style="text-align:center">Status</th>
                    <th data-no-sort data-no-filter data-no-resize data-no-reorder></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tours as $tour)
                    @php
                        $vpe = $tour->stops->sum(fn($s) => $s->order?->items?->sum('qty') ?? 0);
                        $employee = $tour->driver_employee_id ? ($employees[$tour->driver_employee_id] ?? null) : null;
                        $statusLabels = [
                            'planned'     => ['label' => 'Geplant',     'color' => '#64748b'],
                            'in_progress' => ['label' => 'Unterwegs',   'color' => '#d97706'],
                            'done'        => ['label' => 'Fertig',      'color' => '#16a34a'],
                            'cancelled'   => ['label' => 'Storniert',   'color' => '#dc2626'],
                        ];
                        $sl = $statusLabels[$tour->status] ?? ['label' => $tour->status, 'color' => '#64748b'];
                    @endphp
                    <tr>
                        <td>{{ $tour->name ?? '—' }}</td>
                        <td>{{ $tour->tour_date->format('d.m.Y') }}</td>
                        <td>{{ $tour->regularDeliveryTour?->name ?? '—' }}</td>
                        <td>{{ $employee ? ($employee->first_name . ' ' . $employee->last_name) : '—' }}</td>
                        <td style="text-align:center">{{ $tour->stops->count() }}</td>
                        <td style="text-align:center">{{ $vpe }}</td>
                        <td style="text-align:center">
                            <span style="background:{{ $sl['color'] }}1a;color:{{ $sl['color'] }};border-radius:12px;padding:2px 10px;font-size:.8rem;font-weight:600">
                                {{ $sl['label'] }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.touren.show', $tour) }}" class="btn btn-sm btn-outline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">Keine Touren gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
new AdminTable('touren-table', { tableKey: 'touren-index' });
</script>
@endpush
