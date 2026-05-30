@php
    $colors = [
        'planned'                 => 'background:#cbd5e1;color:#475569',
        'confirmed'               => 'background:#3b82f6;color:#fff',
        'delivery_planned'        => 'background:#60a5fa;color:#fff',
        'partially_delivered'     => 'background:#f59e0b;color:#fff',
        'delivered'               => 'background:#22c55e;color:#fff',
        'during_event'            => 'background:#10b981;color:#fff',
        'pickup_planned'          => 'background:#f97316;color:#fff',
        'picked_up'               => 'background:#84cc16;color:#fff',
        'post_calculation_open'   => 'background:#8b5cf6;color:#fff',
        'closed'                  => 'background:#6b7280;color:#fff',
    ];
    $labels = [
        'planned'                 => 'Geplant',
        'confirmed'               => 'Bestätigt',
        'delivery_planned'        => 'Lieferung geplant',
        'partially_delivered'     => 'Teilgeliefert',
        'delivered'               => 'Geliefert',
        'during_event'            => 'Laufend',
        'pickup_planned'          => 'Abholung geplant',
        'picked_up'               => 'Abgeholt',
        'post_calculation_open'   => 'Nachkalk. offen',
        'closed'                  => 'Abgeschlossen',
    ];
    $style = $colors[$status] ?? 'background:#e2e8f0;color:#475569';
    $label = $labels[$status] ?? $status;
@endphp
<span style="{{ $style }};border-radius:10px;padding:2px 8px;font-size:.75rem;font-weight:600;white-space:nowrap;">
    {{ $label }}
</span>
