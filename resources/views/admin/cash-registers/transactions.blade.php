@extends('admin.layout')

@section('title', 'Buchungen – ' . $register->name)

@section('content')
<div style="margin-bottom:12px">
    <a href="{{ route('admin.cash-registers.index') }}"
       style="font-size:13px;color:var(--c-primary)">&larr; Alle Kassen</a>
</div>

<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;box-shadow:var(--shadow);overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
            <tr>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Datum</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Typ</th>
                <th style="padding:10px 16px;text-align:right;font-weight:500;color:var(--c-muted)">Betrag</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Tour</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Notiz</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
            <tr style="border-bottom:1px solid var(--c-border)">
                <td style="padding:10px 16px;color:var(--c-muted)">{{ $tx->created_at->format('d.m.Y H:i') }}</td>
                <td style="padding:10px 16px">
                    @if($tx->type === 'deposit')
                        <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534">Einnahme</span>
                    @else
                        <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b">Entnahme</span>
                    @endif
                </td>
                <td style="padding:10px 16px;text-align:right;font-weight:600;color:{{ $tx->type === 'deposit' ? 'var(--c-success)' : 'var(--c-danger)' }}">
                    {{ $tx->type === 'deposit' ? '+' : '-' }}{{ number_format($tx->amount_cents / 100, 2, ',', '.') }} €
                </td>
                <td style="padding:10px 16px;color:var(--c-muted)">{{ $tx->tour_id ? '#'.$tx->tour_id : '—' }}</td>
                <td style="padding:10px 16px;color:var(--c-text)">{{ $tx->note ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--c-muted)">Keine Buchungen.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px">{{ $transactions->links() }}</div>
@endsection
