@extends('admin.layout')

@section('title', 'Onboarding: ' . $employee->full_name)

@section('content')

<div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <a href="{{ route('admin.onboarding.index') }}" style="font-size:13px;color:var(--c-muted)">← Onboarding-Liste</a>
    @if($employee->onboarding_status === 'pending_review')
        <form method="POST" action="{{ route('admin.onboarding.approve', $employee) }}" style="margin-left:auto">
            @csrf
            <button type="submit" class="btn btn-primary">Freigeben &amp; aktivieren</button>
        </form>
        <form method="POST" action="{{ route('admin.onboarding.reject', $employee) }}"
              onsubmit="return confirm('Onboarding zurückweisen?')">
            @csrf
            <button type="submit" class="btn btn-outline" style="color:var(--c-danger);border-color:var(--c-danger)">
                Zurückweisen
            </button>
        </form>
    @endif
    @if($employee->onboarding_status !== 'pending')
        <form method="POST" action="{{ route('admin.employees.reset-onboarding', $employee) }}"
              onsubmit="return confirm('Onboarding für {{ $employee->full_name }} komplett zurücksetzen?')">
            @csrf
            <button type="submit" class="btn btn-sm"
                    style="background:#f59e0b;color:#fff;border:none">
                Onboarding zurücksetzen
            </button>
        </form>
    @endif
</div>

@php
    $statusLabel = match($employee->onboarding_status) {
        'pending'        => 'Noch nicht gestartet',
        'pending_review' => 'Warten auf Freigabe',
        'approved'       => 'Freigegeben',
        'active'         => 'Aktiv',
        default          => $employee->onboarding_status,
    };
    $statusColor = match($employee->onboarding_status) {
        'pending_review' => '#f59e0b',
        'active'         => '#10b981',
        'approved'       => '#3b82f6',
        default          => 'var(--c-muted)',
    };
@endphp

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px">
        <div style="font-size:12px;color:var(--c-muted);margin-bottom:4px">Status</div>
        <div style="font-size:16px;font-weight:700;color:{{ $statusColor }}">{{ $statusLabel }}</div>
    </div>
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px">
        <div style="font-size:12px;color:var(--c-muted);margin-bottom:4px">Personalnummer</div>
        <div style="font-size:16px;font-weight:700;font-family:monospace">{{ $employee->employee_number }}</div>
    </div>
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px">
        <div style="font-size:12px;color:var(--c-muted);margin-bottom:4px">Eingereicht</div>
        <div style="font-size:14px;font-weight:600">{{ $employee->onboarding_completed_at?->format('d.m.Y H:i') ?? '—' }}</div>
    </div>
</div>

@php
    $sections = [
        'Persönliche Daten' => [
            'Vorname'        => $employee->first_name,
            'Nachname'       => $employee->last_name,
            'Rufname'        => $employee->nickname,
            'Geburtsdatum'   => $employee->birth_date?->format('d.m.Y'),
        ],
        'Kontakt & Adresse' => [
            'E-Mail'          => $employee->email,
            'Telefon'         => $employee->phone,
            'Straße'          => $employee->address_street,
            'PLZ / Ort'       => trim(($employee->address_zip ?? '') . ' ' . ($employee->address_city ?? '')),
        ],
        'Bankdaten' => [
            'IBAN'            => $employee->iban ? '•••• ' . substr($employee->iban, -4) : null,
        ],
        'Notfallkontakt' => [
            'Name'            => $employee->emergency_contact_name,
            'Telefon'         => $employee->emergency_contact_phone,
        ],
        'Optionale Angaben' => [
            'Kleidergröße'        => $employee->clothing_size,
            'Schuhgröße'          => $employee->shoe_size,
            'Führerschein'        => $employee->drivers_license_class,
            'Führerschein bis'    => $employee->drivers_license_expiry?->format('d.m.Y'),
            'Bemerkungen'         => $employee->notes_employee,
        ],
        'Einstellung' => [
            'Eintrittsdatum'  => $employee->hire_date?->format('d.m.Y'),
            'Beschäftigung'   => $employee->employment_type,
            'Rolle'           => $employee->role,
            'Wochenstunden'   => $employee->weekly_hours,
            'Datenschutz'     => $employee->privacy_accepted_at ? '✓ ' . $employee->privacy_accepted_at->format('d.m.Y H:i') : '—',
        ],
    ];
@endphp

@foreach($sections as $sectionTitle => $fields)
    @php $hasContent = collect($fields)->filter()->isNotEmpty(); @endphp
    @if($hasContent)
    <div class="card" style="margin-bottom:12px">
        <div class="card-header">{{ $sectionTitle }}</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px 16px">
            @foreach($fields as $label => $value)
                @if($value !== null && $value !== '')
                    <div>
                        <div style="font-size:11px;color:var(--c-muted);text-transform:uppercase;letter-spacing:.05em">{{ $label }}</div>
                        <div style="font-size:14px;color:var(--c-text);margin-top:2px">{{ $value }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif
@endforeach

@endsection
