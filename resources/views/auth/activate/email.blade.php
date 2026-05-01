<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kundenkonto aktivieren — Kolabri Getränke</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="{{ route('shop.index') }}" class="text-3xl font-bold text-amber-600">Kolabri</a>
        <p class="mt-2 text-gray-500 text-sm">Bestehendes Kundenkonto aktivieren</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Konto aktivieren</h2>
            <p class="text-sm text-gray-500 mt-1">
                Geben Sie die E-Mail-Adresse ein, die bei uns als Kunde hinterlegt ist.
                Wir senden Ihnen dann einen Bestätigungscode.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('activation.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Adresse</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="email"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 @error('email') border-red-400 @enderror">
            </div>

            <button type="submit"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-3 text-sm transition-colors">
                Code anfordern
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-gray-100 space-y-2 text-center">
            <p class="text-sm text-gray-500">
                Noch kein Konto?
                <a href="{{ route('register') }}" class="text-amber-600 hover:underline font-medium">Neu registrieren</a>
            </p>
            <p class="text-sm text-gray-500">
                Konto bereits vorhanden?
                <a href="{{ route('login') }}" class="text-amber-600 hover:underline font-medium">Anmelden</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
