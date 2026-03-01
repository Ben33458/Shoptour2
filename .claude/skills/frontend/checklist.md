# Frontend Implementation Checklist (Next.js / shadcn/ui)

Before marking frontend as complete:

## shadcn/ui
- [ ] shadcn/ui für JEDE benötigte UI-Komponente geprüft: `ls src/components/ui/`
- [ ] Keine eigenen Duplikate von shadcn-Komponenten erstellt
- [ ] Fehlende shadcn-Komponenten installiert via `npx shadcn@latest add`

## Existing Code
- [ ] Existierende Projekt-Komponenten geprüft: `git ls-files src/components/`
- [ ] Existierende Hooks geprüft: `ls src/hooks/ 2>/dev/null`
- [ ] Existierende Komponenten wiederverwendet wo möglich

## Design
- [ ] Design-Anforderungen mit Nutzer geklärt (falls keine Mockups)
- [ ] Komponenten-Architektur aus Solution Architect Tech Design eingehalten

## Implementation
- [ ] Alle geplanten Komponenten implementiert
- [ ] Alle Komponenten nutzen Tailwind CSS (keine Inline-Styles, keine CSS Modules)
- [ ] Loading States implementiert (Spinner/Skeleton während Daten-Fetch)
- [ ] Error States implementiert (nutzerfreundliche Fehlermeldungen)
- [ ] Empty States implementiert ("Noch keine Daten" Meldungen)
- [ ] Geldbeträge korrekt umgerechnet: `(milliCent / 1000).toFixed(2) + ' €'`

## API-Integration
- [ ] API-Calls zu Laravel Backend korrekt implementiert
- [ ] Auth-Token im Authorization-Header gesendet
- [ ] 401-Fehler (abgelaufene Session) behandelt → Redirect zu Login
- [ ] Netzwerk-Fehler behandelt (Timeout, kein Internet)

## Quality
- [ ] Responsive: Mobile (375px), Tablet (768px), Desktop (1440px)
- [ ] Accessibility: Semantisches HTML, ARIA-Labels, Tastatur-Navigation
- [ ] TypeScript: Keine Fehler (`npm run build` erfolgreich)
- [ ] ESLint: Keine Warnungen (`npm run lint`)

## Verification (vor Abschluss ausführen)
- [ ] `npm run build` erfolgreich ohne Fehler
- [ ] Alle Acceptance Criteria aus Feature-Spec in der UI abgedeckt
- [ ] `features/INDEX.md` Status auf "In Progress" gesetzt

## Completion
- [ ] Nutzer hat die UI im Browser geprüft und freigegeben
- [ ] Code committed
