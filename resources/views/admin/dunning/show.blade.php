@extends('admin.layout')

@section('title', 'Mahnlauf #' . $run->id)

@section('actions')
    <a href="{{ route('admin.dunning.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    @if($run->isDraft())
        <form method="POST" action="{{ route('admin.dunning.cancel', $run) }}" style="display:inline"
              onsubmit="return confirm('Mahnlauf #{{ $run->id }} wirklich stornieren?')">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#dc2626">Stornieren</button>
        </form>
        <form method="POST" action="{{ route('admin.dunning.execute', $run) }}" style="display:inline"
              onsubmit="return confirm('Mahnlauf jetzt ausführen? E-Mails werden versendet.')">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                @if($run->is_test_mode) [TEST] @endif
                Mahnlauf ausführen
            </button>
        </form>
    @endif
    @if($run->isSent() && $run->is_test_mode)
        <form method="POST" action="{{ route('admin.dunning.reset', $run) }}" style="display:inline"
              onsubmit="return confirm('Testlauf zurücksetzen? Alle Positionen werden auf „Ausstehend" gesetzt.')">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Testlauf zurücksetzen</button>
        </form>
    @endif
@endsection

@section('content')

@if(session('success'))
<div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #ef4444;border-radius:6px;color:#991b1b">
    {{ session('error') }}
</div>
@endif

{{-- Meta-Info --}}
<div class="card" style="margin-bottom:16px;padding:16px 20px">
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px">
        <div>
            <div class="hint">Status</div>
            <div>
                @if($run->isDraft()) <span class="badge badge-pending">Entwurf</span>
                @elseif($run->isSent()) <span class="badge badge-delivered">Versendet</span>
                @else <span class="badge badge-cancelled">Abgebrochen</span>
                @endif
            </div>
        </div>
        <div>
            <div class="hint">Erstellt</div>
            <div>{{ $run->created_at->format('d.m.Y H:i') }}</div>
        </div>
        <div>
            <div class="hint">Erstellt von</div>
            <div>{{ $run->createdBy?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Versendet</div>
            <div>{{ $run->sent_at?->format('d.m.Y H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="hint">Modus</div>
            <div>
                @if($run->is_test_mode)
                    <span class="badge" style="background:#fef3c7;color:#92400e">Testmodus</span>
                @else
                    Produktiv
                @endif
            </div>
        </div>
    </div>
    @if($run->notes)
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb">
            <div class="hint">Notiz</div>
            <div>{{ $run->notes }}</div>
        </div>
    @endif
</div>

{{-- Positionen --}}
<div class="card">
    <div class="card-header">
        Positionen ({{ $run->items->count() }} total —
        {{ $run->sentCount() }} versendet,
        {{ $run->failedCount() }} fehlgeschlagen,
        {{ $run->items->where('status','skipped')->count() }} übersprungen)
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th>Kanal</th>
                    <th>Stufe</th>
                    <th class="text-right">Offen</th>
                    <th class="text-right">Zinsen</th>
                    <th>Empfänger</th>
                    <th>Status</th>
                    <th>Fehler</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($run->items as $item)
                <tr @if($item->status === 'failed') style="background:#fef2f2" @endif>
                    <td>
                        <a href="{{ route('admin.debtor.show', $item->customer) }}">
                            {{ $item->customer->displayName() }}
                        </a>
                        <div class="text-muted" style="font-size:11px">{{ $item->customer->customer_number }}</div>
                    </td>
                    <td>
                        @if($item->channel === 'post')
                            <span class="badge" style="background:#dbeafe;color:#1e40af">Briefpost</span>
                        @else
                            <span class="badge" style="background:#d1fae5;color:#065f46">E-Mail</span>
                        @endif
                    </td>
                    <td>Stufe {{ $item->dunning_level }}</td>
                    <td class="text-right" style="color:#dc2626;font-weight:600">
                        {{ number_format($item->total_open_milli / 1_000_000, 2, ',', '.') }} €
                    </td>
                    <td class="text-right">
                        @if($item->interest_milli > 0)
                            {{ number_format($item->interest_milli / 1_000_000, 2, ',', '.') }} €
                            @if(!empty($item->interest_breakdown))
                                <details style="font-size:11px;text-align:left;margin-top:2px">
                                    <summary class="text-muted" style="cursor:pointer">Aufschlüsselung</summary>
                                    <table style="width:100%;margin-top:4px;border-collapse:collapse">
                                        <tr style="color:var(--c-muted)">
                                            <th style="font-weight:normal;padding:1px 4px">Beleg</th>
                                            <th style="font-weight:normal;padding:1px 4px">Fällig</th>
                                            <th style="font-weight:normal;padding:1px 4px">Tage</th>
                                            <th style="font-weight:normal;padding:1px 4px">Zinssatz</th>
                                            <th style="font-weight:normal;padding:1px 4px;text-align:right">Zinsen</th>
                                        </tr>
                                        @foreach($item->interest_breakdown as $b)
                                        <tr>
                                            <td style="padding:1px 4px">{{ $b['voucher_number'] }}</td>
                                            <td style="padding:1px 4px">{{ $b['due_date'] }}</td>
                                            <td style="padding:1px 4px">{{ $b['days_overdue'] }}</td>
                                            <td style="padding:1px 4px">{{ $b['annual_rate_pct'] }}</td>
                                            <td style="padding:1px 4px;text-align:right;color:#dc2626">
                                                {{ number_format($b['interest_milli']/1_000_000,2,',','.') }} €
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </details>
                            @endif
                        @elseif($item->dunning_level === 1)
                            <span class="text-muted" title="Keine Zinsen bei Stufe 1 (Zahlungserinnerung)">—</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-size:12px">{{ $item->recipient_email ?? '—' }}</td>
                    <td>
                        @php
                            $statusBadge = match($item->status) {
                                'sent' => ['badge-delivered', 'Versendet'],
                                'failed' => ['badge-cancelled', 'Fehlgeschlagen'],
                                'skipped' => ['', 'Übersprungen'],
                                default => ['badge-pending', 'Ausstehend'],
                            };
                        @endphp
                        <span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                    </td>
                    <td style="font-size:11px;color:#dc2626;max-width:200px">
                        {{ $item->error_message ?? '' }}
                    </td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <a href="{{ route('admin.dunning.pdf', [$run, $item]) }}"
                               class="btn btn-outline btn-sm">PDF</a>
                            @if($run->isDraft() && $item->status === 'pending')
                                <form method="POST" action="{{ route('admin.dunning.skip', [$run, $item]) }}">
                                    @csrf @method('POST')
                                    <button type="submit" class="btn btn-outline btn-sm">Überspringen</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
