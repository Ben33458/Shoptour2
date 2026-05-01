@extends('admin.layout')

@section('title', 'Pfandregeln')

@section('actions')
    <a href="{{ route('admin.rental.deposit-rules.create') }}" class="btn btn-primary btn-sm">+ Neue Regel</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Pfandregeln ({{ $rules->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Regeltyp</th>
                    <th>Betrag (netto)</th>
                    <th style="text-align:center">Nur Privat</th>
                    <th>Min. Risikoklasse</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rules as $rule)
                <tr>
                    <td><code>{{ $rule->rule_type }}</code></td>
                    <td>
                        @if($rule->amount_net_milli)
                            {{ number_format($rule->amount_net_milli / 1000000, 2, ',', '.') }} €
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        @if($rule->private_only)
                            <span class="badge badge-delivered">ja</span>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td>{{ $rule->min_risk_class ?? '—' }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.deposit-rules.edit', $rule) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.deposit-rules.destroy', $rule) }}"
                              style="display:inline"
                              onsubmit="return confirm('Pfandregel löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Pfandregeln angelegt.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rules->links() }}
@endsection
