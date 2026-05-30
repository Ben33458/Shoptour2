# PROJ-40 — Wissensassistent (Knowledge Assistant)

**Status:** In Progress
**Created:** 2026-05-28

## Übersicht

RAG-basierter interner Wissensassistent für Mitarbeiter. Indexiert freigegebene Dokumente aus Paperless-ngx und BookStack, speichert Embeddings in Qdrant, und beantwortet Fragen via GPT-4o-mini auf Basis interner Quellen.

## Akzeptanzkriterien

- [x] Mitarbeiter und Admins können chat-basiert Fragen stellen
- [x] Unauthentifizierte Nutzer werden abgeblockt (401/302)
- [x] Nur Paperless-Dokumente mit Tag `ki-freigabe` werden indexiert
- [x] Qdrant läuft intern (127.0.0.1), API-Key geschützt
- [x] BookStack läuft unter wiki.kolabri.de mit HTTP Basic Auth vor dem Proxy
- [x] Nightly Sync via `knowledge:sync --source=all` um 04:00 Uhr
- [x] Delta-Sync: Dokumente nur bei geändertem `content_hash` neu indexiert
- [x] Entfernte/abgetagt Paperless-Dokumente werden aus Qdrant gelöscht
- [x] Preislisten-Datum und Gültigkeitswarnung wird angezeigt
- [x] LMIV-Daten nur mit Quellennachweis
- [x] Feedback (10 Reaktionen) wird gespeichert
- [x] Wissenslücken können gemeldet werden
- [x] KI-Entwürfe werden als Draft in BookStack erstellt (nie auto-publish)
- [x] Kundendaten- und Rechnungsabfragen werden speziell geloggt
- [x] Quelltyp-Filter (Alle / Wiki / Dokumente / Preislisten / Technik)
- [x] Admin-Bereich: Sync starten, Quellen verwalten, Lücken auflösen

## Technische Umsetzung

### Infrastruktur
- **Qdrant** `kolabri-ai_default`-Netzwerk, Collection `kolabri_knowledge` (1536 dim, Cosine)
- **BookStack** Docker unter `/srv/bookstack`, nginx-Proxy auf `wiki.kolabri.de`
- **ShopTour2** Container im `kolabri_ai`-Netzwerk für direkte Qdrant-Kommunikation

### Services
| Service | Datei | Funktion |
|---------|-------|----------|
| `PaperlessConnector` | `app/Services/Knowledge/` | Paperless REST API, filtert nach `ki-freigabe` |
| `BookStackConnector` | `app/Services/Knowledge/` | BookStack REST API, Drafts via `draft=true` |
| `QdrantService` | `app/Services/Knowledge/` | Upsert, Delete, Search, Collection-Init |
| `ChunkingService` | `app/Services/Knowledge/` | 400 Wörter, 50 Overlap, SHA-256 Hashes |
| `EmbeddingService` | `app/Services/Knowledge/` | OpenAI `text-embedding-3-small` |
| `IndexingService` | `app/Services/Knowledge/` | Delta-Sync, Paperless + BookStack |
| `KnowledgeQueryService` | `app/Services/Knowledge/` | RAG-Pipeline, Sensitive-Data-Erkennung |

### Commands
- `knowledge:sync [--source=all|paperless|bookstack]` — Nightly Sync
- `knowledge:reindex [--source=]` — Vollständige Neuindexierung
- `knowledge:test` — Verbindungstests
- `knowledge:query "frage"` — CLI-Test
- `knowledge:cleanup [--days=90]` — Alte Queries aufräumen

### Datenbank (9 Tabellen)
- `knowledge_sources`, `knowledge_documents`, `knowledge_chunks`
- `knowledge_sync_runs`
- `knowledge_queries`, `knowledge_query_sources`, `knowledge_feedback`
- `knowledge_gaps`, `knowledge_drafts`

### Routen
- `GET  /mitarbeiter/wissensassistent` — Chat-UI
- `POST /mitarbeiter/wissensassistent/fragen` — Frage stellen
- `POST /mitarbeiter/wissensassistent/feedback/{query}` — Reaktion
- `POST /mitarbeiter/wissensassistent/luecke` — Wissenslücke melden
- `POST /mitarbeiter/wissensassistent/entwurf` — BookStack-Entwurf erstellen
- `GET  /admin/knowledge/` — Admin-Übersicht
- `GET  /admin/knowledge/luecken` — Lücken-Liste

## Offene TODOs (nach Go-Live)

1. DNS `wiki.kolabri.de` auf `89.167.121.25` umstellen → Let's Encrypt Zertifikat
2. `OPENAI_API_KEY` in `/srv/shoptour2/.env` eintragen
3. BookStack KI-Service-User anlegen, `BOOKSTACK_TOKEN_ID` + `BOOKSTACK_TOKEN_SECRET` setzen
4. Ersten Sync manuell starten: `docker exec shoptour2-app php artisan knowledge:sync`
5. BookStack-Buchstruktur anlegen (Markt, Lieferservice, Festbedarf, Verwaltung, Technik, Sortiment, Notfall, Entwürfe)
