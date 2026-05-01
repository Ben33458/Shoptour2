@extends('shop.account.account-layout')

@section('title', 'Meine Adressen')

@section('account-content')

@include('components.onboarding-banner')

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Adressen</h1>
        <button onclick="openAddModal()"
                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + Adresse hinzufügen
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($customer->addresses->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-4xl mb-3">📭</p>
            <p class="font-medium">Noch keine Adressen gespeichert.</p>
            <button onclick="openAddModal()" class="mt-4 text-amber-600 hover:underline text-sm">
                Jetzt erste Adresse anlegen
            </button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($customer->addresses as $addr)
                <div class="bg-white rounded-2xl border {{ $addr->is_default ? 'border-amber-300' : 'border-gray-100' }} p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            {{-- Type badge --}}
                            <span class="mt-0.5 shrink-0 inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $addr->type === 'delivery' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                {{ $addr->type === 'delivery' ? 'Lieferung' : 'Rechnung' }}
                            </span>
                            {{-- Address content --}}
                            <div>
                                @if($addr->is_default)
                                    <span class="text-xs text-amber-600 font-medium block">⭐ Standard</span>
                                @endif
                                @if($addr->company)
                                    <p class="font-medium text-sm text-gray-900">{{ $addr->company }}</p>
                                @endif
                                <p class="text-sm {{ $addr->company ? 'text-gray-500' : 'font-medium text-gray-900' }}">
                                    {{ trim(($addr->first_name ?? '') . ' ' . ($addr->last_name ?? '')) }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $addr->street }}{{ $addr->house_number ? ' ' . $addr->house_number : '' }}
                                </p>
                                <p class="text-sm text-gray-500">{{ $addr->zip }} {{ $addr->city }}</p>
                                @if($addr->phone)
                                    <p class="text-sm text-gray-400">{{ $addr->phone }}</p>
                                @endif
                                @if($addr->delivery_note)
                                    <p class="text-xs text-gray-400 mt-1 italic">{{ $addr->delivery_note }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-3 text-xs shrink-0">
                            @if(!$addr->is_default)
                                <form method="POST" action="{{ route('account.addresses.setDefault', $addr) }}">
                                    @csrf
                                    <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">
                                    <button class="text-amber-600 hover:underline">Als Standard</button>
                                </form>
                            @endif
                            <button data-addr="{{ json_encode($addr) }}"
                                    onclick="openEditModal({{ $addr->id }}, '{{ $addr->type }}', JSON.parse(this.dataset.addr))"
                                    class="text-blue-600 hover:underline">Bearbeiten</button>
                            <form method="POST" action="{{ route('account.addresses.destroy', $addr) }}" id="addr-del-{{ $addr->id }}">
                                @csrf @method('DELETE')
                                <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">
                                <button type="button"
                                        onclick="shopConfirm('Adresse löschen', 'Diese Adresse wirklich löschen?', 'Löschen').then(function(ok){ if(ok) document.getElementById('addr-del-{{ $addr->id }}').submit(); })"
                                        class="text-red-400 hover:text-red-600">Löschen</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ─── Address modal ─────────────────────────────────────────────────────── --}}
<div id="address-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 id="modal-title" class="font-bold text-gray-900 text-lg">Adresse</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="address-form" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">
                <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">

                {{-- Type selector --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Adresstyp *</label>
                    <div class="flex gap-3">
                        <label class="flex-1 flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2 cursor-pointer">
                            <input type="radio" name="type" value="delivery" id="type-delivery" class="accent-blue-500" required>
                            <span class="text-sm text-gray-700">Lieferadresse</span>
                        </label>
                        <label class="flex-1 flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2 cursor-pointer">
                            <input type="radio" name="type" value="billing" id="type-billing" class="accent-purple-500">
                            <span class="text-sm text-gray-700">Rechnungsadresse</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Firma (optional)</label>
                    <input type="text" name="company" id="addr-company"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vorname</label>
                        <input type="text" name="first_name" id="addr-first-name"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nachname</label>
                        <input type="text" name="last_name" id="addr-last-name"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Straße *</label>
                        <input type="text" name="street" id="addr-street" required
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nr.</label>
                        <input type="text" name="house_number" id="addr-house-number"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">PLZ *</label>
                        <input type="text" name="zip" id="addr-zip" required
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ort *</label>
                        <input type="text" name="city" id="addr-city" required
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefon (optional)</label>
                    <input type="text" name="phone" id="addr-phone"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Lieferhinweis (optional)</label>
                    <input type="text" name="delivery_note" id="addr-delivery-note"
                           placeholder="z.B. Bitte klingeln, Hintereingang links"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_default" id="addr-is-default" value="1" class="accent-amber-500">
                    Als Standardadresse setzen
                </label>

                <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl py-2.5 transition-colors mt-2">
                    Speichern
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openAddModal(defaultType) {
    document.getElementById('modal-title').textContent = 'Adresse hinzufügen';
    document.getElementById('address-form').action = '{{ route('account.addresses.store') }}';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('type-delivery').checked = (defaultType !== 'billing');
    document.getElementById('type-billing').checked  = (defaultType === 'billing');
    ['company','first-name','last-name','street','house-number','zip','city','phone','delivery-note'].forEach(f => {
        const el = document.getElementById('addr-' + f);
        if (el) el.value = '';
    });
    document.getElementById('addr-is-default').checked = false;
    document.getElementById('address-modal').classList.remove('hidden');
}

function openEditModal(id, type, addr) {
    document.getElementById('modal-title').textContent = 'Adresse bearbeiten';
    document.getElementById('address-form').action = '/mein-konto/adressen/' + id;
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('type-delivery').checked = (type === 'delivery');
    document.getElementById('type-billing').checked  = (type === 'billing');
    document.getElementById('addr-company').value        = addr.company || '';
    document.getElementById('addr-first-name').value    = addr.first_name || '';
    document.getElementById('addr-last-name').value     = addr.last_name || '';
    document.getElementById('addr-street').value        = addr.street || '';
    document.getElementById('addr-house-number').value  = addr.house_number || '';
    document.getElementById('addr-zip').value           = addr.zip || '';
    document.getElementById('addr-city').value          = addr.city || '';
    document.getElementById('addr-phone').value         = addr.phone || '';
    document.getElementById('addr-delivery-note').value = addr.delivery_note || '';
    document.getElementById('addr-is-default').checked  = !!addr.is_default;
    document.getElementById('address-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('address-modal').classList.add('hidden');
}

document.getElementById('address-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
@endpush
@endsection
