@extends('admin.layout')

@section('title', 'Miet-Preisregeln')

@section('actions')
    <a href="{{ route('admin.rental.price-rules.create') }}" class="btn btn-primary btn-sm">+ Neue Preisregel</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Miet-Preisregeln ({{ $rules->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Leihartikel</th>
                    <th>Preistyp</th>
                    <th>Preis (netto)</th>
                    <th>Preis (brutto 19%)</th>
                    <th>Kundengruppe</th>
                    <th>Gültig von</th>
                    <th>Gültig bis</th>
                    <th style="text-align:center">Getränkebestellung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rules as $rule)
                <tr>
                    <td>{{ $rule->rentalItem?->name ?? '—' }}</td>
                    <td><code>{{ $rule->price_type }}</code></td>
                    <td>{{ number_format($rule->price_net_milli / 1000000, 2, ',', '.') }} €</td>
                    <td>{{ number_format($rule->price_net_milli * 1.19 / 1000000, 2, ',', '.') }} €</td>
                    <td>{{ $rule->customerGroup?->name ?? 'Alle' }}</td>
                    <td>{{ $rule->valid_from?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $rule->valid_until?->format('d.m.Y') ?? '—' }}</td>
                    <td style="text-align:center">
                        @if($rule->requires_drink_order)
                            <span class="badge badge-delivered">ja</span>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.price-rules.edit', $rule) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.price-rules.destroy', $rule) }}"
                              style="display:inline"
                              onsubmit="return confirm('Preisregel löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Preisregeln angelegt.
                        <a href="{{ route('admin.rental.price-rules.create') }}" class="btn btn-primary btn-sm" style="margin-left:12px">+ Erste anlegen</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rules->links() }}
@endsection
