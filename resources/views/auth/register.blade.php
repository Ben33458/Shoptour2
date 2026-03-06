<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrieren — Kolabri Getränke</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="{{ route('shop.index') }}" class="text-3xl font-bold text-amber-600">Kolabri</a>
        <p class="mt-2 text-gray-500 text-sm">Konto erstellen</p>
    </div>

    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-4 py-6 sm:p-8">

        {{-- Google OAuth --}}
        <a href="{{ route('auth.google') }}"
           class="flex items-center justify-center gap-3 w-full border border-gray-300 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors mb-6">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Mit Google registrieren / anmelden
        </a>

        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
            <div class="relative flex justify-center text-xs text-gray-400"><span class="bg-white px-3">oder</span></div>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('first_name') border-red-400 @enderror">
                    @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('last_name') border-red-400 @enderror">
                    @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Firmenname <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="company_name" value="{{ old('company_name') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('email') border-red-400 @enderror">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('password') border-red-400 @enderror">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>

            {{-- Lieferadresse --}}
            <div class="pt-2">
                <p class="text-sm font-semibold text-gray-700 mb-3">Lieferadresse</p>

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_6rem] gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Straße</label>
                        <input type="text" name="address[street]" value="{{ old('address.street') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('address.street') border-red-400 @enderror">
                        @error('address.street') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nr. <span class="text-red-500">*</span></label>
                        <input type="text" name="address[house_number]" value="{{ old('address.house_number') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('address.house_number') border-red-400 @enderror">
                        @error('address.house_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[7rem_1fr] gap-3 mt-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                        <input type="text" name="address[zip]" value="{{ old('address.zip') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('address.zip') border-red-400 @enderror">
                        @error('address.zip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                        <input type="text" name="address[city]" value="{{ old('address.city') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('address.city') border-red-400 @enderror">
                        @error('address.city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="tel" name="address[phone]" value="{{ old('address.phone') }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-3 text-sm transition-colors mt-2">
                Konto erstellen
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            Bereits ein Konto?
            <a href="{{ route('login') }}" class="text-amber-600 hover:underline font-medium">Anmelden</a>
        </p>
    </div>
</div>

</body>
</html>
