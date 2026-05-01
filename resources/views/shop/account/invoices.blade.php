@extends('shop.account.account-layout')

@section('title', 'Meine Rechnungen')

@section('account-content')

@include('components.onboarding-banner')

<div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Rechnungen & Zahlungen</h1>

    @if(! ($customer->display_preferences['onboarding_completed'] ?? false))
    <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 mb-6 flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-amber-800">Konto-Einrichtung abschließen</p>
            <p class="text-xs text-amber-600 mt-0.5">Du hast alle Schritte durchlaufen. Klicke hier um die Einrichtung abzuschließen.</p>
        </div>
        <form method="POST" action="{{ route('onboarding.complete') }}">
            @csrf
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl px-4 py-2 text-sm transition-colors whitespace-nowrap">
                Einrichtung abschließen ✓
            </button>
        </form>
    </div>
    @endif

    {{-- Saldo card --}}
    <div class="rounded-2xl border p-5 mb-8 flex items-center justify-between
        {{ $saldo > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200' }}">
        <div>
            <p class="text-sm font-medium {{ $saldo > 0 ? 'text-red-700' : 'text-green-700' }}">Offener Saldo</p>
            <p class="text-xs text-gray-400 mt-0.5">Summe aller offenen und überfälligen Rechnungen</p>
        </div>
        <p class="text-2xl font-bold {{ $saldo > 0 ? 'text-red-600' : 'text-green-600' }}">
            {{ number_format($saldo / 1_000_000, 2, ',', '.') }} €
        </p>
    </div>

    {{-- Voucher list --}}
    @forelse($vouchers as $voucher)
        @php
            $voucherPayments = $payments[$voucher->lexoffice_voucher_id] ?? collect();
            $localInvoice    = $localInvoices[$voucher->lexoffice_voucher_id] ?? null;

            $typeLabel = match($voucher->voucher_type) {
                'salesinvoice'        => 'Rechnung',
                'creditnote'          => 'Gutschrift',
                'downpaymentinvoice'  => 'Anzahlungsrechnung',
                default               => $voucher->voucher_type,
            };
            $statusLabel = match($voucher->voucher_status) {
                'open'    => ['label' => 'Offen',      'class' => 'bg-yellow-100 text-yellow-800'],
                'overdue' => ['label' => 'Überfällig', 'class' => 'bg-red-100 text-red-700'],
                'paid'    => ['label' => 'Bezahlt',    'class' => 'bg-green-100 text-green-700'],
                'paidoff' => ['label' => 'Ausgeglichen','class' => 'bg-green-100 text-green-700'],
                default   => ['label' => $voucher->voucher_status, 'class' => 'bg-gray-100 text-gray-600'],
            };
        @endphp

        <div class="bg-white border border-gray-100 rounded-2xl mb-4 overflow-hidden">

            {{-- Voucher header --}}
            <div class="px-5 py-4 flex flex-wrap items-start gap-3 justify-between">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $typeLabel }}</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ $voucher->voucher_number ?? ('Beleg ' . substr($voucher->lexoffice_voucher_id, 0, 8)) }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusLabel['class'] }}">
                            {{ $statusLabel['label'] }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $voucher->voucher_date?->format('d.m.Y') ?? '–' }}
                        @if($voucher->due_date && $voucher->isOpen())
                            &middot; Fällig: {{ $voucher->due_date->format('d.m.Y') }}
                        @endif
                    </p>
                </div>

                <div class="text-right flex flex-col items-end gap-1.5">
                    <p class="text-base font-bold text-gray-900">{{ $voucher->formattedTotal() }}</p>
                    @if($voucher->isOpen() && $voucher->open_amount > 0)
                        <p class="text-xs text-gray-400">Offen: {{ $voucher->formattedOpen() }}</p>
                    @endif
                    @if($voucher->lexoffice_voucher_id)
                        <a href="{{ route('account.voucher.download', $voucher) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-700">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            PDF herunterladen
                        </a>
                    @endif
                </div>
            </div>

            {{-- Linked local invoice --}}
            @if($localInvoice)
                <div class="px-5 py-2 bg-amber-50 border-t border-amber-100 flex items-center justify-between text-sm">
                    <span class="text-amber-700 font-medium">
                        Rechnungsnr. {{ $localInvoice->invoice_number }}
                    </span>
                    @if($localInvoice->pdf_path)
                        <a href="{{ route('account.invoice.download', $localInvoice) }}"
                           class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-700 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            PDF herunterladen
                        </a>
                    @endif
                </div>
            @endif

            {{-- Payments --}}
            @if($voucherPayments->isNotEmpty())
                <div class="border-t border-gray-50 px-5 py-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Zahlungen</p>
                    <div class="divide-y divide-gray-50">
                        @foreach($voucherPayments as $payment)
                            @php
                                $rawItems = $payment->raw_json['paymentItems'] ?? [];
                            @endphp
                            @foreach($rawItems as $item)
                                <div class="flex items-center justify-between py-1.5 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400 text-xs">
                                            {{ isset($item['postingDate']) ? \Carbon\Carbon::parse($item['postingDate'])->format('d.m.Y') : '–' }}
                                        </span>
                                        <span class="text-gray-500 text-xs">
                                            {{ match($item['paymentItemType'] ?? '') {
                                                'partPaymentFinancialTransaction' => 'Zahlung',
                                                'creditNote'     => 'Gutschrift',
                                                'bankTransaction'=> 'Banküberweisung',
                                                default          => $item['paymentItemType'] ?? 'Zahlung',
                                            } }}
                                        </span>
                                    </div>
                                    <span class="font-medium text-green-700">
                                        {{ number_format(($item['amount'] ?? 0), 2, ',', '.') }} {{ $item['currency'] ?? 'EUR' }}
                                    </span>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    @empty
        <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center text-gray-400">
            <p class="text-4xl mb-3">🧾</p>
            <p>Keine Rechnungen vorhanden.</p>
        </div>
    @endforelse

    @if($vouchers->hasPages())
    <div class="mt-6">
        {{ $vouchers->links() }}
    </div>
    @endif

</div>
@endsection
