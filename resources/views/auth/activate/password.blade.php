<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort festlegen — Kolabri Getränke</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="{{ route('shop.index') }}" class="text-3xl font-bold text-amber-600">Kolabri</a>
        <p class="mt-2 text-gray-500 text-sm">Konto aktivieren – Schritt 3</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Passwort festlegen</h2>
            <p class="text-sm text-gray-500 mt-1">
                Ihr Code wurde bestätigt. Legen Sie jetzt ein Passwort für Ihr neues Benutzerkonto fest.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('activation.password.set') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                <input type="password" name="password" required autofocus
                       autocomplete="new-password" minlength="8"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('password') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1">Mindestens 8 Zeichen.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
                <input type="password" name="password_confirmation" required
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
            </div>

            <button type="submit"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-3 text-sm transition-colors">
                Konto aktivieren &amp; Tour starten
            </button>
        </form>
    </div>
</div>

</body>
</html>
