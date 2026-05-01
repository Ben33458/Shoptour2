@extends('admin.layout')

@section('title', 'Schadenstarife')

@section('actions')
    <a href="{{ route('admin.rental.damage-tariffs.create') }}" class="btn btn-primary btn-sm">+ Neuer Tarif</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Schadenstarife ({{ $tariffs->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Gilt für Typ</th>
                    <th>Gilt für ID</th>
                    <th>Betrag (netto)</th>
                    <th>Beschreibung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($tariffs as $tariff)
                <tr>
                    <td><code>{{ $tariff->applies_to_type }}</code></td>
                    <td>{{ $tariff->applies_to_id }}</td>
                    <td>{{ number_format($tariff->amount_net_milli / 1000000, 2, ',', '.') }} €</td>
                    <td>{{ $tariff->description }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.damage-tariffs.edit', $tariff) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.damage-tariffs.destroy', $tariff) }}"
                              style="display:inline"
                              onsubmit="return confirm('Schadenstarif löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Schadenstarife angelegt.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $tariffs->links() }}
@endsection
