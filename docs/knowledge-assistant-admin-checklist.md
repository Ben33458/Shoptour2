# Wissensassistent — Admin-Checkliste

## Ersteinrichtung (einmalig)

### 1. DNS umstellen
- [ ] A-Record `wiki.kolabri.de` auf `89.167.121.25` ändern (aktuell zeigt er auf `91.210.225.5`)
- [ ] Nach DNS-Propagation (~5–15 min) SSL-Zertifikat ausstellen:
  ```bash
  docker exec wordpress-nginx certbot certonly --webroot \
    -w /var/www/certbot -d wiki.kolabri.de \
    --email admin@kolabri.de --agree-tos --non-interactive
  ```
- [ ] Nginx-SSL-Block in `/srv/wordpress/nginx/conf.d/kolabri-ssl.conf` anpassen:
  Kommentar `# TODO` entfernen und Zeilen tauschen:
  ```nginx
  # vorher (self-signed):
  ssl_certificate     /etc/letsencrypt/self-signed/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/self-signed/privkey.pem;

  # nachher (Let's Encrypt):
  ssl_certificate     /etc/letsencrypt/live/wiki.kolabri.de/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/wiki.kolabri.de/privkey.pem;
  ```
- [ ] Nginx neu laden: `docker exec wordpress-nginx nginx -s reload`

### 2. OpenAI API-Key
- [ ] API-Key beschaffen: https://platform.openai.com/api-keys
- [ ] In `/srv/shoptour2/.env` eintragen:
  ```
  OPENAI_API_KEY=sk-proj-...
  ```
- [ ] Container neu starten: `cd /srv/shoptour2 && docker compose restart app scheduler`

### 3. BookStack KI-Service-User anlegen
- [ ] BookStack unter https://wiki.kolabri.de aufrufen
- [ ] Einloggen als Admin (Standard: `admin@admin.com` / `password` — sofort ändern!)
- [ ] Unter **Settings → Users → Add User** einen neuen Benutzer anlegen:
  - Name: `KI-Service`
  - E-Mail: `ki-service@kolabri.intern`
  - Rolle: `API User` (nur Lese-Zugriff auf Bücher/Seiten)
- [ ] Unter **Profil → API Tokens** einen neuen Token anlegen:
  - Name: `shoptour2-wissensassistent`
  - Ablaufdatum: weit in der Zukunft
- [ ] Token ID und Secret in `/srv/shoptour2/.env` eintragen:
  ```
  KNOWLEDGE_BOOKSTACK_TOKEN_ID=token-id-hier
  KNOWLEDGE_BOOKSTACK_TOKEN_SECRET=token-secret-hier
  ```
- [ ] Container neu starten (s.o.)

### 4. BookStack-Buchstruktur anlegen
In BookStack empfohlene Bücher erstellen (Shelf → Books):

**Shelf: Kolabri-Intern**
- [ ] `Markt & Verkauf` — Verkaufsprozesse, Kasse, Vollgut
- [ ] `Lieferservice` — Touren, Abläufe, Sonderfälle
- [ ] `Festbedarf` — Veranstaltungsbestellungen, Leihinventar
- [ ] `Verwaltung` — Prozesse, Formulare, Ansprechpartner
- [ ] `Technik & IT` — Systeme, Zugangsdaten (KEIN Klartext!), Anleitungen
- [ ] `Sortiment` — Produktwissen, Lieferanten, Aktionen
- [ ] `Notfall & Ausfall` — Notfallpläne, Vertretungen
- [ ] `Entwürfe (KI)` — KI-generierte Entwürfe, noch zu prüfen

### 5. Paperless-Tags prüfen
- [ ] In Paperless-Admin prüfen, dass diese Tags existieren:
  - `ki-freigabe` — Pflicht für alle zu indexierenden Dokumente
  - `ki-preisliste` — für Preislisten
  - `ki-lmiv` — für Lebensmittelinformationsverordnung
  - `ki-technik` — für technische Dokumente
  - `ki-intern` — allgemein intern
  - `ki-entwurf` — noch nicht freigegeben
- [ ] Oder automatisch anlegen lassen:
  ```bash
  docker exec shoptour2-app php artisan knowledge:test
  ```

### 6. Verbindungen testen
```bash
docker exec shoptour2-app php artisan knowledge:test
```
Erwartet: ✓ für Qdrant, OpenAI, Paperless, BookStack

### 7. Ersten Sync starten
```bash
docker exec shoptour2-app php artisan knowledge:sync --source=all
```
Oder im Admin-Bereich unter **Wissensassistent → Sync starten**.

---

## Laufender Betrieb

### Nightly Sync
Läuft automatisch täglich um 04:00 Uhr. Protokoll unter **Admin → Wissensassistent → Sync-Verlauf**.

### Neue Dokumente freigeben (Paperless)
1. Dokument in Paperless öffnen
2. Tag `ki-freigabe` hinzufügen
3. Optionale Tags: `ki-preisliste`, `ki-lmiv`, `ki-technik`
4. Beim nächsten Nightly Sync (oder manuell) wird es indexiert

### Dokument aus Index entfernen
- Tag `ki-freigabe` in Paperless entfernen → nächster Sync löscht es aus Qdrant
- Oder direkt im Admin-Bereich **Wissensassistent → Quellen → Entfernen**

### Wissenslücken bearbeiten
1. Admin-Bereich → Wissensassistent → Wissenslücken
2. Lücke als `in_progress` markieren
3. Entwurf in BookStack erstellen (manuell oder per "Entwurf erstellen"-Button)
4. Entwurf in BookStack prüfen, korrigieren und veröffentlichen
5. Lücke als `resolved` markieren

### KI-Entwürfe in BookStack
- Alle KI-erstellten Entwürfe sind im Buch `Entwürfe (KI)` zu finden
- Erkennbar am Marker `<!-- KI-Entwurf – bitte prüfen -->` am Anfang
- **Immer vor Veröffentlichung menschlich prüfen!**
- Faktische Fehler, veraltete Preise, fehlende LMIV-Angaben manuell korrigieren

---

## Troubleshooting

### Sync schlägt fehl
```bash
# Logs prüfen
docker exec shoptour2-app php artisan tinker
>>> \App\Models\Knowledge\KnowledgeSyncRun::latest()->first()->error_log
```

### Qdrant nicht erreichbar
```bash
# Aus dem App-Container testen
docker exec shoptour2-app curl -s http://qdrant:6333/collections \
  -H "api-key: $(docker exec shoptour2-app php artisan tinker --execute="echo config('knowledge.qdrant_api_key');")"
```

### BookStack Verbindungsfehler
```bash
docker exec shoptour2-app php artisan knowledge:test
```

### Embedding schlägt fehl
- OpenAI API-Key in `.env` prüfen: `OPENAI_API_KEY=sk-...`
- Account-Guthaben auf https://platform.openai.com prüfen

### Antworten basieren nicht auf internen Quellen
1. Prüfen ob Dokumente mit `ki-freigabe` existieren
2. Sync manuell ausführen
3. Qdrant-Collection prüfen:
   ```bash
   docker exec shoptour2-app php artisan knowledge:test
   ```

---

## Kosten (Orientierung)

| Aktion | OpenAI-Modell | Kosten/Einheit |
|--------|--------------|----------------|
| Embedding (Sync) | text-embedding-3-small | ~$0.02/1M Tokens |
| Antwort generieren | gpt-4o-mini | ~$0.15/1M Input, $0.60/1M Output |

Bei ~100 Dokumente à 2000 Wörter: ca. $0.10 für die erste Indexierung.
Bei ~50 Anfragen/Tag: ca. $0.20/Tag.
