# Wissensassistent — Technische Dokumentation (PROJ-40)

## Übersicht

Der Wissensassistent ist ein RAG-basiertes (Retrieval-Augmented Generation) System, das Mitarbeitern ermöglicht, interne Wissensdatenbanken über einen natürlichsprachlichen Chat zu befragen. Er indiziert freigegebene Dokumente aus Paperless-ngx und BookStack, speichert Embeddings in Qdrant und beantwortet Fragen via GPT-4o-mini auf Basis der gefundenen internen Quellen.

---

## Infrastruktur

### Qdrant (Vektor-Datenbank)
- **Container:** `qdrant` im Docker-Netzwerk `kolabri-ai_default`
- **Intern erreichbar:** `http://qdrant:6333`
- **Extern gesperrt:** Port nur auf `127.0.0.1:6333` gebunden
- **API-Key:** In `/srv/kolabri-ai/.env` als `QDRANT_API_KEY`
- **Collection:** `kolabri_knowledge` (1536 Dimensionen, Cosine-Ähnlichkeit)
- **Tenant-ID:** `kolabri` (für zukünftige Mandantentrennung)

### BookStack (Wiki)
- **Container:** `bookstack` unter `/srv/bookstack/`
- **URL:** `https://wiki.kolabri.de`
- **Zugangskontrolle:** HTTP Basic Auth auf nginx-Ebene (zusätzlich zu BookStack-Login)
- **Netzwerk:** `bookstack_default`, nginx-Container ist in beiden Netzwerken

### ShopTour2 ↔ Qdrant
- ShopTour2 `app`- und `scheduler`-Container sind im `kolabri_ai`-Netzwerk (`kolabri-ai_default`) registriert
- Direkte Verbindung zu Qdrant ohne öffentliche Exposition

---

## Konfiguration

Alle Einstellungen in `/srv/shoptour2/.env`:

```env
KNOWLEDGE_ENABLED=true
KNOWLEDGE_TENANT_ID=kolabri

# OpenAI
OPENAI_API_KEY=sk-...
KNOWLEDGE_OPENAI_EMBEDDING_MODEL=text-embedding-3-small
KNOWLEDGE_OPENAI_CHAT_MODEL=gpt-4o-mini

# Qdrant
KNOWLEDGE_QDRANT_HOST=qdrant
KNOWLEDGE_QDRANT_PORT=6333
KNOWLEDGE_QDRANT_API_KEY=...
KNOWLEDGE_QDRANT_COLLECTION=kolabri_knowledge

# Paperless-ngx
KNOWLEDGE_PAPERLESS_URL=http://paperless:8000
KNOWLEDGE_PAPERLESS_TOKEN=...

# BookStack
KNOWLEDGE_BOOKSTACK_URL=https://wiki.kolabri.de
KNOWLEDGE_BOOKSTACK_TOKEN_ID=...
KNOWLEDGE_BOOKSTACK_TOKEN_SECRET=...
```

Laravel-Config: `config/knowledge.php`

---

## Datenbank-Schema

### `knowledge_sources`
Quellkonfigurationen (Paperless, BookStack, intern).

### `knowledge_documents`
Indizierte Dokumente mit `content_hash` für Delta-Sync.

### `knowledge_chunks`
Text-Chunks (400 Wörter, 50 Overlap) mit `chunk_hash` und Qdrant-Punkt-ID (`qdrant_point_id`).

### `knowledge_sync_runs`
Protokoll aller Sync-Läufe mit Statistiken (added/updated/skipped/deleted/errors).

### `knowledge_queries`
Geloggte Anfragen mit `question_type`, `has_customer_data`, `has_invoice_data`.

### `knowledge_query_sources`
Zugeordnete Quellen zu jeder Anfrage (Score, Snippet, Metadaten).

### `knowledge_feedback`
Nutzer-Reaktionen auf Antworten (10 Typen, z.B. `helpful`, `wrong`, `outdated`).

### `knowledge_gaps`
Gemeldete Wissenslücken mit Status (`open`, `in_progress`, `draft_created`, `resolved`, `ignored`).

### `knowledge_drafts`
Protokoll erstellter BookStack-Entwürfe (immer `draft=true`, nie auto-published).

---

## Services

### `PaperlessConnector`
- Ruft alle Dokumente mit Tag `ki-freigabe` ab (Generator, paginiert)
- Setzt `is_price_list=true` wenn Tag `ki-preisliste` vorhanden
- Setzt `is_lmiv=true` wenn Tag `ki-lmiv` vorhanden
- `ensureTags()` legt fehlende Tags automatisch an: `ki-freigabe`, `ki-preisliste`, `ki-lmiv`, `ki-technik`, `ki-intern`, `ki-entwurf`

### `BookStackConnector`
- `fetchPages()` — lädt alle Seiten paginiert
- `createDraftPage()` — erstellt **immer** als Entwurf (`draft=true`), nie als veröffentlichte Seite

### `QdrantService`
- `ensureCollection()` — legt Collection an falls nicht vorhanden
- `upsertPoints()` — Batch-Upsert
- `deletePoints()` — Delete nach IDs
- `deleteByFilter()` — Delete nach Payload-Feldern
- `search()` — Semantische Suche mit optionalem Filter

### `ChunkingService`
- 400 Wörter pro Chunk, 50 Wörter Überlappung
- `hash()` — SHA-256 des Chunk-Textes
- `contentHash()` — SHA-256 des Gesamtinhalts (für Delta-Sync)

### `EmbeddingService`
- OpenAI `text-embedding-3-small` (1536 Dimensionen)
- `embedBatch()` — bis zu 2048 Texte pro Aufruf
- Gibt leeres Array zurück wenn kein API-Key konfiguriert

### `IndexingService`
- `indexDocument()` — Delta-Sync via `content_hash`: überspringt unveränderte Dokumente
- Löscht alte Chunks aus Qdrant vor Neuindexierung
- Gibt `['added'=>N, 'updated'=>N, 'skipped'=>N, 'errors'=>N]` zurück

### `KnowledgeQueryService`
- Erkennt Fragetyp: `knowledge` vs. `data` (live-Daten)
- Erkennt Kundendaten-Anfragen (`has_customer_data`) und Rechnungsanfragen (`has_invoice_data`) — **vor** dem Embedding, damit auch bei fehlendem API-Key korrekt geloggt wird
- Baut Qdrant-Filter aus UI-Filter (alle/wiki/dokumente/preislisten/technik)
- Generiert Antwort mit System-Prompt (Preislisten-Datum, LMIV-Pflicht, kein Erfinden)
- Audit-Log für sensitive Queries in `daily`-Log-Kanal

---

## Artisan Commands

```bash
# Nightly Sync (automatisch 04:00)
php artisan knowledge:sync
php artisan knowledge:sync --source=paperless
php artisan knowledge:sync --source=bookstack

# Vollständige Neuindexierung
php artisan knowledge:reindex
php artisan knowledge:reindex --source=paperless

# Verbindungstests
php artisan knowledge:test

# CLI-Testabfrage
php artisan knowledge:query "Wie läuft Vollgut-Rückgabe?"

# Alte Queries aufräumen (Standard: 90 Tage)
php artisan knowledge:cleanup --days=90
```

---

## Nightly Scheduler

In `routes/console.php`:
```php
Schedule::command('knowledge:sync --source=all')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('knowledge:cleanup --days=90')
    ->weeklyOn(0, '05:00')
    ->withoutOverlapping();
```

---

## Sicherheit

- **Qdrant:** Nur intern erreichbar (127.0.0.1:6333), API-Key Pflicht
- **BookStack:** HTTP Basic Auth auf nginx-Ebene als zweite Schicht
- **Kein öffentliches Indexieren:** Nur Paperless-Dokumente mit `ki-freigabe`-Tag
- **Kein Auto-Publish:** BookStack-Entwürfe immer mit `draft=true`
- **Kein Senden an OpenAI ohne Freigabe:** IndexingService prüft `source.approved` vor API-Call
- **Sensitive Queries:** `has_customer_data` und `has_invoice_data` werden separat geloggt

---

## Routen

| Methode | Route | Name | Beschreibung |
|---------|-------|------|-------------|
| GET | `/mitarbeiter/wissensassistent` | `employee.knowledge.chat` | Chat-UI |
| POST | `/mitarbeiter/wissensassistent/fragen` | `employee.knowledge.ask` | Frage stellen |
| POST | `/mitarbeiter/wissensassistent/feedback/{query}` | `employee.knowledge.feedback` | Reaktion |
| POST | `/mitarbeiter/wissensassistent/luecke` | `employee.knowledge.gap` | Wissenslücke |
| POST | `/mitarbeiter/wissensassistent/entwurf` | `employee.knowledge.draft` | Entwurf erstellen |
| GET | `/admin/knowledge` | `admin.knowledge.index` | Admin-Übersicht |
| GET | `/admin/knowledge/luecken` | `admin.knowledge.gaps` | Lücken-Liste |
| POST | `/admin/knowledge/sync` | `admin.knowledge.sync` | Sync auslösen |
| POST | `/admin/knowledge/reindex` | `admin.knowledge.reindex` | Neuindexierung |
| DELETE | `/admin/knowledge/dokument/{document}` | `admin.knowledge.remove` | Quelle entfernen |
| GET | `/admin/knowledge/verbindung-testen` | `admin.knowledge.test` | Verbindungstests |
| PATCH | `/admin/knowledge/luecken/{gap}` | `admin.knowledge.gaps.resolve` | Lücke auflösen |

---

## Tests

```bash
docker exec shoptour2-app php artisan test tests/Feature/Knowledge/
```

5 Test-Klassen, 25 Tests:
- `ChunkingServiceTest` — Chunking-Logik
- `QdrantServiceTest` — Qdrant HTTP-Aufrufe (Http::fake)
- `PaperlessConnectorTest` — Paperless API-Filterung
- `KnowledgeAccessTest` — Auth/Zugangskontrolle
- `KnowledgeLoggingTest` — Query-Logging, Sensitive-Data-Flags, Feedback, Gaps
