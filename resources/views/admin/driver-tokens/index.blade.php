@extends('admin.layout')

@section('title', 'Fahrer-API-Token')

@section('actions')
    <a href="{{ route('admin.driver-tokens.create') }}" class="btn btn-primary">
        + Neues Token ausstellen
    </a>
@endsection

@section('content')

{{-- ── One-time plain token display ── --}}
@if(session('plain_token'))
    <div class="alert alert-success" style="word-break:break-all">
        <strong>✅ Token „{{ session('plain_token_label') }}" wurde ausgestellt.</strong><br>
        <span style="font-size:0.85em">
            Achtung: Dieser Token wird <strong>nur einmal</strong> angezeigt und
            kann nicht wiederhergestellt werden. Kopiere ihn jetzt.
        </span>
        <br><br>
        <code style="font-size:1.05em;background:rgba(0,0,0,.08);padding:6px 10px;border-radius:4px;display:block">
            {{ session('plain_token') }}
        </code>
    </div>
@endif

{{-- ── Token table ── --}}
<div class="card">
    <div class="card-header">Alle Fahrer-Token</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bezeichnung</th>
                    <th>Status</th>
                    <th>Zuletzt genutzt</th>
                    <th>Läuft ab</th>
                    <th>Ausgestellt am</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tokens as $token)
                    <tr>
                        <td>{{ $token->id }}</td>
                        <td>{{ $token->label ?? '—' }}</td>
                        <td>
                            @php $status = $token->statusLabel(); @endphp
                            <span class="badge badge-{{ $status === 'aktiv' ? 'success' : ($status === 'widerrufen' ? 'danger' : 'warning') }}">
                                {{ $status }}
                            </span>
                        </td>
                        <td>
                            {{ $token->last_used_at
                                ? $token->last_used_at->format('d.m.Y H:i')
                                : '—' }}
                        </td>
                        <td>
                            {{ $token->expires_at
                                ? $token->expires_at->format('d.m.Y')
                                : 'nie' }}
                        </td>
                        <td>{{ $token->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($token->revoked_at === null)
                                <form method="POST"
                                      action="{{ route('admin.driver-tokens.revoke', $token) }}"
                                      style="display:inline"
                                      onsubmit="return confirm('Token „{{ addslashes($token->label ?? '#'.$token->id) }}" wirklich widerrufen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Widerrufen
                                    </button>
                                </form>
                            @else
                                <span class="text-muted" style="font-size:.85em">
                                    {{ $token->revoked_at->format('d.m.Y') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--c-muted)">
                            Keine Tokens vorhanden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tokens->hasPages())
        <div class="card-body" style="padding-top:0">
            {{ $tokens->links() }}
        </div>
    @endif
</div>

@endsection
