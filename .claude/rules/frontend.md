# Frontend Development Rules (Next.js / shadcn/ui)

## shadcn/ui First (MANDATORY)
- Before creating ANY UI component, check if shadcn/ui has it: `ls src/components/ui/`
- NEVER create custom implementations of: Button, Input, Select, Checkbox, Switch, Dialog, Modal, Alert, Toast, Table, Tabs, Card, Badge, Dropdown, Popover, Tooltip, Navigation, Sidebar, Breadcrumb
- If a shadcn component is missing, install it: `npx shadcn@latest add <name> --yes`
- Custom components are ONLY for business-specific compositions that internally use shadcn primitives

## Import Pattern
```tsx
import { Button } from "@/components/ui/button"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
```

## Component Standards
- Use Tailwind CSS exclusively (no inline styles, no CSS modules)
- All components must be responsive (mobile 375px, tablet 768px, desktop 1440px)
- Implement loading states, error states, and empty states
- Use semantic HTML and ARIA labels for accessibility
- Keep components small and focused
- Use TypeScript interfaces for all props

## API-Calls zu Laravel Backend
- API-Calls via `fetch()` oder einem zentralen `api.ts` Client
- Backend läuft auf separatem Laravel-Server (nicht Next.js API Routes für Geschäftslogik)
- Auth-Token (Laravel Sanctum) im `Authorization: Bearer` Header senden
- Fehlerbehandlung: HTTP-Status-Codes prüfen, User-freundliche Fehlermeldungen zeigen

## Auth Best Practices (Laravel Sanctum)
- Login-Token im `localStorage` oder `httpOnly Cookie` speichern (je nach Konfiguration)
- Nach Login: `window.location.href` für Redirect nutzen (nicht `router.push`) um Auth-State zu resetten
- Loading-State immer in allen Pfaden zurücksetzen (success, error, finally)
- Token-Ablauf behandeln: bei 401-Response → Redirect zu Login

## Geldbeträge
- Backend liefert Beträge als Integer (Milli-Cent): `10000 = 10,00 €`
- Im Frontend immer in Euro umrechnen: `(milliCent / 1000).toFixed(2)` + `€`-Symbol
- Niemals Float-Arithmetik für Geldbeträge verwenden
