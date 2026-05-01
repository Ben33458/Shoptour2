@extends('shop.account.account-layout')

@section('title', 'Unterbenutzer')

@section('account-content')

@include('components.onboarding-banner')

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Unterbenutzer</h1>
        <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + Einladen
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <p class="text-sm text-gray-500 mb-6">
        Unterbenutzer können im Namen Ihres Unternehmens bestellen. Sie erhalten einen eigenen Login und haben nur die Rechte, die Sie ihnen vergeben.
    </p>

    @if($customer->subUsers->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-4xl mb-3">👥</p>
            <p class="font-medium">Noch keine Unterbenutzer.</p>
            <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                    class="mt-4 text-amber-600 hover:underline text-sm">
                Jetzt ersten Unterbenutzer einladen
            </button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($customer->subUsers as $sub)
                @php $perms = $sub->permissions; @endphp
                <div class="bg-white rounded-2xl border {{ $sub->active ? 'border-gray-100' : 'border-red-100' }} p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-medium text-sm text-gray-900">{{ $sub->user->name }}</p>
                                @if(!$sub->active)
                                    <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full">Deaktiviert</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 mb-2">{{ $sub->user->email }}</p>
                            {{-- Permissions badges --}}
                            <div class="flex flex-wrap gap-1.5">
                                <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">
                                    Bestellen ({{ $perms['order_history'] === 'all' ? 'alle Bestellungen' : 'eigene' }})
                                </span>
                                @if($perms['invoices'] ?? false)
                                    <span class="text-xs bg-purple-50 text-purple-600 px-2 py-0.5 rounded-full">Rechnungen</span>
                                @endif
                                @if($perms['addresses'] ?? false)
                                    <span class="text-xs bg-green-50 text-green-600 px-2 py-0.5 rounded-full">Adressen</span>
                                @endif
                                @if($perms['assortment'] ?? false)
                                    <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full">Stammsortiment</span>
                                @endif
                                @if($perms['bestellen_all'] ?? false)
                                    <span class="text-xs bg-teal-50 text-teal-600 px-2 py-0.5 rounded-full">Bestellen (Shop)</span>
                                @elseif($perms['bestellen_favoritenliste'] ?? false)
                                    <span class="text-xs bg-teal-50 text-teal-600 px-2 py-0.5 rounded-full">Bestellen (Sortiment)</span>
                                @endif
                                @if($perms['preise_sehen'] ?? false)
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">Preise sehen</span>
                                @endif
                                @if($perms['sub_users'] ?? false)
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">Unterbenutzer verwalten</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-xs shrink-0">
                            <button onclick="openPermModal({{ $sub->id }}, {{ json_encode($sub->permissions) }})"
                                    class="text-blue-600 hover:underline">Rechte</button>

                            <form method="POST" action="{{ route('account.sub-users.toggle', $sub) }}">
                                @csrf
                                <button class="{{ $sub->active ? 'text-amber-500 hover:text-amber-700' : 'text-green-600 hover:text-green-800' }}">
                                    {{ $sub->active ? 'Sperren' : 'Entsperren' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('account.sub-users.destroy', $sub) }}" id="sub-del-{{ $sub->id }}">
                                @csrf @method('DELETE')
                                <button type="button"
                                        onclick="shopConfirm('Unterbenutzer entfernen', '{{ addslashes($sub->name ?? 'Diesen Benutzer') }} wirklich entfernen? Der Zugang wird dauerhaft gelöscht.', 'Entfernen').then(function(ok){ if(ok) document.getElementById('sub-del-{{ $sub->id }}').submit(); })"
                                        class="text-red-400 hover:text-red-600">Entfernen</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ─── Invite Modal ───────────────────────────────────────────────────────── --}}
<div id="invite-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-bold text-gray-900 text-lg">Unterbenutzer einladen</h3>
                <button onclick="document.getElementById('invite-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('account.sub-users.invite') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vorname *</label>
                        <input type="text" name="first_name" required
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nachname *</label>
                        <input type="text" name="last_name" required
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-Mail *</label>
                    <input type="email" name="email" required
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Berechtigungen</p>
                    <div class="space-y-2.5 bg-gray-50 rounded-xl p-3">

                        <div>
                            <p class="text-xs text-gray-500 mb-1">Bestellhistorie einsehen</p>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                    <input type="radio" name="permissions[order_history]" value="own" checked class="accent-amber-500">
                                    <span class="text-xs">Eigene Bestellungen</span>
                                </label>
                                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                    <input type="radio" name="permissions[order_history]" value="all" class="accent-amber-500">
                                    <span class="text-xs">Alle Firmenbestellungen</span>
                                </label>
                            </div>
                        </div>

                        @foreach([
                            'invoices'                 => 'Rechnungen einsehen',
                            'addresses'                => 'Adressen verwalten',
                            'assortment'               => 'Stammsortiment sehen',
                            'sub_users'                => 'Unterbenutzer verwalten',
                            'bestellen_all'            => 'Im Shop bestellen (alle Produkte)',
                            'bestellen_favoritenliste' => 'Aus Stammsortiment bestellen',
                            'sollbestaende_bearbeiten' => 'Sollbestände bearbeiten',
                            'preise_sehen'             => 'Preise anzeigen',
                        ] as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="permissions[{{ $key }}]" value="1"
                                       {{ $key === 'bestellen_favoritenliste' ? 'checked' : '' }}
                                       class="accent-amber-500">
                                <span class="text-xs text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach

                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl py-2.5 transition-colors">
                    Einladung senden
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ─── Permissions Edit Modal ─────────────────────────────────────────────── --}}
<div id="perm-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-bold text-gray-900 text-lg">Berechtigungen bearbeiten</h3>
                <button onclick="document.getElementById('perm-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="perm-form" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-2.5 bg-gray-50 rounded-xl p-3">

                    <div>
                        <p class="text-xs text-gray-500 mb-1">Bestellhistorie einsehen</p>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="permissions[order_history]" id="perm-history-own" value="own" class="accent-amber-500">
                                <span class="text-xs">Eigene Bestellungen</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="permissions[order_history]" id="perm-history-all" value="all" class="accent-amber-500">
                                <span class="text-xs">Alle Firmenbestellungen</span>
                            </label>
                        </div>
                    </div>

                    @foreach([
                        'invoices'                 => ['id' => 'perm-invoices',    'label' => 'Rechnungen einsehen'],
                        'addresses'                => ['id' => 'perm-addresses',   'label' => 'Adressen verwalten'],
                        'assortment'               => ['id' => 'perm-assortment',  'label' => 'Stammsortiment sehen'],
                        'sub_users'                => ['id' => 'perm-sub-users',   'label' => 'Unterbenutzer verwalten'],
                        'bestellen_all'            => ['id' => 'perm-order-all',   'label' => 'Im Shop bestellen (alle Produkte)'],
                        'bestellen_favoritenliste' => ['id' => 'perm-order-fav',   'label' => 'Aus Stammsortiment bestellen'],
                        'sollbestaende_bearbeiten' => ['id' => 'perm-soll',        'label' => 'Sollbestände bearbeiten'],
                        'preise_sehen'             => ['id' => 'perm-prices',      'label' => 'Preise anzeigen'],
                    ] as $key => $meta)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="permissions[{{ $key }}]" id="{{ $meta['id'] }}" value="1" class="accent-amber-500">
                            <span class="text-xs text-gray-700">{{ $meta['label'] }}</span>
                        </label>
                    @endforeach

                </div>

                <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl py-2.5 transition-colors">
                    Speichern
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openPermModal(subUserId, perms) {
    document.getElementById('perm-form').action = '/mein-konto/unterbenutzer/' + subUserId + '/rechte';
    document.getElementById('perm-history-own').checked = (perms.order_history !== 'all');
    document.getElementById('perm-history-all').checked = (perms.order_history === 'all');
    document.getElementById('perm-invoices').checked    = !!perms.invoices;
    document.getElementById('perm-addresses').checked   = !!perms.addresses;
    document.getElementById('perm-assortment').checked  = !!perms.assortment;
    document.getElementById('perm-sub-users').checked   = !!perms.sub_users;
    document.getElementById('perm-order-all').checked   = !!perms.bestellen_all;
    document.getElementById('perm-order-fav').checked   = !!perms.bestellen_favoritenliste;
    document.getElementById('perm-soll').checked        = !!perms.sollbestaende_bearbeiten;
    document.getElementById('perm-prices').checked      = !!perms.preise_sehen;
    document.getElementById('perm-modal').classList.remove('hidden');
}
</script>
@endpush
@endsection
