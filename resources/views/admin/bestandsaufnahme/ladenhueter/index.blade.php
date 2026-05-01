@extends('admin.layout')

@section('title', 'Ladenhüter')

@section('actions')
    <a href="{{ route('admin.ladenhueter.regeln') }}" class="btn btn-secondary">Regeln & MHD-Konfiguration</a>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($regel)
<div style="padding:10px 14px;background:#fff3cd;border-radius:6px;margin-bottom:16px;font-size:13px">
    Aktuelle Regel: Kein Verkauf seit <strong>{{ $regel->tage_ohne_verkauf }}</strong> Tagen
    | Lagerdauer > <strong>{{ $regel->max_lagerdauer_tage }}</strong> Tagen
    | Bestandsreichweite > <strong>{{ $regel->max_bestandsreichweite_tage }}</strong> Tagen
    <a href="{{ route('admin.ladenhueter.regeln') }}" style="margin-left:8px;font-size:12px">Anpassen →</a>
</div>
@endif

@if($ladenhueter->isEmpty())
    <p class="text-muted">Keine Ladenhüter gefunden. Gut!</p>
@else
<table class="table">
    <thead>
        <tr>
            <th>Artikel</th>
            <th>Lager</th>
            <th>Bestand</th>
            <th>Grund</th>
            <th>Aktion</th>
        </tr>
    </thead>
    <tbody>
        @foreach($ladenhueter as $item)
        <tr>
            <td>
                <small>{{ $item['product']->artikelnummer }}</small><br>
                {{ $item['product']->produktname }}
            </td>
            <td>{{ $item['warehouse']->name }}</td>
            <td>{{ number_format($item['bestand'], 2, ',', '.') }}</td>
            <td><span class="badge badge-warning">{{ str_replace('_', ' ', $item['grund']) }}</span></td>
            <td>
                @php $currentStatus = $statusByProduct[$item['product_id']] ?? null; @endphp
                <form method="POST" action="{{ route('admin.ladenhueter.set-status', $item['product_id']) }}" style="display:flex;gap:4px;flex-wrap:wrap">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $item['warehouse_id'] }}">
                    <select name="status" style="font-size:12px">
                        @foreach($aktionen as $code => $label)
                            <option value="{{ $code }}" @selected($currentStatus === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="notiz" placeholder="Notiz" style="font-size:12px;width:140px">
                    <button type="submit" class="btn btn-sm btn-secondary">Setzen</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@endsection
