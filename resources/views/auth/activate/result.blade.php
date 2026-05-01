<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konto aktivieren — Kolabri Getränke</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="{{ route('shop.index') }}" class="text-3xl font-bold text-amber-600">Kolabri</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">

        @if($case === 'B')
            <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mb-3">Manuelle Prüfung erforderlich</h2>
            <p class="text-sm text-gray-600">
                Zu dieser E-Mail-Adresse wurden mehrere Kundenkonten gefunden. Eine automatische Aktivierung ist daher nicht möglich.
            </p>
            <p class="text-sm text-gray-600 mt-3">
                Wir haben unser Team informiert und melden uns zeitnah bei Ihnen.
                Alternativ können Sie uns direkt kontaktieren:
            </p>

        @elseif($case === 'C')
            <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mb-3">Benutzerkonto bereits vorhanden</h2>
            <p class="text-sm text-gray-600">
                Zu dieser E-Mail-Adresse existiert bereits ein Benutzerkonto bei Kolabri Getränke.
                Eine erneute Aktivierung ist nicht möglich.
            </p>
            <div class="mt-5 space-y-2">
                <a href="{{ route('login') }}"
                   class="block w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl px-4 py-3 text-sm transition-colors">
                    Zum Login
                </a>
                <a href="{{ route('password.request') }}"
                   class="block w-full border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-xl px-4 py-3 text-sm transition-colors">
                    Passwort vergessen?
                </a>
            </div>

        @elseif(in_array($case, ['D', 'E']))
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 0 1 5.656 0M9 10h.01M15 10h.01M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mb-3">Keine automatische Aktivierung möglich</h2>
            <p class="text-sm text-gray-600">
                Zu dieser E-Mail-Adresse wurde kein aktivierbares Kundenkonto gefunden.
                Möglicherweise ist bei uns eine andere E-Mail-Adresse hinterlegt.
            </p>
            <p class="text-sm text-gray-600 mt-3">
                Bitte wenden Sie sich direkt an uns:
            </p>

        @elseif(in_array($case, ['blocked_email', 'blocked_ip']))
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 0 1 5.636 5.636m12.728 12.728A9 9 0 0 0 5.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mb-3">Zu viele Anfragen</h2>
            <p class="text-sm text-gray-600">{{ $message ?? 'Bitte versuchen Sie es später erneut.' }}</p>
        @endif

        @if(in_array($case, ['B', 'D', 'E']))
            <div class="mt-6 bg-gray-50 rounded-xl p-4 text-sm text-gray-600 space-y-1">
                <p><strong>E-Mail:</strong> <a href="mailto:getraenke@kolabri.de" class="text-amber-600">getraenke@kolabri.de</a></p>
                <p><strong>Telefon:</strong> persönlich oder telefonisch erreichbar</p>
            </div>
        @endif

        @if(! in_array($case, ['C']))
            <div class="mt-6">
                <a href="{{ route('activation.show') }}" class="text-sm text-amber-600 hover:underline">
                    ← Andere E-Mail-Adresse eingeben
                </a>
            </div>
        @endif

    </div>
</div>

</body>
</html>
