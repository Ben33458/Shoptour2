# 07 – Abnahmekriterien MVP

## Mitarbeiter / Rollen
- Ein Mitarbeiter kann mehreren Rollen zugeordnet werden.
- Ein Mitarbeiter kann optional einen Vorgesetzten haben.
- Rollen steuern Sichtbarkeit und Bearbeitungsrechte.

## Schichtplanung
- Schichten können aus Vorlagen bzw. Dienstplan erzeugt werden.
- Bereiche Markt, Lieferung und Unterstützung sind nutzbar.
- Feiertage Hessen sind in Tabelle vorhanden und im Plan nutzbar.

## Zeiterfassung
- Mitarbeiter kann Schicht starten, Pause starten, Pause beenden und Schicht beenden.
- Tatsächliche Pause und automatische Korrekturpause sind getrennt gespeichert.
- Zu wenig Pause erzeugt Warnstatus.
- Vergessenes Ausstempeln wird automatisch zum geplanten Schichtende geschlossen.
- Spätestens die 12h-Notbremse schließt problematische Datensätze technisch.
- Auto-Close-Fälle sind für Admin erkennbar.
- Manuelle Änderungen erfordern Begründung und Freigabe.

## Schichtbericht
- Schichtbericht ist an eine Schicht gekoppelt.
- Pflichtchecklisten je Schichttyp werden angezeigt.
- Kassensturz, Kassendifferenz, Differenzbetrag und Glasbruch können erfasst werden.
- Unvollständige Berichte sind im Status erkennbar.
- Optionaler Fotobeleg kann gespeichert werden.

## Aufgaben
- Offene Aufgaben können angezeigt und gefiltert werden.
- Harte Tagespflichten und flexible Intervallaufgaben sind unterscheidbar.
- Cronjob erzeugt aus regelmäßigen Aufgaben offene Aufgaben.

## Urlaub
- Mitarbeiter kann einen Zeitraum beantragen.
- Optionaler Freitext und Notfall-Erreichbarkeit sind vorhanden.
- Admin kann genehmigen/ablehnen.
- Konflikthinweise werden im Zeitraum ±7 Tage angezeigt.
- Feld „in Lexoffice Lohn eingetragen“ ist nur für Admin/HR nutzbar.

## Dashboard
- Admin sieht aktuell eingestempelte Mitarbeiter.
- Admin sieht heute eingeplante Mitarbeiter.
- Admin sieht unvollständige Schichten.
- Admin sieht fehlende Pflichtaufgaben.
- Admin sieht Wochenstunden und Plus-/Minusstunden.

## Logging
- Kritische Aktionen werden zentral protokolliert.
- Logs sind filterbar.
- Änderungen an Zeiten sind revisionssicher nachvollziehbar.

