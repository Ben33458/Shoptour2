@extends('admin.layout')

@section('title', 'Reinigungsgebühren')

@section('actions')
    <a href="{{ route('admin.rental.cleaning-fee-rules.create') }}" class="btn btn-primary btn-sm">+ Neue Regel</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Reinigungsgebühren ({{ $rules->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Gilt für Typ</th>
                    <th>Gilt für ID</th>
                    <th>Gebührentyp</th>
                    <th>Betrag (netto)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rules as $rule)
                <tr>
                    <td><code>{{ $rule->applies_to_type }}</code></td>
                    <td>{{ $rule->applies_to_id }}</td>
                    <td><code>{{ $rule->fee_type }}</code></td>
                    <td>{{ number_format($rule->amount_net_milli / 1000000, 2, ',', '.') }} €</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.cleaning-fee-rules.edit', $rule) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.cleaning-fee-rules.destroy', $rule) }}"
                              style="display:inline"
                              onsubmit="return confirm('Reinigungsregel löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Reinigungsregeln angelegt.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rules->links() }}
@endsection
