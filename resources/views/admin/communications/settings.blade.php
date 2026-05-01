@extends('admin.layout')

@section('title', 'Gmail-Einstellungen')

@section('content')
<div style="max-width:640px;">

    <div class="card" style="padding:24px;margin-bottom:20px;">
        <h2 style="font-size:1rem;font-weight:700;margin:0 0 16px;">Gmail-Verbindung</h2>

        @if($syncState && $syncState->email_address)
            {{-- Connected --}}
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:14px 16px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                <span style="font-size:1.4rem;">✓</span>
                <div>
                    <div style="font-weight:600;color:#15803d;">Verbunden</div>
                    <div style="font-size:.875rem;color:#166534;">{{ $syncState->email_address }}</div>
                </div>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                <form action="{{ route('admin.communications.settings.gmail.sync') }}" method="POST">
                    @csrf
                    <button class="btn btn-primary"
                        {{ $syncState->sync_status === 'running' ? 'disabled' : '' }}>
                        {{ $syncState->sync_status === 'running' ? 'Läuft…' : '▶ 50 E-Mails aus Posteingang importieren' }}
                    </button>
                </form>
                <form action="{{ route('admin.communications.settings.gmail.disconnect') }}" method="POST"
                      onsubmit="return confirm('Gmail-Verbindung wirklich trennen?')">
                    @csrf
                    <button class="btn btn-danger">Verbindung trennen</button>
                </form>
            </div>

            <div style="font-size:.8125rem;color:#6b7280;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:4px;">
                <strong>Manueller Import:</strong> Liest die letzten 50 E-Mails aus dem Posteingang ein —
                ohne Label-Filterung. Nützlich zum Befüllen der ersten Trainingsdaten.
            </div>

            <div style="font-size:.875rem;color:#6b7280;">
                <strong>Letzter Sync:</strong> {{ $syncState->last_synced_at?->format('d.m.Y H:i') ?? 'Noch nie' }}
                &nbsp;|&nbsp;
                <strong>Status:</strong>
                <span style="color:{{ $syncState->sync_status === 'error' ? '#dc2626' : '#6b7280' }}">
                    {{ $syncState->sync_status }}
                </span>
                @if($syncState->error_message)
                    <div style="color:#dc2626;margin-top:8px;padding:8px;background:#fee2e2;border-radius:6px;">
                        {{ $syncState->error_message }}
                    </div>
                @endif
            </div>

        @else
            {{-- Not connected --}}
            <p style="color:#6b7280;margin-bottom:20px;font-size:.875rem;">
                Verbinden Sie ein Gmail-Konto, um eingehende E-Mails automatisch zu importieren und zu verarbeiten.
            </p>

            @if(!config('services.gmail.client_id'))
            <div style="padding:12px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;color:#9a3412;font-size:.875rem;margin-bottom:16px;">
                <strong>Konfiguration fehlt:</strong> Bitte <code>GMAIL_CLIENT_ID</code>, <code>GMAIL_CLIENT_SECRET</code>
                und <code>GMAIL_REDIRECT_URI</code> in der <code>.env</code>-Datei eintragen.
            </div>
            @endif

            <form action="{{ route('admin.communications.settings.gmail.connect') }}" method="POST">
                @csrf
                <button class="btn btn-primary" {{ !config('services.gmail.client_id') ? 'disabled' : '' }}>
                    Mit Gmail verbinden
                </button>
            </form>
        @endif
    </div>

    {{-- Nest-Labels --}}
    <div class="card" style="padding:24px;margin-bottom:20px;">
        <h2 style="font-size:.9rem;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
            Automatisierter Import — Nest-Labels
        </h2>
        <p style="font-size:.875rem;color:#374151;margin:0 0 14px;">
            Für den automatisierten Import (z.B. per Cron) müssen E-Mails in Gmail mit dem Label
            <strong>nest/einflug</strong> markiert sein. Die Labels werden beim ersten Sync automatisch
            im Postfach angelegt.
        </p>
        <table style="font-size:.875rem;border-collapse:collapse;width:100%;">
            <thead>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:6px 12px 6px 0;color:#6b7280;font-weight:600;">Gmail-Label</th>
                    <th style="text-align:left;padding:6px 0;color:#6b7280;font-weight:600;">Bedeutung</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 12px 8px 0;"><code style="background:#eff6ff;color:#1d4ed8;padding:2px 6px;border-radius:4px;">nest/einflug</code></td>
                    <td style="padding:8px 0;color:#374151;">Mail soll eingelesen werden — <em>hier manuell setzen</em></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 12px 8px 0;"><code style="background:#fefce8;color:#854d0e;padding:2px 6px;border-radius:4px;">nest/im-flug</code></td>
                    <td style="padding:8px 0;color:#374151;">Verarbeitung läuft gerade</td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 12px 8px 0;"><code style="background:#f0fdf4;color:#166534;padding:2px 6px;border-radius:4px;">nest/gelandet</code></td>
                    <td style="padding:8px 0;color:#374151;">Erfolgreich übernommen</td>
                </tr>
                <tr>
                    <td style="padding:8px 12px 8px 0;"><code style="background:#fef2f2;color:#991b1b;padding:2px 6px;border-radius:4px;">nest/abgestürzt</code></td>
                    <td style="padding:8px 0;color:#374151;">Verarbeitung fehlgeschlagen — manuelle Prüfung nötig</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size:.8125rem;color:#9ca3af;margin:12px 0 0;">
            Benötigter OAuth-Scope: <code>gmail.modify</code> (nicht nur readonly).
            Falls du bisher mit <code>gmail.readonly</code> verbunden warst, bitte neu autorisieren.
        </p>
    </div>

    {{-- Setup instructions --}}
    <div class="card" style="padding:24px;">
        <h2 style="font-size:.9rem;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">
            Einrichtung — Google Cloud Console
        </h2>
        <ol style="font-size:.875rem;color:#374151;line-height:1.8;padding-left:20px;">
            <li>Google Cloud Console öffnen → <strong>APIs &amp; Services → Credentials</strong></li>
            <li><strong>OAuth 2.0-Client-ID</strong> erstellen (Typ: Webanwendung)</li>
            <li>Autorisierte Weiterleitungs-URI eintragen: <code>{{ config('services.gmail.redirect_uri') }}</code></li>
            <li><strong>Gmail API</strong> aktivieren (APIs &amp; Services → Library)</li>
            <li><code>GMAIL_CLIENT_ID</code> und <code>GMAIL_CLIENT_SECRET</code> in <code>.env</code> eintragen</li>
            <li>Seite neu laden → „Mit Gmail verbinden" klicken</li>
        </ol>
    </div>

</div>
@endsection
