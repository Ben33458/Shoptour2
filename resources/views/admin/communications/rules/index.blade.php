@extends('admin.layout')

@section('title', 'Kommunikation — Regeln')

@section('content')

{{-- New rule form --}}
<div class="card" style="margin-bottom:24px;padding:20px;">
    <h2 style="font-size:1rem;font-weight:600;margin:0 0 16px;">Neue Regel</h2>
    <form action="{{ route('admin.communications.rules.store') }}" method="POST">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="form-label">Name *</label>
                <input name="name" class="form-control" required maxlength="200">
            </div>
            <div>
                <label class="form-label">Priorität (kleiner = früher)</label>
                <input name="priority" type="number" class="form-control" value="100">
            </div>
            <div>
                <label class="form-label">Bedingung *</label>
                <select name="condition_type" class="form-control" required>
                    <option value="from_domain">Von Domain (z.B. example.com)</option>
                    <option value="from_address">Von Adresse (exakt)</option>
                    <option value="subject_contains">Betreff enthält</option>
                    <option value="has_attachment">Hat Anhang</option>
                    <option value="attachment_type">Anhang-Typ (MIME)</option>
                    <option value="to_address">An Adresse</option>
                </select>
            </div>
            <div>
                <label class="form-label">Bedingungswert *</label>
                <input name="condition_value" class="form-control" required maxlength="500" placeholder="z.B. example.com oder rechnung">
            </div>
            <div>
                <label class="form-label">Aktion *</label>
                <select name="action_type" class="form-control" required>
                    <option value="assign_customer">Kunden zuordnen (ID)</option>
                    <option value="assign_supplier">Lieferanten zuordnen (ID)</option>
                    <option value="set_tag">Tag setzen (Name)</option>
                    <option value="set_category">Kategorie setzen</option>
                    <option value="skip_review">Prüfung überspringen</option>
                    <option value="set_direction">Richtung setzen</option>
                </select>
            </div>
            <div>
                <label class="form-label">Aktionswert</label>
                <input name="action_value" class="form-control" maxlength="500" placeholder="z.B. Kunden-ID oder Tag-Name">
            </div>
            <div>
                <label class="form-label">Konfidenz-Boost (0–100)</label>
                <input name="confidence_boost" type="number" class="form-control" value="20" min="0" max="100">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:6px;">
                    <input name="active" type="checkbox" value="1" checked>
                    Aktiv
                </label>
            </div>
        </div>
        <div style="margin-top:12px;">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea>
        </div>
        <div style="margin-top:12px;">
            <button class="btn btn-primary">Regel erstellen</button>
        </div>
    </form>
</div>

{{-- Rules list --}}
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Prio</th>
                <th>Name</th>
                <th>Bedingung</th>
                <th>Aktion</th>
                <th>Boost</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rules as $rule)
            <tr>
                <td style="color:#9ca3af;">{{ $rule->priority }}</td>
                <td>
                    <strong>{{ $rule->name }}</strong>
                    @if($rule->description)
                        <div style="font-size:.8rem;color:#9ca3af;">{{ Str::limit($rule->description, 60) }}</div>
                    @endif
                </td>
                <td style="font-size:.875rem;">
                    <span style="background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:.8rem;">{{ $rule->conditionLabel() }}</span>
                    <span style="color:#6b7280;"> = </span>
                    <code style="font-size:.8rem;">{{ $rule->condition_value }}</code>
                </td>
                <td style="font-size:.875rem;">
                    <span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-size:.8rem;">{{ $rule->actionLabel() }}</span>
                    @if($rule->action_value)
                        <span style="color:#6b7280;"> → </span>
                        <code style="font-size:.8rem;">{{ $rule->action_value }}</code>
                    @endif
                </td>
                <td style="text-align:center;color:#6b7280;">+{{ $rule->confidence_boost }}</td>
                <td>
                    <form action="{{ route('admin.communications.rules.update', $rule) }}" method="POST" style="display:inline;">
                        @csrf @method('PUT')
                        <input type="hidden" name="name" value="{{ $rule->name }}">
                        <input type="hidden" name="description" value="{{ $rule->description }}">
                        <input type="hidden" name="condition_type" value="{{ $rule->condition_type }}">
                        <input type="hidden" name="condition_value" value="{{ $rule->condition_value }}">
                        <input type="hidden" name="action_type" value="{{ $rule->action_type }}">
                        <input type="hidden" name="action_value" value="{{ $rule->action_value }}">
                        <input type="hidden" name="confidence_boost" value="{{ $rule->confidence_boost }}">
                        <input type="hidden" name="priority" value="{{ $rule->priority }}">
                        <input type="hidden" name="active" value="{{ $rule->active ? '0' : '1' }}">
                        <button class="btn btn-outline" style="padding:3px 10px;font-size:.78rem;">
                            {{ $rule->active ? 'Deaktivieren' : 'Aktivieren' }}
                        </button>
                    </form>
                </td>
                <td>
                    <form action="{{ route('admin.communications.rules.destroy', $rule) }}" method="POST"
                          onsubmit="return confirm('Regel löschen?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger" style="padding:3px 10px;font-size:.78rem;">Löschen</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;color:#9ca3af;padding:30px;">Noch keine Regeln angelegt.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
