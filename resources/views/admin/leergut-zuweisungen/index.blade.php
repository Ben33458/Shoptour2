@extends('admin.layout')
@section('title', 'Leergut-Zuweisungen')

@section('actions')
    <form method="POST" action="{{ route('admin.leergut-zuweisungen.auto-assign') }}"
          onsubmit="return confirm('Alle Produkte automatisch zuweisen? Bestehende Zuweisungen werden überschrieben.')">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">⚡ Alle automatisch zuweisen</button>
    </form>
@endsection

@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <p style="margin-bottom:1.5rem;color:var(--c-muted);font-size:.875rem">
        Hier kannst du jedem Produkt einen Leergut-Typ zuweisen. Der Kunde sieht diese Auswahl im Warenkorb und kann die Rückgabe-Menge anpassen.
        <br>Automatische Zuweisung: Pfand 5,10&nbsp;€ → Leergut Art.-Nr. <strong>1510</strong> (führende 1 + Pfand in Cent).
    </p>

    @if($leergutTypes->isEmpty())
        <div class="alert alert-danger">
            Keine Leergut-Artikel in WaWi gefunden (wawi_artikel WHERE cName LIKE 'Leergut%').
            Bitte zuerst den WaWi-Artikel-Sync ausführen.
        </div>
    @else

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:100px">Art.-Nr.</th>
                    <th>Produkt</th>
                    <th style="width:100px">Pfand</th>
                    <th style="width:280px">Leergut-Typ</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $currentArtNr = $product->leergut?->leergut_art_nr;
                        $pfandMilli   = 0;
                        // Show pfand info from leergut record if assigned, else from gebinde
                        if ($product->leergut) {
                            $pfandMilli = $product->leergut->unit_price_gross_milli;
                        }
                    @endphp
                    <tr>
                        <td><code style="font-size:.8rem">{{ $product->artikelnummer }}</code></td>
                        <td>{{ $product->produktname }}</td>
                        <td>
                            @if($currentArtNr)
                                <span class="badge badge-success" style="font-size:.75rem">
                                    {{ milli_to_eur($pfandMilli) }}
                                </span>
                            @else
                                <span style="color:var(--c-muted);font-size:.8rem">–</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('admin.leergut-zuweisungen.update', $product) }}"
                                  style="display:flex;gap:.4rem;align-items:center">
                                @csrf
                                <select name="leergut_art_nr"
                                        style="flex:1;font-size:.8rem;padding:.25rem .4rem">
                                    <option value="">– kein Leergut –</option>
                                    @foreach($leergutTypes as $type)
                                        <option value="{{ $type->cArtNr }}"
                                            @selected($currentArtNr === $type->cArtNr)>
                                            {{ $type->cName }}
                                            (−{{ number_format($type->fVKNetto * 1.19, 2, ',', '.') }}&nbsp;€)
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm"
                                        style="white-space:nowrap;padding:.25rem .6rem">
                                    Speichern
                                </button>
                            </form>
                        </td>
                        <td>
                            @if($currentArtNr)
                                <form method="POST"
                                      action="{{ route('admin.leergut-zuweisungen.update', $product) }}"
                                      onsubmit="return confirm('Zuweisung entfernen?')">
                                    @csrf
                                    <input type="hidden" name="leergut_art_nr" value="">
                                    <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="color:var(--c-muted);text-align:center">
                            Keine Produkte mit Pfand-Zuweisung gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $products->links() }}
    </div>

    @endif
</div>
@endsection
