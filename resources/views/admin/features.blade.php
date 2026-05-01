@extends('admin.layout')

@section('title', 'Was kann das Tool?')

@section('content')

<style>
.feat-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
}
.feat-hero h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0 0 .5rem; }
.feat-hero p  { color: #94a3b8; font-size: .95rem; margin: 0; max-width: 680px; line-height: 1.6; }

.feat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
.feat-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
}
.feat-card-header {
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .6rem;
}
.feat-icon {
    font-size: 1.3rem;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    background: var(--c-bg);
    border-radius: 8px;
    flex-shrink: 0;
}
.feat-card-title { font-size: .95rem; font-weight: 700; color: var(--c-text); flex: 1; }
.feat-date {
    font-size: .7rem;
    color: var(--c-muted);
    margin-bottom: .85rem;
    display: flex;
    align-items: center;
    gap: .3rem;
}
.feat-date span { color: var(--c-primary); font-weight: 600; }
.feat-card ul { list-style: none; padding: 0; margin: 0; flex: 1; }
.feat-card ul li {
    font-size: .85rem; color: var(--c-muted);
    padding: .3rem 0;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: flex-start; gap: .5rem;
    line-height: 1.45;
}
.feat-card ul li:last-child { border-bottom: none; }
.feat-card ul li::before { content: "✓"; color: #10b981; font-size: .75rem; margin-top: 1px; flex-shrink: 0; }

.section-heading {
    font-size: 1.1rem; font-weight: 700; color: var(--c-text);
    margin: 2rem 0 1rem;
    display: flex; align-items: center; gap: .5rem;
}
.section-heading::after {
    content: ''; flex: 1;
    height: 1px; background: var(--c-border);
}

.roadmap-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.roadmap-item {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .5rem 0;
    border-bottom: 1px solid var(--c-border);
    font-size: .875rem;
    color: var(--c-muted);
    line-height: 1.5;
}
.roadmap-item:last-child { border-bottom: none; }
.roadmap-dot {
    width: 8px; height: 8px; border-radius: 50%;
    margin-top: 5px; flex-shrink: 0;
}
.dot-idea   { background: #6366f1; }
.dot-opt    { background: #f59e0b; }
.dot-future { background: #64748b; }
</style>

@php
/**
 * Feature-Karten.
 * Format: ['icon', 'title', 'since' (YYYY-MM-DD), 'updated' (YYYY-MM-DD|null), items[]]
 * → 'since'   = Datum, ab dem dieses Modul verfügbar ist
 * → 'updated' = letztes Update (null = keine Änderung nach Einführung)
 */
$features = [
    [
        'icon'    => '👤',
        'title'   => 'Mitarbeiterverwaltung',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'Anlegen, Bearbeiten, Deaktivieren (Soft Delete)',
            'Rollen: Admin, Manager, Teamleiter, Mitarbeiter',
            'Beschäftigungsarten: Vollzeit, Teilzeit, Minijob, Praktikum',
            'Stammdaten: Adresse, IBAN, Notfallkontakt, Kleidergröße, Führerschein',
            '4-stellige PIN und Personalnummer für die Stempeluhr',
            'Automatischer Vorschlag der nächsten freien Personalnummer',
            'E-Mail-Protokoll: alle versandten Mails je Mitarbeiter',
        ],
    ],
    [
        'icon'    => '🚀',
        'title'   => 'Onboarding-Workflow',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'E-Mail-Einladung mit Verifikationslink + 6-stelligem Code',
            'Mitarbeiter füllt eigenes Profil aus (Datenschutz, IBAN, Notfallkontakt …)',
            'Eigene Personalnummer + PIN selbst wählen',
            'Admin-Review: Freigeben oder Zurückweisen mit Begründung',
            'Automatische Willkommens-E-Mail nach Freigabe (mit Status-Meldung)',
            'Onboarding jederzeit zurücksetzbar (Token werden invalidiert)',
            'Status-Badges in der Mitarbeiterliste (pending, review, approved, active)',
        ],
    ],
    [
        'icon'    => '🏠',
        'title'   => 'Mitarbeiter-Portal',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'PIN-basierter Login an der Stempeluhr (ohne Passwort)',
            'Dashboard mit Stempeluhr, Schichtbericht und Aufgaben',
            'Stempeluhr: Ein-/Ausstempeln, Pause starten/beenden',
            'Schichtbericht täglich direkt im Dashboard (ohne Schicht erforderlich)',
            'Vorfallmeldung (keine / gering / schwerwiegend) im Schichtbericht',
            'Checklisten im Schichtbericht (templatebasiert)',
            'Team-Übersicht für Schichtleiter (wer ist gerade eingestempelt?)',
        ],
    ],
    [
        'icon'    => '📅',
        'title'   => 'Schichten &amp; Zeiterfassung',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'Schichtplanung mit Schichtbereichen (Farben)',
            'Ad-hoc-Stempelung ohne geplante Schicht möglich',
            'Netto-Arbeitszeit (abzüglich Pausen) live auf dem Dashboard',
            'Zeiteinträge mit Pausensegmenten',
            'Admin-Übersicht: Zeiterfassung je Mitarbeiter und Tag',
            'Schichtberichte entkoppelt von Schichten — ein Bericht pro Tag',
        ],
    ],
    [
        'icon'    => '🛒',
        'title'   => 'Onlineshop',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'Produktkatalog mit Kategorien, Marken, Warengruppen, Gebinden',
            'Produktdetailseite mit Pfandanzeige (netto/brutto je Kundengruppe)',
            'Warenkorb mit Mini-Dropdown im Header (Alpine.js)',
            'Checkout mit Adressverwaltung',
            'Bestellverlauf im Kundenkonto',
            'Kundengruppen mit individuellen Preisen (Netto/Brutto-Anzeige)',
            'LMIV-Kennzeichnung für Lebensmittel',
            'Kolabri-Blau Branding (#1e90d0), Light &amp; Dark Mode',
        ],
    ],
    [
        'icon'    => '👥',
        'title'   => 'Kundenverwaltung',
        'since'   => '2026-03-22',
        'updated' => null,
        'items'   => [
            'Kundenstammdaten mit mehreren Adressen',
            'Kundengruppen (z. B. Gastro, Privat, Groß) mit Preislogik',
            'Bestellhistorie je Kunde',
            'Kundenregistrierung inkl. E-Mail-Verifizierung',
            'Ninox-ID-Verknüpfung für bestehende Kunden',
        ],
    ],
    [
        'icon'    => '🔗',
        'title'   => 'Ninox-Integration',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'Zwei Datenbanken: Kehr-DB (aktuell) und Alt-DB (historisch)',
            'Mitarbeiter-Abgleich per Namens-Fuzzy-Matching (beide DBs)',
            'Priorität: shoptour2 &gt; Kehr-DB &gt; Alt-DB (neueste Änderung gewinnt)',
            'Überschreiben nur mit expliziter Bestätigung möglich',
            'Recurring Tasks aus Ninox (Zuständigkeiten, Fälligkeiten)',
            'Rohdaten-Import: Alle Tabellen beider DBs in ninox_raw_records',
            'Abgleich-Übersicht mit Konfidenzwerten und manuellem Bestätigen',
        ],
    ],
    [
        'icon'    => '📄',
        'title'   => 'CMS &amp; Rechtliches',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'CMS-Seiten (HTML-Editor, Slug, Menü-Einbindung)',
            'Hauptmenü und Footer-Menü im Shop steuerbar',
            'Vollständige DSGVO-konforme Datenschutzerklärung',
            'Impressum-Seite',
            'Dark-Mode-fähige CMS-Inhalte (section-cards, Überschriften)',
        ],
    ],
    [
        'icon'    => '✉️',
        'title'   => 'E-Mail-System',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'SMTP-Versand über s5.internetwerk.de (TLS, Port 587)',
            'Kolabri-gebrandete HTML-Mails (Logo, #1e90d0-Blau)',
            'Vorlagen: Onboarding-Verifizierung, Willkommen im Team',
            'Audit-Log aller versandten Mails je Mitarbeiter',
            'Fehlerstatus bei Versandproblemen wird gespeichert',
            'SPF + DKIM für kolabri.de eingerichtet',
        ],
    ],
    [
        'icon'    => '🎨',
        'title'   => 'Design &amp; UX',
        'since'   => '2026-03-22',
        'updated' => '2026-03-23',
        'items'   => [
            'Dark Mode in allen Bereichen (Admin, Shop, Mitarbeiterportal)',
            'Dark-Mode-Einstellung wird pro User/Cookie gespeichert',
            'Einheitliches Kolabri-Logo überall (Admin, Shop, E-Mails, Onboarding)',
            'Responsive Design (Mobile-freundlich im Shop)',
            'Deutsche Benutzeroberfläche inkl. Validierungsfehlertexte',
            'Flash-Messages (Erfolg, Fehler, Info) in allen Bereichen',
        ],
    ],
    [
        'icon'    => '🔒',
        'title'   => 'Sicherheit &amp; Zugriff',
        'since'   => '2026-03-22',
        'updated' => null,
        'items'   => [
            'Rollenbasierter Zugriff (Admin, Manager, Teamleiter, Mitarbeiter)',
            'PIN-Hashing (bcrypt) für Stempeluhr-Zugänge',
            'CSRF-Schutz in allen Formularen',
            'Onboarding-Token-Invalidierung nach Verwendung oder Reset',
            'Separate Session für Mitarbeiterportal (kein Laravel-Auth nötig)',
            'Soft Delete für Mitarbeiter (Daten bleiben erhalten)',
        ],
    ],
    [
        'icon'    => '✅',
        'title'   => 'Wiederkehrende Aufgaben',
        'since'   => '2026-03-22',
        'updated' => null,
        'items'   => [
            'Import aus Ninox-Tabelle ninox_77_regelmaessige_aufgaben',
            'Fälligkeiten und Prioritäten aus Ninox',
            'Zuständigkeit je Mitarbeiter-Rolle',
            'Aufgaben als erledigt markieren mit automatischer Nächstfälligkeit',
            'Vollständige Erledigungshistorie je Aufgabe',
            'Offene Aufgaben-Zähler auf dem Mitarbeiter-Dashboard',
        ],
    ],
];

$roadmap = [
    ['dot-idea',   'Urlaubsverwaltung',                   'Urlaubsanträge stellen, genehmigen und ablehnen. Urlaubskonten automatisch pflegen (Resturlaub, Übertrag).'],
    ['dot-idea',   'Dienstplanung / Schichtkalender',     'Visueller Kalender für Schichtplanung. Mitarbeiterverfügbarkeit, Wunschfreitage und automatische Planvorschläge.'],
    ['dot-idea',   'Lohnabrechnung / Stundennachweis',    'Monatsauswertung der Arbeitszeiten je Mitarbeiter als PDF-Export. Anbindung an DATEV oder Steuerbüro denkbar.'],
    ['dot-idea',   'Lexoffice-Integration',               'Bestellungen automatisch als Rechnungen in Lexoffice anlegen. Zahlungsabgleich und Buchhaltungsexport.'],
    ['dot-idea',   'Dokumentenverwaltung für Mitarbeiter','Arbeitsverträge, Lohnnachweise und Zertifikate hochladen und anzeigen. Mitarbeiter können Dokumente im Portal einsehen.'],
    ['dot-opt',    'Ninox-Sync: Bidirektional',           'Änderungen in shoptour2 zurück in Ninox schreiben (z. B. E-Mail, Adresse). Aktuell nur Einweg (Ninox → shoptour2).'],
    ['dot-opt',    'Schichtbericht-Auswertung im Admin',  'Statistiken über Kassendifferenzen, Kundenzahlen und Vorfälle über Zeiträume. Filterbar nach Mitarbeiter und Bereich.'],
    ['dot-opt',    'Produktverwaltung per Barcode-Scanner','Lagerbestandspflege und Wareneingangsbuchung über Scanner. EAN-Suche im Shop.'],
    ['dot-opt',    'Kundenbewertungen &amp; Favoriten',   'Produkte bewerten und auf Merklisten setzen. Basis für personalisierte Empfehlungen.'],
    ['dot-opt',    'Push-Benachrichtigungen',             'In-App-Benachrichtigungen für neue Aufgaben, Vorfälle oder Onboarding-Ereignisse. Optional per E-Mail oder Slack.'],
    ['dot-future', 'Progressive Web App (PWA)',           'Mitarbeiterportal und Stempeluhr als installierbare App auf dem Smartphone. Offline-Fähigkeit für Stempelungen.'],
    ['dot-future', 'Tourenplanung-Integration',           'Liefertouren aus Ninox direkt im Portal planen und Fahrern zuweisen. GPS-Tracking und Bestätigungs-Workflow.'],
    ['dot-future', 'Kundenbindungs-Programm',             'Punkte sammeln, Rabatte einlösen, Treuekarten. Gamification-Elemente für wiederkehrende Käufer.'],
];

$benefits = [
    ['🏢', 'Alles an einem Ort',         'Kein Wechsel zwischen Ninox, Kassensystem, E-Mail-Programm und Excel. Mitarbeiter, Shop und Betrieb laufen in einem System.'],
    ['⚡', 'Auf Kolabri zugeschnitten',   'Keine generische Software, die angepasst werden muss. Jede Funktion wurde für die spezifischen Abläufe von Kolabri Getränke entwickelt.'],
    ['🔄', 'Ninox bleibt erhalten',       'Bestehende Ninox-Daten werden importiert und weitergenutzt. Kein Datenverlust beim Umstieg — shoptour2 ergänzt Ninox, ersetzt es nicht sofort.'],
    ['🌙', 'Angenehm zu bedienen',        'Dark Mode, sauberes Design, deutsche Texte. Das Tool ist darauf ausgelegt, von echten Menschen täglich genutzt zu werden — nicht nur von IT-Profis.'],
    ['📈', 'Wächst mit dem Betrieb',      'Neue Funktionen können jederzeit ergänzt werden. Die Architektur ist modular — Urlaubsverwaltung, Lohnabrechnung oder App sind erweiterbar.'],
    ['🔒', 'Datenschutz ab Werk',         'DSGVO-konforme Datenschutzerklärung, PIN-Hashing, CSRF-Schutz, rollenbasierter Zugriff. Personaldaten bleiben auf dem eigenen Server.'],
];
@endphp

{{-- Hero --}}
<div class="feat-hero">
    <h1>Kolabri Portal — Funktionsübersicht</h1>
    <p>
        Das Kolabri Portal ist die zentrale Betriebsplattform für Kolabri Getränke.
        Es vereint Mitarbeiterverwaltung, Onlineshop, Stempeluhr, Schichtberichte,
        Ninox-Integration und Kundenverwaltung in einem einheitlichen System —
        maßgeschneidert für die täglichen Abläufe des Unternehmens.
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.25rem;">
        @foreach([['Laravel 11','#3b82f6'],['Tailwind CSS v4','#06b6d4'],['Alpine.js','#8b5cf6'],['Ninox-Integration','#10b981'],['DSGVO-konform','#f59e0b'],['Dark Mode','#64748b']] as [$label,$color])
            <span style="padding:.2rem .65rem;border-radius:999px;font-size:.75rem;font-weight:700;background:color-mix(in srgb,{{ $color }} 20%,transparent);color:{{ $color }};">{{ $label }}</span>
        @endforeach
    </div>
</div>

{{-- ─── Aktuelle Features ─────────────────────────────────────────────── --}}
<div class="section-heading">Aktuelle Features</div>

<div class="feat-grid">
    @foreach($features as $f)
    <div class="feat-card">
        <div class="feat-card-header">
            <div class="feat-icon">{!! $f['icon'] !!}</div>
            <div class="feat-card-title">{!! $f['title'] !!}</div>
        </div>
        <div class="feat-date">
            Verfügbar seit <span>{{ \Carbon\Carbon::parse($f['since'])->locale('de')->isoFormat('D. MMM YYYY') }}</span>
            @if($f['updated'])
                &nbsp;·&nbsp; Zuletzt aktualisiert <span>{{ \Carbon\Carbon::parse($f['updated'])->locale('de')->isoFormat('D. MMM YYYY') }}</span>
            @endif
        </div>
        <ul>
            @foreach($f['items'] as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
    </div>
    @endforeach
</div>

{{-- ─── Roadmap ───────────────────────────────────────────────────────── --}}
<div class="section-heading">Was noch aussteht &amp; optimiert werden könnte</div>

<div class="roadmap-card">
    @foreach($roadmap as [$dot, $title, $desc])
    <div class="roadmap-item">
        <div class="roadmap-dot {{ $dot }}"></div>
        <div><strong style="color:var(--c-text)">{!! $title !!}</strong> — {!! $desc !!}</div>
    </div>
    @endforeach
</div>

<div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.8rem;color:var(--c-muted);margin-bottom:2rem;">
    <span style="display:flex;align-items:center;gap:.4rem;"><span style="width:8px;height:8px;border-radius:50%;background:#6366f1;display:inline-block;"></span> Geplante neue Funktion</span>
    <span style="display:flex;align-items:center;gap:.4rem;"><span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;"></span> Optimierung bestehender Funktion</span>
    <span style="display:flex;align-items:center;gap:.4rem;"><span style="width:8px;height:8px;border-radius:50%;background:#64748b;display:inline-block;"></span> Langfristige Vision</span>
</div>

{{-- ─── Vorteile ────────────────────────────────────────────────────── --}}
<div class="section-heading">Warum dieses Tool?</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:2rem;">
    @foreach($benefits as [$icon, $title, $desc])
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:1.25rem;">
        <div style="font-size:1.5rem;margin-bottom:.5rem;">{{ $icon }}</div>
        <div style="font-size:.92rem;font-weight:700;color:var(--c-text);margin-bottom:.4rem;">{{ $title }}</div>
        <div style="font-size:.82rem;color:var(--c-muted);line-height:1.55;">{{ $desc }}</div>
    </div>
    @endforeach
</div>

<div style="font-size:.75rem;color:var(--c-muted);text-align:right;margin-bottom:1rem;">
    Seite zuletzt generiert: {{ now()->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr
</div>

@endsection
