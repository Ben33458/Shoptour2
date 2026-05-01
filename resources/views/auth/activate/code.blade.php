<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestätigungscode eingeben — Kolabri Getränke</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="{{ route('shop.index') }}" class="text-3xl font-bold text-amber-600">Kolabri</a>
        <p class="mt-2 text-gray-500 text-sm">Konto aktivieren – Schritt 2</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Bestätigungscode eingeben</h2>
            <p class="text-sm text-gray-500 mt-1">
                Wir haben einen 6-stelligen Code an
                <span class="font-medium text-gray-700">{{ $email }}</span>
                gesendet. Der Code ist 15 Minuten gültig.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('activation.code.verify') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">6-stelliger Code</label>
                <input type="text" name="code" value="{{ old('code') }}" required autofocus
                       inputmode="numeric" maxlength="6" pattern="\d{6}"
                       autocomplete="one-time-code"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-center tracking-widest text-lg font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 @error('code') border-red-400 @enderror"
                       placeholder="000000">
            </div>

            <button type="submit"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-3 text-sm transition-colors">
                Code bestätigen
            </button>
        </form>

        <div class="mt-4 text-center">
            @if($cooldownLeft > 0)
                <p class="text-sm text-gray-400" id="cooldown-msg">
                    Neuen Code anfordern möglich in <span id="countdown">{{ $cooldownLeft }}</span> s
                </p>
                <form method="POST" action="{{ route('activation.code.resend') }}" id="resend-form" class="hidden">
                    @csrf
                    <button type="submit" class="text-sm text-amber-600 hover:underline">
                        Code erneut senden
                    </button>
                </form>
                <script>
                    let left = {{ $cooldownLeft }};
                    const countdown = document.getElementById('countdown');
                    const msg = document.getElementById('cooldown-msg');
                    const form = document.getElementById('resend-form');
                    const interval = setInterval(() => {
                        left--;
                        if (left <= 0) {
                            clearInterval(interval);
                            msg.classList.add('hidden');
                            form.classList.remove('hidden');
                        } else {
                            countdown.textContent = left;
                        }
                    }, 1000);
                </script>
            @else
                <form method="POST" action="{{ route('activation.code.resend') }}">
                    @csrf
                    <button type="submit" class="text-sm text-amber-600 hover:underline">
                        Code erneut senden
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-6 pt-4 border-t border-gray-100 text-center">
            <a href="{{ route('activation.show') }}" class="text-sm text-gray-400 hover:text-gray-600">
                ← Andere E-Mail-Adresse eingeben
            </a>
        </div>
    </div>
</div>

</body>
</html>
