@extends('admin.layout')

@section('title', 'Mitarbeiterliste')

@section('content')
<div class="page-header">
    <h1>Mitarbeiter</h1>
    <div class="page-actions">
        <a href="{{ route('admin.employees.dashboard') }}" class="btn btn-secondary">Dashboard</a>
        <a href="{{ route('admin.reconcile.employees') }}" class="btn btn-secondary">Ninox-Abgleich</a>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">+ Neuer Mitarbeiter</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Personalnummer</th>
                    <th>Rolle</th>
                    <th>Beschäftigungsart</th>
                    <th>Std./Woche</th>
                    <th>Status</th>
                    <th>Ninox</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                @php $nx = $ninoxData[$employee->id] ?? null; @endphp
                <tr @if($employee->trashed()) style="opacity:.6;" @endif>
                    <td>{{ $loop->iteration }}</td>

                    <td @if($employee->trashed()) style="text-decoration:line-through;" @endif>
                        <div style="font-weight:500">{{ $employee->full_name }}</div>
                        @php
                            $nick = $employee->nickname ?: ($nx?->spitzname ?? '');
                        @endphp
                        @if($nick && $nick !== $employee->first_name)
                            <div style="font-size:11px;color:var(--c-muted)">"{{ $nick }}"</div>
                        @endif
                    </td>

                    <td>{{ $employee->employee_number }}</td>
                    <td>{{ ucfirst($employee->role) }}</td>
                    <td>{{ str_replace('_', ' ', ucfirst($employee->employment_type)) }}</td>
                    <td>{{ $employee->weekly_hours }} h</td>

                    <td>
                        @if($employee->trashed())
                            <span class="badge badge-danger">Gelöscht</span>
                        @elseif($employee->is_active)
                            <span class="badge badge-success">Aktiv</span>
                        @else
                            <span class="badge badge-secondary">Inaktiv</span>
                        @endif
                        @php
                            $obStatus = match($employee->onboarding_status) {
                                'pending'        => ['Onboarding offen',    '#6366f1'],
                                'pending_review' => ['Wartet auf Freigabe', '#f59e0b'],
                                'approved'       => ['Freigegeben',         '#10b981'],
                                'active'         => null,
                                default          => [$employee->onboarding_status, '#94a3b8'],
                            };
                        @endphp
                        @if($obStatus)
                            <div style="margin-top:3px">
                                <a href="{{ route('admin.onboarding.show', $employee) }}"
                                   style="font-size:11px;color:{{ $obStatus[1] }};text-decoration:none"
                                   title="Zum Onboarding">
                                    ● {{ $obStatus[0] }}
                                </a>
                            </div>
                        @endif
                    </td>

                    {{-- Ninox column --}}
                    <td>
                        @if($nx)
                            <div style="display:flex;flex-direction:column;gap:3px">
                                <span style="display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;font-family:monospace;
                                            background:color-mix(in srgb,#10b981 15%,transparent);color:#10b981;border:1px solid #10b981;width:fit-content">
                                    Ninox #{{ $nx->ninox_id }}
                                </span>
                                @if($nx->status && (($nx->status === 'Aktiv') !== $employee->is_active))
                                    <span style="font-size:11px;color:#f59e0b" title="Ninox-Status weicht vom lokalen Status ab">
                                        ⚠ Ninox: {{ $nx->status }}
                                    </span>
                                @elseif($nx->status)
                                    <span style="font-size:11px;color:var(--c-muted)">{{ $nx->status }}</span>
                                @endif
                                @if($nx->profilbild)
                                    <span style="font-size:11px;color:var(--c-muted)" title="{{ $nx->profilbild }}">📷 Bild hinterlegt</span>
                                @endif
                            </div>
                        @elseif($employee->ninox_source_id)
                            <span style="font-size:11px;color:#f59e0b">⚠ ID {{ $employee->ninox_source_id }} (fehlt)</span>
                        @else
                            <span style="font-size:11px;color:var(--c-muted)">—</span>
                        @endif
                    </td>

                    <td>
                        @if($employee->trashed())
                            <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-secondary">Wiederherstellen</a>
                        @else
                            <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-secondary">Bearbeiten</a>
                            <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" style="display:inline;" onsubmit="return confirm('Mitarbeiter wirklich deaktivieren?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--c-muted,#64748b);padding:2rem;">
                        Noch keine Mitarbeiter angelegt.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
