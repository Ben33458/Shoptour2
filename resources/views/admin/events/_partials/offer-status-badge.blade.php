@php
    $colors = [
        'requested'               => 'background:#94a3b8;color:#fff',
        'needs_clarification'     => 'background:#f59e0b;color:#fff',
        'draft'                   => 'background:#cbd5e1;color:#475569',
        'external_offer_created'  => 'background:#64748b;color:#fff',
        'sent'                    => 'background:#3b82f6;color:#fff',
        'revision_requested'      => 'background:#f97316;color:#fff',
        'accepted'                => 'background:#22c55e;color:#fff',
        'rejected'                => 'background:#ef4444;color:#fff',
        'expired'                 => 'background:#94a3b8;color:#fff',
        'cancelled'               => 'background:#94a3b8;color:#fff',
        'converted_to_order'      => 'background:#8b5cf6;color:#fff',
    ];
    $labels = [
        'requested'               => 'Anfrage',
        'needs_clarification'     => 'Klärung nötig',
        'draft'                   => 'Entwurf',
        'external_offer_created'  => 'Ext. Angebot',
        'sent'                    => 'Versendet',
        'revision_requested'      => 'Überarbeitung',
        'accepted'                => 'Angenommen',
        'rejected'                => 'Abgelehnt',
        'expired'                 => 'Abgelaufen',
        'cancelled'               => 'Storniert',
        'converted_to_order'      => 'In Bestellung',
    ];
    $style = $colors[$status] ?? 'background:#e2e8f0;color:#475569';
    $label = $labels[$status] ?? $status;
@endphp
<span style="{{ $style }};border-radius:10px;padding:2px 8px;font-size:.75rem;font-weight:600;white-space:nowrap;">
    {{ $label }}
</span>
