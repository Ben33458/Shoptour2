@extends('shop.layout')

@section('title', 'Einladung annehmen')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12">
    <div class="w-full max-w-md">

        @php
            $companyName = $invitation->parentCustomer->company_name
                ?: trim(($invitation->parentCustomer->first_name ?? '') . ' ' . ($invitation->parentCustomer->last_name ?? ''));
        @endphp

        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center text-2xl font-bold text-amber-600 mx-auto mb-4">
                {{ mb_substr($companyName, 0, 1) }}
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Einladung annehmen</h1>
            <p class="text-gray-500 text-sm">
                Sie wurden eingeladen, im Namen von <strong>{{ $companyName }}</strong> zu bestellen.
            </p>
        </div>

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4 text-sm">
                {{ session('error') }}
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

        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="mb-5 pb-4 border-b border-gray-50">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Ihr Konto</p>
                <p class="text-sm font-medium text-gray-900">{{ $invitation->first_name }} {{ $invitation->last_name }}</p>
                <p class="text-sm text-gray-500">{{ $invitation->email }}</p>
            </div>

            <form method="POST" action="{{ route('sub-users.invitation.accept', $token) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Passwort *</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <p class="text-xs text-gray-400 mt-1">Mindestens 8 Zeichen.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Passwort bestätigen *</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                </div>

                <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl py-2.5 transition-colors">
                    Konto einrichten & anmelden
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            Dieser Einladungslink ist 48 Stunden gültig.<br>
            Läuft er ab, bitte den Hauptkunden um eine neue Einladung.
        </p>

    </div>
</div>
@endsection
