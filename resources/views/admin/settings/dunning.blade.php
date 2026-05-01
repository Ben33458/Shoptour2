@extends('admin.layout')

@section('title', 'Mahnwesen-Einstellungen')

@section('actions')
    <a href="{{ route('admin.debtor.index') }}" class="btn btn-outline btn-sm">← Offene Posten</a>
@endsection

@section('content')

@if(session('success'))
<div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
    {{ session('success') }}
</div>
@endif

<form method="POST" action="{{ route('admin.settings.dunning.update') }}">
@csrf @method('POST')

{{-- ── Absender & Kommunikation ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Absender & E-Mail-Konfiguration</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <label class="hint">Absendername</label>
            <input type="text" name="dunning[sender_name]" class="form-control"
                value="{{ $settings['dunning.sender_name'] ?? '' }}"
                placeholder="z.B. Kolabri Getränke">
        </div>
        <div>
            <label class="hint">Absender-E-Mail</label>
            <input type="email" name="dunning[sender_email]" class="form-control"
                value="{{ $settings['dunning.sender_email'] ?? '' }}"
                placeholder="mahnung@firma.de">
        </div>
        <div>
            <label class="hint">Reply-To (optional)</label>
            <input type="email" name="dunning[reply_to]" class="form-control"
                value="{{ $settings['dunning.reply_to'] ?? '' }}"
                placeholder="buchhaltung@firma.de">
        </div>
        <div>
            <label class="hint">CC (optional)</label>
            <input type="email" name="dunning[cc]" class="form-control"
                value="{{ $settings['dunning.cc'] ?? '' }}">
        </div>
        <div>
            <label class="hint">BCC (optional)</label>
            <input type="email" name="dunning[bcc]" class="form-control"
                value="{{ $settings['dunning.bcc'] ?? '' }}">
        </div>
        <div>
            <label class="hint">Test-E-Mail-Adresse (Testmodus)</label>
            <input type="email" name="dunning[test_email]" class="form-control"
                value="{{ $settings['dunning.test_email'] ?? '' }}"
                placeholder="test@intern.de">
        </div>
    </div>
</div>

{{-- ── Briefdienst ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Briefdienst (Stufe 2 — postalische Mahnung)</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div style="grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" name="dunning[postal_service_enabled]" value="1"
                    @checked(($settings['dunning.postal_service_enabled'] ?? '0') === '1')>
                <span>Briefdienst aktiv (Stufe 2 per E-Mail an Briefdienst)</span>
            </label>
        </div>
        <div>
            <label class="hint">Briefdienst-E-Mail-Adresse</label>
            <input type="email" name="dunning[postal_service_email]" class="form-control"
                value="{{ $settings['dunning.postal_service_email'] ?? 'auftrag@e-mailbrief.de' }}"
                placeholder="auftrag@e-mailbrief.de">
            <div class="hint" style="margin-top:4px">Standardwert: auftrag@e-mailbrief.de</div>
        </div>
    </div>
</div>

{{-- ── Fristen ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Mahnstufen & Fristen</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <label class="hint">Tage bis Stufe 1 (nach Fälligkeit)</label>
            <input type="number" name="dunning[days_to_level1]" class="form-control" min="1" max="90"
                value="{{ $settings['dunning.days_to_level1'] ?? 7 }}">
        </div>
        <div>
            <label class="hint">Tage zwischen Stufe 1 und Stufe 2</label>
            <input type="number" name="dunning[days_level1_to_level2]" class="form-control" min="1" max="180"
                value="{{ $settings['dunning.days_level1_to_level2'] ?? 14 }}">
        </div>
        <div>
            <label class="hint">Maximaler Versand pro Lauf</label>
            <input type="number" name="dunning[max_send_per_run]" class="form-control" min="1" max="500"
                value="{{ $settings['dunning.max_send_per_run'] ?? 50 }}">
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" name="dunning[test_mode]" value="1"
                    @checked(($settings['dunning.test_mode'] ?? '0') === '1')>
                <span>Vorschau-only / Testmodus global aktiviert</span>
            </label>
            <div class="hint" style="margin-top:4px">Im Testmodus gehen alle E-Mails an die Test-E-Mail-Adresse, keine Änderungen an Mahnstufen.</div>
        </div>
    </div>
</div>

{{-- ── Zinsen & Gebühren ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Verzugszinsen & Pauschalen</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div style="grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" name="dunning[interest_enabled]" value="1"
                    @checked(($settings['dunning.interest_enabled'] ?? '0') === '1')>
                <span>Verzugszinsen berechnen (§ 288 BGB)</span>
            </label>
            <div class="hint" style="margin-top:4px">B2C: Basiszins + 5 %. B2B: Basiszins + 8 %. Nur bei nachgewiesenem Verzug.</div>
        </div>
        <div>
            <label class="hint">Basiszinssatz (in Basispunkten, z.B. 362 = 3,62%)</label>
            <input type="number" name="dunning[base_rate_bps]" class="form-control" min="0" max="5000"
                value="{{ $settings['dunning.base_rate_bps'] ?? 362 }}">
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" name="dunning[b2b_flat_fee_enabled]" value="1"
                    @checked(($settings['dunning.b2b_flat_fee_enabled'] ?? '0') === '1')>
                <span>B2B-Verzugspauschale (40 € je Rechnung, § 288 Abs. 5 BGB)</span>
            </label>
            <div class="hint" style="margin-top:4px">Nur bei gewerblichen Kunden und wenn rechtlich zulässig.</div>
        </div>
    </div>
</div>

{{-- ── Firmendaten & Bankverbindung ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">Firmendaten & Bankverbindung (für Mahnschreiben)</div>
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <label class="hint">Firmenname (Absender)</label>
            <input type="text" name="dunning[company_name]" class="form-control"
                value="{{ $settings['dunning.company_name'] ?? '' }}"
                placeholder="Kolabri Getränke GmbH">
        </div>
        <div>
            <label class="hint">Adresse (einzeilig)</label>
            <input type="text" name="dunning[company_address]" class="form-control"
                value="{{ $settings['dunning.company_address'] ?? '' }}"
                placeholder="Musterstraße 1, 12345 Musterstadt">
        </div>
        <div>
            <label class="hint">IBAN</label>
            <input type="text" name="dunning[bank_iban]" class="form-control"
                value="{{ $settings['dunning.bank_iban'] ?? '' }}"
                placeholder="DE00 0000 0000 0000 0000 00">
        </div>
        <div>
            <label class="hint">BIC</label>
            <input type="text" name="dunning[bank_bic]" class="form-control"
                value="{{ $settings['dunning.bank_bic'] ?? '' }}"
                placeholder="COBADEFFXXX">
        </div>
        <div>
            <label class="hint">Bankname</label>
            <input type="text" name="dunning[bank_name]" class="form-control"
                value="{{ $settings['dunning.bank_name'] ?? '' }}"
                placeholder="Commerzbank">
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary">Einstellungen speichern</button>

</form>
@endsection
