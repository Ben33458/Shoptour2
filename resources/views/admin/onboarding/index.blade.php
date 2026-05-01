@extends('admin.layout')

@section('title', 'Onboarding-Freigabe')

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-success) 15%,var(--c-surface));border:1px solid var(--c-success);border-radius:6px;color:var(--c-success)">
        {{ session('success') }}
    </div>
@endif

<div style="margin-bottom:20px">
    <h1 style="font-size:20px;font-weight:700;color:var(--c-text)">Mitarbeiter-Onboarding</h1>
    <p style="font-size:13px;color:var(--c-muted);margin-top:4px">
        Mitarbeiter, die ihr Onboarding eingereicht haben, warten auf deine Freigabe.
    </p>
</div>

{{-- Filter-Tabs --}}
<div style="display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap">
    @foreach([
        ['key' => 'pending_review', 'label' => 'Zur Prüfung'],
        ['key' => 'pending',        'label' => 'Noch nicht gestartet'],
        ['key' => 'active',         'label' => 'Aktiv'],
    ] as $tab)
        <a href="{{ route('admin.onboarding.index', ['filter' => $tab['key']]) }}"
           style="padding:6px 14px;border-radius:4px;font-size:13px;text-decoration:none;border:1px solid var(--c-border);
                  {{ $filter === $tab['key'] ? 'background:var(--c-primary,#2563eb);color:#fff;border-color:transparent' : 'background:var(--c-surface);color:var(--c-text)' }}">
            {{ $tab['label'] }}
            @if(($counts[$tab['key']] ?? 0) > 0)
                <span style="display:inline-block;min-width:18px;border-radius:9px;font-size:11px;margin-left:4px;padding:0 4px;
                             {{ $filter === $tab['key'] ? 'background:rgba(255,255,255,0.3)' : 'background:var(--c-bg)' }}">
                    {{ $counts[$tab['key']] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

<div class="card">
    @if($employees->isEmpty())
        <div style="padding:40px;text-align:center;color:var(--c-muted)">
            Keine Mitarbeiter in dieser Kategorie.
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Personalnr.</th>
                        <th>Eingereicht</th>
                        <th>Beschäftigung</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($employees as $emp)
                    <tr>
                        <td style="font-weight:600">{{ $emp->full_name }}</td>
                        <td style="font-size:13px;color:var(--c-muted)">{{ $emp->email }}</td>
                        <td style="font-family:monospace;font-size:12px">{{ $emp->employee_number }}</td>
                        <td style="font-size:12px;color:var(--c-muted)">
                            {{ $emp->onboarding_completed_at?->format('d.m.Y H:i') ?? '—' }}
                        </td>
                        <td style="font-size:12px;color:var(--c-muted)">{{ $emp->employment_type }}</td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="{{ route('admin.onboarding.show', $emp) }}"
                                   class="btn btn-sm btn-outline">Details</a>
                                @if($filter === 'pending_review')
                                    <form method="POST" action="{{ route('admin.onboarding.approve', $emp) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">Freigeben</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.onboarding.reject', $emp) }}"
                                          onsubmit="return confirm('Onboarding zurückweisen?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline"
                                                style="color:var(--c-danger);border-color:var(--c-danger)">
                                            Zurückweisen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px">{{ $employees->links() }}</div>
    @endif
</div>

@endsection
