@extends('shop.layout')

@section('title', 'Leihauftrag bestätigt')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16 text-center">

    <div class="text-6xl mb-6">✅</div>

    <h1 class="text-2xl font-bold text-gray-900 mb-3">
        Leihauftrag eingegangen!
    </h1>

    <p class="text-gray-600 mb-2">
        Dein Auftrag <strong class="text-gray-800">{{ $orderNumber }}</strong> wurde erfolgreich übermittelt.
    </p>

    <p class="text-gray-500 text-sm mb-8">
        Wir prüfen die Verfügbarkeit und melden uns zeitnah bei dir, um den Auftrag zu bestätigen
        und die Logistik abzustimmen.
    </p>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-800 mb-8 text-left space-y-2">
        <p class="font-semibold">Wie geht es weiter?</p>
        <ul class="list-disc list-inside space-y-1 text-blue-700">
            <li>Wir prüfen die Verfügbarkeit aller Artikel für deinen Zeitraum</li>
            <li>Du erhältst eine Auftragsbestätigung per E-Mail</li>
            <li>Liefertermin und Details werden mit dir abgestimmt</li>
            <li>Vor der Veranstaltung erhältst du die Rechnung</li>
        </ul>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="{{ route('rental.landing') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3 rounded-xl transition">
            Weitere Leihartikel buchen
        </a>
        <a href="{{ route('shop.index') }}"
            class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-8 py-3 rounded-xl transition">
            Zum Shop
        </a>
    </div>

</div>
@endsection
