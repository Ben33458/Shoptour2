@extends('admin.layout')

@section('title', 'Benutzerverwaltung')

@section('content')

{{-- Benutzer anlegen --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;margin-bottom:24px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border)">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">Neuen Benutzer anlegen</h2>
    </div>
    <form method="POST" action="{{ route('admin.users.store') }}" style="padding:20px">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 2fr 1fr 1fr auto;gap:10px;align-items:end">
            <div>
                <label style="font-size:12px;color:var(--c-muted);display:block;margin-bottom:4px">Vorname *</label>
                <input type="text" name="first_name" required value="{{ old('first_name') }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div>
                <label style="font-size:12px;color:var(--c-muted);display:block;margin-bottom:4px">Nachname *</label>
                <input type="text" name="last_name" required value="{{ old('last_name') }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div>
                <label style="font-size:12px;color:var(--c-muted);display:block;margin-bottom:4px">E-Mail *</label>
                <input type="email" name="email" required value="{{ old('email') }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div>
                <label style="font-size:12px;color:var(--c-muted);display:block;margin-bottom:4px">Rolle *</label>
                <select name="role" required
                        style="width:100%;padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                    <option value="mitarbeiter" @selected(old('role')=='mitarbeiter')>Mitarbeiter</option>
                    <option value="admin"       @selected(old('role')=='admin')>Admin</option>
                    <option value="kunde"       @selected(old('role')=='kunde')>Kunde</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--c-muted);display:block;margin-bottom:4px">Passwort *</label>
                <input type="password" name="password" required autocomplete="new-password"
                       style="width:100%;padding:7px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
            </div>
            <div>
                <button type="submit"
                        style="padding:7px 14px;background:var(--c-primary);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;white-space:nowrap">
                    + Anlegen
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Benutzerliste --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border)">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">Alle Benutzer</h2>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
            <tr>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Name</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">E-Mail</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Rolle</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Zuständigkeiten</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Status</th>
                <th style="padding:10px 16px"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            <tr style="border-bottom:1px solid var(--c-border)" x-data="{ editing: false, resetting: false }">
                {{-- Normale Ansicht --}}
                <td style="padding:10px 16px;font-weight:500;color:var(--c-text)">{{ $u->name }}</td>
                <td style="padding:10px 16px;color:var(--c-muted)">{{ $u->email }}</td>
                <td style="padding:10px 16px">
                    @php
                        $roleBg    = ['admin' => '#ede9fe', 'mitarbeiter' => '#dbeafe', 'kunde' => '#f3f4f6'][$u->role] ?? '#f3f4f6';
                        $roleColor = ['admin' => '#5b21b6', 'mitarbeiter' => '#1e40af', 'kunde' => '#374151'][$u->role] ?? '#374151';
                    @endphp
                    <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $roleBg }};color:{{ $roleColor }}">
                        {{ ucfirst($u->role) }}
                    </span>
                </td>
                <td style="padding:10px 16px;color:var(--c-muted);font-size:12px">
                    @if($u->zustaendigkeit)
                        {{ implode(', ', $u->zustaendigkeit) }}
                    @else
                        <span style="color:var(--c-muted);opacity:.6">—</span>
                    @endif
                </td>
                <td style="padding:10px 16px">
                    <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;
                                 background:{{ $u->active ? '#dcfce7' : '#f3f4f6' }};
                                 color:{{ $u->active ? '#166534' : '#6b7280' }}">
                        {{ $u->active ? 'Aktiv' : 'Inaktiv' }}
                    </span>
                </td>
                <td style="padding:10px 16px;text-align:right;white-space:nowrap">
                    <button onclick="toggleEdit({{ $u->id }})"
                            style="font-size:12px;color:var(--c-primary);background:none;border:none;cursor:pointer;padding:2px 6px">
                        Bearbeiten
                    </button>
                    <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" style="display:inline">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Passwort-Reset-Link an {{ $u->email }} senden?')"
                                style="font-size:12px;color:var(--c-muted);background:none;border:none;cursor:pointer;padding:2px 6px">
                            Reset-Link senden
                        </button>
                    </form>
                </td>
            </tr>

            {{-- Bearbeitungszeile --}}
            <tr id="edit-row-{{ $u->id }}" style="display:none;background:var(--c-bg);border-bottom:2px solid var(--c-border)">
                <td colspan="6" style="padding:16px 20px">
                    <form method="POST" action="{{ route('admin.users.update', $u) }}"
                          style="display:grid;grid-template-columns:1fr 1fr 2fr 1fr 100px 1fr auto;gap:10px;align-items:end">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">Vorname</label>
                            <input type="text" name="first_name" value="{{ $u->first_name }}" required
                                   style="width:100%;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">Nachname</label>
                            <input type="text" name="last_name" value="{{ $u->last_name }}" required
                                   style="width:100%;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">E-Mail</label>
                            <input type="email" name="email" value="{{ $u->email }}" required
                                   style="width:100%;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">Rolle</label>
                            <select name="role" required
                                    style="width:100%;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                                <option value="mitarbeiter" @selected($u->role==='mitarbeiter')>Mitarbeiter</option>
                                <option value="admin"       @selected($u->role==='admin')>Admin</option>
                                <option value="kunde"       @selected($u->role==='kunde')>Kunde</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">Aktiv</label>
                            <select name="active"
                                    style="width:100%;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                                <option value="1" @selected($u->active)>Ja</option>
                                <option value="0" @selected(!$u->active)>Nein</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--c-muted);display:block;margin-bottom:3px">Neues Passwort</label>
                            <div style="display:flex;gap:6px">
                                <input type="password" id="pw-{{ $u->id }}" placeholder="leer lassen"
                                       style="flex:1;padding:6px 8px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
                                <button type="button"
                                        onclick="setPassword({{ $u->id }}, '{{ route('admin.users.set-password', $u) }}')"
                                        style="padding:6px 10px;background:var(--c-muted);color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap">
                                    Setzen
                                </button>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px">
                            <button type="submit"
                                    style="padding:6px 12px;background:var(--c-primary);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer">
                                Speichern
                            </button>
                            <button type="button" onclick="toggleEdit({{ $u->id }})"
                                    style="padding:6px 10px;background:none;border:1px solid var(--c-border);border-radius:6px;font-size:12px;cursor:pointer;color:var(--c-muted)">
                                Abbrechen
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:24px;text-align:center;color:var(--c-muted)">Keine Benutzer.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('scripts')
<script>
function toggleEdit(id) {
    const row = document.getElementById('edit-row-' + id);
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

function setPassword(userId, url) {
    const pw = document.getElementById('pw-' + userId).value;
    if (!pw) { alert('Bitte ein Passwort eingeben.'); return; }
    if (pw.length < 8) { alert('Passwort muss mindestens 8 Zeichen lang sein.'); return; }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ password: pw }),
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert(data.message);
            document.getElementById('pw-' + userId).value = '';
        } else {
            alert(data.message || 'Fehler beim Setzen des Passworts.');
        }
    });
}
</script>
@endpush
@endsection
