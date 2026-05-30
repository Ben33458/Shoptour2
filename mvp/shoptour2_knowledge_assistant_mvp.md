# MVP-Arbeitsauftrag: ShopTour2 Wissensassistent mit BookStack, Paperless und Qdrant

## Ziel

Baue für ShopTour2 einen internen **Wissensassistenten** für Mitarbeiter.

Der Assistent soll interne Fragen beantworten, relevante Quellen inline anzeigen und Wissenslücken erfassen. Die Wissensbasis besteht aus:

- BookStack als zentrale Wissensdatenbank
- Paperless-ngx als Dokumentenarchiv
- Qdrant als interner Vektorindex
- ausgewählten ShopTour2-Datenbankdaten für Live-Datenfragen
- OpenAI für Embeddings und Antwortgenerierung

Wichtig: Das alte bestehende MediaWiki darf **nicht** indexiert, migriert oder gelesen werden. Dort ist privater Inhalt enthalten.

---

## 1. Harte Vorgaben

### 1.1 Nicht anfassen

Folgende Systeme/Inhalte dürfen nicht automatisch gelesen, indexiert oder migriert werden:

- bestehendes altes MediaWiki
- private Dokumente
- Paperless-Dokumente ohne Freigabe-Tag
- Lohn-/Bank-/Steuer-/Privatdokumente
- technische Markdown-Dateien für Mitarbeiter-KI

Technische Markdown-Dateien auf dem Server dürfen höchstens später für eine separate Entwickler-/Admin-KI genutzt werden, aber **nicht** für den Mitarbeiter-Wissensassistenten.

### 1.2 OpenAI nur mit freigegebenen Inhalten

OpenAI darf verwendet werden für:

- Embeddings
- Antwortgenerierung

Aber nur mit freigegebenen Inhalten.

Keine nicht freigegebenen Paperless-Dokumente an OpenAI senden.

---

## 2. Zielarchitektur

```text
ShopTour2 Mitarbeiterbereich
        ↓
Menüpunkt: Wissensassistent
        ↓
Laravel Knowledge-Modul
        ↓
Qdrant interner Vektorindex
        ↓
BookStack + Paperless + ausgewählte ShopTour2-Daten
        ↓
OpenAI für Embeddings und Antwortgenerierung
```

### V1-Quellen

- BookStack-Seiten
- Paperless-Dokumente mit Tag `ki-freigabe`
- ausgewählte ShopTour2-Datenbankdaten
- Preislisten in Paperless, aber nur als nicht verbindliche Quelle
- LMIV-Dokumente, wenn freigegeben

### Spätere Quellen

- JTL/Wawi-Exporte
- Ninox-Exporte
- Google Drive / T-Drive, bevorzugt erst nach Export nach BookStack oder Paperless
- E-Mails, sobald E-Mail-Anbindung existiert

---

## 3. Serverprüfung

Prüfe zuerst die vorhandene Serverstruktur.

Ermitteln:

- Wo läuft Paperless?
- Läuft Paperless in Docker, Docker Compose oder Coolify?
- Wo liegt ShopTour2?
- Laravel-Version
- PHP-Version
- MySQL-Version
- vorhandene Docker-Netzwerke
- vorhandene Reverse-Proxy-Struktur
- vorhandene Domains/Subdomains
- ob Coolify vorhanden ist
- ob Traefik/Nginx genutzt wird

Entscheide danach passend, ob BookStack und Qdrant per Docker Compose oder Coolify-Service eingerichtet werden.

Keine bestehenden Dienste beschädigen.

---

## 4. BookStack installieren

### 4.1 Subdomain

BookStack soll unter folgender Subdomain laufen:

```text
wiki.kolabri.de
```

### 4.2 Zugriffssicherheit

BookStack darf öffentlich erreichbar sein, aber **nicht nur mit Benutzername und Passwort**.

Zusätzliche Zugriffsschicht erforderlich.

Prüfe und setze eine sinnvolle Variante um:

- Cloudflare Access
- Authentik
- Authelia
- vergleichbare Lösung

Ziel:

- Zugriff nur für berechtigte Mitarbeiter
- idealerweise Gerätefreigabe oder E-Mail-/Einmalcode-Authentifizierung
- kein frei erreichbares Login nur mit Benutzername und Passwort
- BookStack selbst nicht ungeschützt direkt exponieren

### 4.3 Rollenmodell

In BookStack folgende Rollen vorbereiten:

```text
Admin
- lesen
- schreiben
- verwalten
- veröffentlichen

Marktleitung / Büroleitung
- lesen
- schreiben
- veröffentlichen

Normale Mitarbeiter
- lesen
- Entwürfe sehen
- Entwürfe kommentieren

Fahrer / Aushilfen
- lesen
- Entwürfe sehen
- Entwürfe kommentieren

KI-Service-User
- lesen
- API-Zugriff
```

### 4.4 Grundstruktur automatisch anlegen

BookStack soll initial folgende Struktur erhalten:

```text
Markt
- Kasse
- Leergut
- Paketshop
- Reklamationen

Lieferservice
- Tourablauf
- Zahlungsarten
- Kunde nicht da
- Vollgut-Rücknahme

Festbedarf
- Anfrage vs. Bestellung
- Angebot erstellen
- Kühlanhänger
- Mietartikel-Rückgabe

Verwaltung
- Mahnungen
- Lexoffice
- Dext
- Personal

Technik
- JTL
- LS-POS
- Ninox
- ShopTour2
- n8n

Sortiment
- Pfand
- LMIV
- MHD
- Altersprüfung

Notfall
- Stromausfall
- Kühlung defekt
- Fahrzeugausfall
- EC-Gerät defekt

Entwürfe
- KI-Entwürfe
- Mitarbeitervorschläge
- Wissenslücken
```

### 4.5 Entwürfe

Der Bereich `Entwürfe` soll für normale Mitarbeiter sichtbar sein.

Mitarbeiter dürfen kommentieren.

Veröffentlichen dürfen nur:

- Admin
- Marktleitung
- Büroleitung

Jeder KI-generierte Entwurf muss klar markiert werden:

```text
KI-Entwurf – bitte prüfen
```

---

## 5. Paperless-Anbindung

Paperless ist bereits installiert und enthält viele Dokumente.

### 5.1 Freigabe-Regel

Nur Paperless-Dokumente mit folgendem Tag dürfen indexiert werden:

```text
ki-freigabe
```

Ohne diesen Tag keine Indexierung.

### 5.2 Paperless-Tags anlegen

Prüfe, ob folgende Tags existieren. Falls nicht, anlegen:

```text
ki-freigabe
ki-mitarbeiter
ki-admin
ki-preisliste
ki-lmiv
ki-technik
```

### 5.3 V1-Sichtbarkeit

Für V1 dürfen alle eingeloggten internen ShopTour2-Benutzer alle Dokumente mit `ki-freigabe` sehen.

Die weiteren Tags sollen schon als Payload/Metadaten gespeichert werden, damit spätere Rollenfilter möglich sind.

### 5.4 Zu indexierende Metadaten

Für Paperless-Dokumente indexieren:

- Titel
- Korrespondent
- Dokumenttyp
- Tags
- Archivdatum
- Erstelldatum
- Änderungsdatum
- OCR-Text
- Paperless-Dokument-ID
- interner Link
- Dokumentdatum
- Gültigkeit, falls erkennbar
- Preislisten-Metadaten, falls vorhanden

### 5.5 Quelle inline öffnen

Paperless-Quellen sollen im ShopTour2-Wissensassistenten inline angezeigt werden.

Nicht standardmäßig in neuem Tab öffnen.

Quelle anzeigen:

- relevanter Ausschnitt zuerst
- Button: vollständige Quelle anzeigen
- optional Admin-Link zum Original

---

## 6. Qdrant installieren

### 6.1 Betrieb

Qdrant soll auf demselben Server laufen wie ShopTour2/Paperless.

Qdrant darf nicht öffentlich erreichbar sein.

Zugriff nur:

- intern im Docker-Netzwerk oder lokal
- mit API-Key abgesichert

### 6.2 Backup

Kein separates Qdrant-Backup nötig.

Begründung: Qdrant ist rekonstruierbar aus BookStack, Paperless und ShopTour2-Daten.

### 6.3 Collection

Für V1 reicht eine Collection:

```text
kolabri_knowledge
```

Payload-Felder:

```text
tenant_id
source_type
source_id
source_document_id
source_chunk_id
title
url
tags
visibility
permissions
created_at
updated_at
indexed_at
content_hash
chunk_hash
document_date
valid_from
valid_until
source_system
source_category
is_price_list
is_lmiv
is_technical
```

Vorgesehene `source_type`:

```text
bookstack
paperless
shoptour2
price_list
lmiv
manual
```

Mandantenfähigkeit vorbereiten über:

```text
tenant_id
```

Default:

```text
kolabri
```

---

## 7. ShopTour2 Knowledge-Modul

Baue ein eigenes Laravel-Modul innerhalb von ShopTour2.

Name:

```text
Knowledge
```

Menüpunkt im Mitarbeiterbereich:

```text
Wissensassistent
```

Nur eingeloggte interne ShopTour2-Benutzer dürfen den Wissensassistenten nutzen.

V1: alle internen Rollen dürfen ihn nutzen.

---

## 8. Wissensfrage vs. Datenfrage

Das Modul muss zwischen zwei Fragetypen unterscheiden.

### 8.1 Wissensfrage

Beispiele:

```text
Was mache ich, wenn Kunde nicht da ist?
Wie läuft eine Veranstaltungsanfrage?
Wie buche ich Vollgut-Rückgabe?
Was tun bei EC-Gerät defekt?
Wo finde ich LMIV-Daten?
Wie prüfe ich eine Preisliste?
```

Antwort aus:

- Qdrant
- BookStack
- Paperless
- freigegebenen Preislisten/LMIV-Dokumenten

### 8.2 Datenfrage

Beispiele:

```text
Was kostet Artikel X aktuell?
Welche Adresse hat Kunde Y?
Hat Kunde Z offene Rechnungen?
Wie viel Bestand haben wir von Artikel X?
```

Antwort aus:

- Live-ShopTour2-Datenbank/API
- nicht aus alten PDF-/OCR-Daten

### 8.3 Pflicht

Aktuelle strukturierte Daten müssen live abgefragt werden, nicht aus dem Vektorindex.

Beispiel:

```text
"Wie läuft Vollgut-Rückgabe?" → Qdrant/Wiki
"Was kostet Bionade Holunder aktuell?" → ShopTour2/JTL/Preisdaten live
```

---

## 9. Erlaubte ShopTour2-Daten in V1

Folgende Daten dürfen in V1 angebunden werden:

```text
Artikelstammdaten
Tour-/Ablaufdaten
Mietartikel/Festbedarf
Preislisten-Metadaten
LMIV-Felder, falls vorhanden
Kundendaten
Offene Rechnungen/Mahnstatus
Bestände
aktuelle Preise
```

### 9.1 Kundendaten

Kundendaten dürfen für interne Benutzer beantwortet werden, aber mit Protokollierung.

Zu protokollieren:

```text
User-ID
Zeitpunkt
Frage
erkannter Kunde
Antwort
verwendete Datenquellen
```

### 9.2 Offene Rechnungen/Mahnstatus

Darf für alle internen Benutzer beantwortet werden.

Mit Protokollierung.

### 9.3 Bestände

Dürfen für alle internen Benutzer beantwortet werden.

Wenn Bestände unsicher oder veraltet sein können, Hinweis anzeigen.

### 9.4 Preise

Aktuelle Preise dürfen nur genannt werden, wenn sie aus strukturierten ShopTour2-/JTL-Daten kommen.

Antwort mit Hinweis:

```text
Aktueller Stand laut System: [Datum/Uhrzeit]
```

Preislisten aus Paperless dürfen gefunden und erklärt werden, sind aber nicht die verbindliche Preisquelle.

---

## 10. Preislisten-Regeln

### V1

Preislisten dürfen:

- in Paperless gespeichert werden
- mit `ki-freigabe` und `ki-preisliste` getaggt werden
- indexiert werden
- gefunden werden
- erklärt werden

Die KI darf aber keine verbindlichen aktuellen Preise aus OCR/PDF behaupten.

Bei Preislisten-Antworten immer anzeigen:

```text
Dokumentdatum
Gültigkeit / gültig ab / gültig bis, falls vorhanden
Quelle
Hinweis, dass aktuelle Preise im System geprüft werden müssen
```

Beispielhinweis:

```text
Diese Information stammt aus einer Preisliste mit Datum/Gültigkeit [X].
Verbindliche aktuelle Preise bitte in ShopTour2/JTL prüfen.
```

### Später

Strukturierter Preisimport in ShopTour2.

Dann:

```text
konkrete aktuelle Preise nur aus ShopTour2/JTL-Strukturdaten
```

---

## 11. LMIV-Regeln

LMIV-/Lebensmittelangaben nur mit Quelle anzeigen.

Wenn keine freigegebene interne Quelle vorhanden ist:

```text
Ich finde dazu keine freigegebene LMIV-Quelle.
```

Keine Zutaten, Allergene, Nährwerte oder Lebensmittelangaben erfinden.

LMIV ist ein Quellenpflicht-Bereich.

---

## 12. Antwortverhalten

Der Wissensassistent soll antworten:

- kurz
- praktisch
- Schritt-für-Schritt, wenn sinnvoll
- keine langen Romane
- bei Unsicherheit klar sagen
- interne Quellen sauber markieren
- allgemeines Wissen klar von internem Wissen trennen

Wenn keine interne Quelle gefunden wird:

```text
Ich finde dazu keine freigegebene interne Quelle. Allgemein gilt ...
```

Bei internen Prozessen, Preisen, Kundendaten, LMIV und rechtlich relevanten Angaben nicht frei raten.

---

## 13. Quellenanzeige

### 13.1 Quellen im Text

Antworten sollen Quellen direkt im Text markieren.

Beispiel:

```text
Bei Veranstaltungsanfragen wird zuerst ein Angebot erstellt [Quelle 1].
Nach Bestätigung wird daraus ein Auftrag [Quelle 2].
```

Quellenmarker in Kolabri-Blau darstellen.

### 13.2 Quellenliste

Unter der Antwort Quellenliste anzeigen:

```text
Quelle 1
Titel
System: BookStack/Paperless/ShopTour2
Datum/Gültigkeit
relevanter Ausschnitt
Button: Quelle anzeigen
```

### 13.3 Inline-Vorschau

Quelle anzeigen öffnet inline:

- Modal
- Drawer
- eingebettetes Panel

Nicht primär neuer Tab.

Zuerst relevanten Ausschnitt zeigen.

Button:

```text
Vollständige Quelle anzeigen
```

---

## 14. UI im ShopTour2-Mitarbeiterbereich

### 14.1 Menüpunkt

```text
Wissensassistent
```

### 14.2 Mobile-first

Die Oberfläche muss mobile-first sein.

Wichtig für:

- Fahrer
- Marktmitarbeiter
- Aushilfen
- Handy-Nutzung

### 14.3 Chatfunktionen V1

- Eingabefeld
- Antwortbereich
- Quellenmarker im Text
- Quellenliste
- Inline-Quellenvorschau
- Filter
- Schnellfragen
- Feedback-Reactions
- Button „Wissensseite fehlt“
- Button „Entwurf erzeugen“, wenn sinnvoll

### 14.4 Schlichte Filter

Filter schlicht halten:

```text
Alle
Wiki
Dokumente
Preislisten
Technik
```

Optional später:

```text
LMIV
Festbedarf
Lieferservice
Markt
```

### 14.5 Schnellfragen

Initiale Schnellfragen:

```text
Was mache ich, wenn Kunde nicht da ist?
Wie läuft eine Veranstaltungsanfrage?
Wie buche ich Vollgut-Rückgabe?
Was tun bei EC-Gerät defekt?
Wo finde ich LMIV-Daten?
Wie prüfe ich eine Preisliste?
```

---

## 15. Feedback/Reactions

Unter jeder Antwort Reactions anzeigen.

V1-Reactions:

```text
👍 hilfreich
👎 falsch
🧩 unvollständig
🕒 veraltet
⚠️ kritisch
💡 gute Idee
📌 als Regel übernehmen
🧯 rettet gerade den Laden
🤨 klingt fragwürdig
🗑️ kann weg
```

Die Reactions sollen ähnlich wie Social-Media-Reaktionen unter der Antwort gesammelt angezeigt werden.

Speichern:

```text
User-ID
Antwort-ID
Reaction
Kommentar optional
Zeitpunkt
```

---

## 16. Wissenslücken

### 16.1 Button

Button im Chat:

```text
Wissensseite fehlt
```

Mitarbeiter darf Kommentar eingeben.

Beispiel:

```text
Wir brauchen eine Anleitung, was bei defektem EC-Gerät zu tun ist.
```

### 16.2 ShopTour2-Adminseite

Baue Adminseite:

```text
Wissenslücken
```

Dort anzeigen:

- Fragen ohne gute Treffer
- negatives Feedback
- veraltete Antworten
- unvollständige Antworten
- häufige Fragen ohne gute Quelle
- Vorschläge für neue Wissensseiten
- als Regel übernehmen markierte Antworten

### 16.3 FAQ/Top-Fragen-Auswertung

Zusätzlich einfache Auswertung:

- häufig gestellte Fragen
- häufig genutzte Quellen
- häufige Wissenslücken
- Quellen mit vielen „veraltet“-Reaktionen
- Quellen mit vielen „falsch“-Reaktionen

---

## 17. BookStack-Entwürfe

Die KI darf neue BookStack-Seiten nur als Entwurf anlegen.

Nicht direkt veröffentlichen.

### 17.1 Ablauf

Wenn Mitarbeiter „Wissensseite fehlt“ oder „als Regel übernehmen“ nutzt:

```text
Frage + Antwort + Quellen + Kommentar sammeln
→ KI erstellt Entwurf
→ Entwurf in BookStack-Bereich "Entwürfe"
→ Markierung "KI-Entwurf – bitte prüfen"
→ Mitarbeiter dürfen kommentieren
→ Admin/Marktleitung/Büroleitung kann veröffentlichen
```

### 17.2 Entwurfsstruktur

KI-Entwürfe sollen einheitlich aufgebaut werden:

```markdown
# Titel

> KI-Entwurf – bitte prüfen

## Zweck

## Wann anwenden?

## Schritt-für-Schritt

1.
2.
3.

## Fehler vermeiden

## Zuständig

## Quellen

## Offene Fragen

## Letzte Prüfung
Noch nicht geprüft
```

---

## 18. Adminbereich: Sync und Index

Baue Adminseite:

```text
Wissensassistent Verwaltung
```

Funktionen:

- letzter Sync
- Anzahl Quellen
- Anzahl Dokumente
- Anzahl Chunks
- Anzahl Fehler
- letzte Fehlerliste
- Button: Paperless syncen
- Button: BookStack syncen
- Button: alles neu indexieren
- Button: Qdrant-Verbindung testen
- Button: OpenAI-Verbindung testen
- Liste indexierter Quellen
- Quelle aus Index entfernen
- Reindex einzelner Quelle

### 18.1 Sync-Zeitplan

Automatischer Sync:

```text
nachts
```

Zusätzlich manuelle Buttons.

### 18.2 Artisan Commands

Erstelle Commands:

```bash
php artisan knowledge:sync --source=paperless
php artisan knowledge:sync --source=bookstack
php artisan knowledge:sync --source=shoptour2
php artisan knowledge:reindex
php artisan knowledge:query "Was mache ich bei Kunde nicht da?"
php artisan knowledge:cleanup
php artisan knowledge:test
```

### 18.3 Delta-Sync

Umsetzen:

- `updated_at` prüfen
- `content_hash`
- `chunk_hash`
- geänderte Inhalte neu indexieren
- gelöschte/enttaggte Paperless-Dokumente aus Qdrant entfernen

Wichtig:

Wenn ein Paperless-Dokument das Tag `ki-freigabe` verliert, muss es aus Qdrant entfernt werden.

---

## 19. Chunking

Lange Dokumente in sinnvolle Chunks zerlegen.

Anforderungen:

- Abschnitte möglichst semantisch trennen
- Titel/Metadaten jedem Chunk mitgeben
- Quelle eindeutig referenzieren
- Chunk-Hash speichern
- Content-Hash speichern
- Dubletten vermeiden

Chunk-Metadaten speichern:

```text
source_type
source_id
document_id
chunk_index
chunk_text
chunk_hash
content_hash
title
url
tags
visibility
document_date
valid_from
valid_until
```

---

## 20. Datenbanktabellen in ShopTour2

Erstelle sinnvolle Tabellen, z. B.:

```text
knowledge_sources
knowledge_documents
knowledge_chunks
knowledge_sync_runs
knowledge_queries
knowledge_query_sources
knowledge_feedback
knowledge_gaps
knowledge_drafts
```

### 20.1 knowledge_queries

Speichern:

```text
id
user_id
question
answer
question_type
used_internal_sources
used_live_data
created_at
```

### 20.2 knowledge_query_sources

Speichern:

```text
query_id
source_type
source_id
source_title
source_url
chunk_id
score
snippet
```

### 20.3 knowledge_feedback

Speichern:

```text
query_id
user_id
reaction
comment
created_at
```

### 20.4 knowledge_gaps

Speichern:

```text
id
user_id
question
comment
status
created_at
resolved_at
bookstack_draft_id
```

### 20.5 knowledge_sync_runs

Speichern:

```text
id
source
started_at
finished_at
status
documents_seen
documents_indexed
chunks_created
chunks_updated
chunks_deleted
errors
```

---

## 21. Sicherheit und Logging

### 21.1 Zugriff

Wissensassistent nur für eingeloggte interne ShopTour2-Benutzer.

### 21.2 Protokollierung

Jede KI-Frage protokollieren:

```text
User
Frage
Antwort
Quellen
Zeitpunkt
Fragetyp
Live-Daten genutzt ja/nein
```

Kundendaten, offene Rechnungen, Mahnstatus besonders protokollieren.

### 21.3 Kein Zugriff auf nicht freigegebene Dokumente

Paperless-Dokumente ohne `ki-freigabe` dürfen nicht:

- indexiert werden
- an OpenAI gesendet werden
- im Chat auftauchen
- als Quelle angezeigt werden

---

## 22. Installation und ENV

Ergänze `.env.example` um nötige Werte:

```env
KNOWLEDGE_ENABLED=true

QDRANT_URL=http://qdrant:6333
QDRANT_API_KEY=
QDRANT_COLLECTION=kolabri_knowledge

OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=
OPENAI_CHAT_MODEL=

PAPERLESS_URL=
PAPERLESS_API_TOKEN=

BOOKSTACK_URL=https://wiki.kolabri.de
BOOKSTACK_TOKEN_ID=
BOOKSTACK_TOKEN_SECRET=

KNOWLEDGE_TENANT_ID=kolabri
KNOWLEDGE_SYNC_SCHEDULE=nightly
```

Sichere Secrets nicht ins Repository committen.

---

## 23. Tests

Möglichst gründliche Tests schreiben.

Mindestens testen:

### Connectoren

- Paperless-Connector liest nur `ki-freigabe`
- BookStack-Connector liest korrekte Seiten
- Fehlerhafte API-Verbindung wird sauber geloggt

### Security

- nicht eingeloggte Benutzer kein Zugriff
- interne Benutzer Zugriff
- Dokument ohne `ki-freigabe` wird ignoriert
- enttaggtes Dokument wird aus Index entfernt

### Qdrant

- Upsert funktioniert
- Delete funktioniert
- Reindex funktioniert
- Query liefert Quellen zurück

### Chunking

- lange Dokumente werden geteilt
- Hashing funktioniert
- unveränderte Chunks werden nicht neu indexiert

### Antworten

- Quellen werden inline referenziert
- fehlende Quellen werden korrekt markiert
- LMIV ohne Quelle wird nicht erfunden
- Preislisten zeigen Datum/Gültigkeit
- Live-Daten zeigen Datenstand

### Logging

- normale Frage wird geloggt
- Kundendatenfrage wird geloggt
- offene Rechnungen/Mahnstatus wird geloggt
- Feedback wird gespeichert
- Wissenslücke wird gespeichert

### BookStack-Entwürfe

- Entwurf wird als Entwurf angelegt
- Markierung „KI-Entwurf – bitte prüfen“ vorhanden
- Veröffentlichung nicht automatisch

---

## 24. README / technische Dokumentation

Erstelle eine technische README:

```text
docs/knowledge-assistant.md
```

Inhalt:

- Ziel
- Architektur
- Dienste
- Docker/Coolify-Setup
- ENV-Variablen
- BookStack-Setup
- Paperless-Tags
- Qdrant-Setup
- OpenAI-Konfiguration
- Artisan Commands
- Scheduler
- Reindex
- Fehlerbehebung
- Sicherheitsregeln
- Preislistenregeln
- LMIV-Regeln
- Backup-/Restore-Hinweis

---

## 25. Admin-Checkliste

Erstelle zusätzlich eine kurze Checkliste:

```text
docs/knowledge-assistant-admin-checklist.md
```

Inhalt:

```markdown
# Admin-Checkliste Wissensassistent

1. BookStack unter wiki.kolabri.de erreichbar?
2. Zusätzliche Zugriffsschicht aktiv?
3. BookStack-Admin angelegt?
4. Rollen geprüft?
5. Grundstruktur vorhanden?
6. Paperless-Tags vorhanden?
7. Erste Dokumente mit ki-freigabe getaggt?
8. Qdrant-Verbindung erfolgreich?
9. OpenAI-Verbindung erfolgreich?
10. Erster Paperless-Sync erfolgreich?
11. Erster BookStack-Sync erfolgreich?
12. Testfrage gestellt?
13. Quellenanzeige geprüft?
14. Inline-Vorschau geprüft?
15. Preislistenhinweis geprüft?
16. LMIV-Sicherheitsregel geprüft?
17. Mitarbeiterzugriff getestet?
18. Feedback-Reactions getestet?
19. Wissenslücke erstellt?
20. BookStack-Entwurf erzeugt?
```

---

## 26. Akzeptanzkriterien

Der Auftrag gilt als fertig, wenn:

- BookStack unter `wiki.kolabri.de` läuft
- BookStack zusätzlich abgesichert ist, nicht nur Username/Passwort
- BookStack-Grundstruktur angelegt ist
- Qdrant intern läuft und nicht öffentlich erreichbar ist
- Qdrant per API-Key abgesichert ist
- Paperless-Tags angelegt sind
- nur Paperless-Dokumente mit `ki-freigabe` indexiert werden
- ShopTour2 ein Knowledge-Modul besitzt
- Menüpunkt `Wissensassistent` vorhanden ist
- nur eingeloggte interne ShopTour2-Benutzer Zugriff haben
- Chat mobile-first nutzbar ist
- Quellen im Text markiert werden
- Quellen inline angezeigt werden
- Feedback-Reactions funktionieren
- Wissenslücken erfasst werden
- BookStack-Entwürfe erzeugt werden können
- Entwürfe nicht automatisch veröffentlicht werden
- nächtlicher Sync eingerichtet ist
- manueller Sync/Reindex funktioniert
- Live-Datenfragen von Wissensfragen getrennt werden
- Kundendatenabfragen protokolliert werden
- offene Rechnungen/Mahnstatus protokolliert werden
- Preisantworten Datenstand anzeigen
- Preislisten Datum/Gültigkeit anzeigen
- LMIV-Angaben nie ohne Quelle erfunden werden
- README vorhanden ist
- Admin-Checkliste vorhanden ist
- Tests vorhanden und lauffähig sind
- altes MediaWiki nicht gelesen oder migriert wurde

---

## 27. Umsetzungshinweise

Arbeite vorsichtig und inkrementell.

Empfohlene Reihenfolge:

```text
1. Serverstruktur prüfen
2. Plan kurz dokumentieren
3. BookStack installieren und absichern
4. Qdrant installieren
5. Paperless-Tags prüfen/anlegen
6. Laravel Knowledge-Modul anlegen
7. Datenbankmigrationen erstellen
8. Paperless-Connector bauen
9. BookStack-Connector bauen
10. Chunking/Hashing bauen
11. Qdrant-Upsert/Delete bauen
12. Sync-Commands bauen
13. Admin-Sync-Seite bauen
14. Mitarbeiter-Chat bauen
15. Quellenanzeige inline bauen
16. Feedback/Wissenslücken bauen
17. BookStack-Entwürfe bauen
18. Live-Datenfrage-Grundlage bauen
19. Tests schreiben
20. README und Checkliste schreiben
```

Keine riskanten Änderungen an bestehenden Produktivdaten ohne vorherige Sicherung/Prüfung.

Keine Secrets committen.

Keine privaten Alt-Wiki-Inhalte anfassen.
