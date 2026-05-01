@extends('shop.account.account-layout')

@section('title', 'Profil & Einstellungen')

@section('account-content')

@include('components.onboarding-banner')

<div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Profil & Einstellungen</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">

        {{-- Persönliche Daten --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Persönliche Daten</h2>
            <div class="space-y-4">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vorname</label>
                        <input type="text" name="first_name"
                               value="{{ old('first_name', $customer->first_name) }}"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nachname</label>
                        <input type="text" name="last_name"
                               value="{{ old('last_name', $customer->last_name) }}"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Firma <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="company_name"
                           value="{{ old('company_name', $customer->company_name) }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefon <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="tel" name="phone"
                           value="{{ old('phone', $customer->phone) }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Geburtsdatum <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="date" name="birth_date"
                           value="{{ old('birth_date', $customer->birth_date?->format('Y-m-d')) }}"
                           max="{{ date('Y-m-d') }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <p class="text-xs text-gray-400 mt-1">Wird für Jugendschutz-Zwecke gespeichert und nicht an Dritte weitergegeben.</p>
                </div>

            </div>
        </div>

        {{-- E-Mail-Adressen --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6" id="emails-section">
            <h2 class="font-bold text-gray-900 mb-4">E-Mail-Adressen</h2>
            <div class="space-y-4">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Haupt-E-Mail</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                           placeholder="ihre@email.de">
                    <p class="text-xs text-gray-400 mt-1">Für Bestellbestätigungen und Ihr Login.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Rechnungs-E-Mail <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="email" name="billing_email" value="{{ old('billing_email', $customer->billing_email) }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                           placeholder="buchhaltung@firma.de">
                    <p class="text-xs text-gray-400 mt-1">Rechnungen werden an diese Adresse gesendet, wenn angegeben.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Versandbenachrichtigung-E-Mail <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="email" name="notification_email" value="{{ old('notification_email', $customer->notification_email) }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                           placeholder="lager@firma.de">
                    <p class="text-xs text-gray-400 mt-1">Versandhinweise werden an diese Adresse gesendet, wenn angegeben.</p>
                </div>

            </div>
        </div>

        {{-- Versandbenachrichtigung --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Versandbenachrichtigung</h2>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="email_notification_shipping" value="1"
                       class="mt-0.5 accent-amber-500"
                       {{ old('email_notification_shipping', $customer->email_notification_shipping) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">
                    Ich möchte eine E-Mail erhalten, wenn meine Bestellung versendet wurde
                </span>
            </label>
        </div>

        {{-- Newsletter-Präferenz --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-bold text-gray-900 mb-4">E-Mail-Präferenz</h2>
            <div class="space-y-3">

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="newsletter_consent" value="all"
                           class="mt-0.5 accent-amber-500"
                           {{ old('newsletter_consent', $customer->newsletter_consent) === 'all' ? 'checked' : '' }}>
                    <div>
                        <span class="text-sm font-medium text-gray-800">Alle E-Mails</span>
                        <p class="text-xs text-gray-500">Angebote, Neuheiten und wichtige Infos</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="newsletter_consent" value="important_only"
                           class="mt-0.5 accent-amber-500"
                           {{ old('newsletter_consent', $customer->newsletter_consent) === 'important_only' ? 'checked' : '' }}>
                    <div>
                        <span class="text-sm font-medium text-gray-800">Nur wichtige Infos</span>
                        <p class="text-xs text-gray-500">Bestellungen, Rechnungen, Änderungen</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="newsletter_consent" value="none"
                           class="mt-0.5 accent-amber-500"
                           {{ old('newsletter_consent', $customer->newsletter_consent) === 'none' ? 'checked' : '' }}>
                    <div>
                        <span class="text-sm font-medium text-gray-800">Keine Marketing-E-Mails</span>
                        <p class="text-xs text-gray-500">Keine Werbung oder Newsletter</p>
                    </div>
                </label>

            </div>
        </div>

        {{-- Preisanzeige --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-bold text-gray-900 mb-1">Preisanzeige</h2>
            <p class="text-xs text-gray-400 mb-4">Legt fest, ob Preise im Shop mit oder ohne Mehrwertsteuer angezeigt werden.</p>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="price_display_mode" value="brutto"
                           class="accent-amber-500"
                           {{ old('price_display_mode', $customer->price_display_mode ?? 'brutto') === 'brutto' ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Brutto <span class="text-gray-400">(inkl. MwSt.)</span></span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="price_display_mode" value="netto"
                           class="accent-amber-500"
                           {{ old('price_display_mode', $customer->price_display_mode ?? 'brutto') === 'netto' ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Netto <span class="text-gray-400">(zzgl. MwSt.)</span></span>
                </label>
            </div>
        </div>

        <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl py-2.5 transition-colors">
            Einstellungen speichern
        </button>
    </form>

    {{-- Shop-Ansicht (separate form) --}}
    @php
        $availViews = json_decode(\App\Models\Pricing\AppSetting::get('shop.display.available_views', '["grid_large","grid_compact","list_images","list_no_images","table"]'), true) ?: ['grid_large'];
        $viewLabels = [
            'grid_large'     => 'Grid groß (Standard)',
            'grid_compact'   => 'Grid kompakt',
            'list_images'    => 'Liste mit Bildern',
            'list_no_images' => 'Textliste (ohne Bilder)',
            'table'          => 'Tabelle',
        ];
        $currentViewMode = $customer->display_preferences['view_mode'] ?? \App\Models\Pricing\AppSetting::get('shop.display.default_view', 'grid_large');
        $currentPerPage  = (int) ($customer->display_preferences['items_per_page'] ?? \App\Models\Pricing\AppSetting::get('shop.display.default_items_per_page', '24'));
    @endphp
    <form method="POST" action="{{ route('account.display_preferences.update') }}" class="mt-6">
        @csrf
        <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 space-y-4">
            <div>
                <h2 class="font-bold text-gray-900 mb-1">Shop-Ansicht</h2>
                <p class="text-xs text-gray-400 mb-4">Legt fest, wie Produkte im Shop angezeigt werden.</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Ansicht</p>
                <div class="flex flex-wrap gap-2" id="view-mode-group">
                    @foreach($availViews as $vm)
                    <label class="view-btn flex items-center gap-2 cursor-pointer border rounded-xl px-3 py-2 text-sm transition-colors
                                  {{ $currentViewMode === $vm ? 'border-amber-400 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}">
                        <input type="radio" name="view_mode" value="{{ $vm }}"
                               class="sr-only" {{ $currentViewMode === $vm ? 'checked' : '' }}>
                        <span>{{ $viewLabels[$vm] ?? $vm }}</span>
                    </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1">Die Ansicht kann auch direkt im Shop über die Schalter oben rechts geändert werden.</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Produkte pro Seite</p>
                <div class="flex gap-2" id="per-page-group">
                    @foreach([24, 48, 96] as $n)
                    <label class="perpage-btn cursor-pointer px-3 py-1.5 border rounded-xl text-sm transition-colors
                                  {{ $currentPerPage == $n ? 'border-amber-400 bg-amber-50 text-amber-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                        <input type="radio" name="items_per_page" value="{{ $n }}"
                               class="sr-only" {{ $currentPerPage == $n ? 'checked' : '' }}>
                        {{ $n }}
                    </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1">Diese Einstellung kann direkt im Shop über die Seitenumschalter geändert werden.</p>
            </div>
            <button type="submit"
                    class="bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors">
                Ansicht speichern
            </button>
        </div>
    </form>
    <script>
    (function () {
        function updateLabels(group, activeClass, inactiveClass) {
            group.querySelectorAll('label').forEach(function (lbl) {
                var radio = lbl.querySelector('input[type=radio]');
                if (radio.checked) {
                    activeClass.split(' ').forEach(function(c){ lbl.classList.add(c); });
                    inactiveClass.split(' ').forEach(function(c){ lbl.classList.remove(c); });
                } else {
                    inactiveClass.split(' ').forEach(function(c){ lbl.classList.add(c); });
                    activeClass.split(' ').forEach(function(c){ lbl.classList.remove(c); });
                }
            });
        }
        var vmGroup = document.getElementById('view-mode-group');
        var ppGroup = document.getElementById('per-page-group');
        var active   = 'border-amber-400 bg-amber-50 text-amber-700';
        var inactive = 'border-gray-200 text-gray-600';
        if (vmGroup) {
            vmGroup.addEventListener('change', function () { updateLabels(vmGroup, active, inactive); });
        }
        if (ppGroup) {
            ppGroup.addEventListener('change', function () { updateLabels(ppGroup, active, inactive + ' font-medium'); });
        }
    })();
    </script>

</div>

    {{-- Passwort ändern --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mt-6" id="passwort">
        <h2 class="font-bold text-gray-900 mb-1">Passwort ändern</h2>
        <p class="text-xs text-gray-400 mb-4">Lassen Sie die Felder leer, wenn Sie das Passwort nicht ändern möchten.</p>

        @if($errors->has('current_password'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4 text-sm">
                {{ $errors->first('current_password') }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.profile.password') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="onboarding_step" value="{{ request()->query('onboarding_step') }}">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Aktuelles Passwort *</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Neues Passwort *</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                <p class="text-xs text-gray-400 mt-1">Mindestens 8 Zeichen.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Neues Passwort bestätigen *</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>

            <button type="submit"
                    class="bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors">
                Passwort ändern
            </button>
        </form>
    </div>
</div>
@if(request()->query('onboarding_step') === 'emails')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('emails-section');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
</script>
@endif
@endsection
