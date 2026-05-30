<x-mail::message>
# Ninox Sync-Bericht

**Zeitraum:** {{ $report['period_from'] }} bis {{ $report['period_to'] }}

---

## shoptour2 → Ninox (Push)

@if($report['push_total'] > 0)
<x-mail::table>
| Typ | Erfolgreich | Fehlgeschlagen |
|:----|------------:|---------------:|
@foreach($report['push_by_type'] as $type => $counts)
| {{ $type }} | {{ $counts['done'] }} | {{ $counts['failed'] }} |
@endforeach
| **Gesamt** | **{{ $report['push_done'] }}** | **{{ $report['push_failed'] }}** |
</x-mail::table>

@if(!empty($report['push_samples']))
### Beispiele (zuletzt verarbeitet)

@foreach($report['push_samples'] as $type => $samples)
**{{ $type }}**
@foreach($samples as $s)
- {{ $s['label'] }} *({{ $s['updated_at'] }})*
@endforeach

@endforeach
@endif

@else
Keine Push-Vorgänge im Berichtszeitraum.
@endif

@if(!empty($report['push_errors']))

### ❌ Fehlgeschlagene Push-Tasks

<x-mail::table>
| Task | Datensatz | Versuche | Fehler | Zeit |
|:-----|:----------|:--------:|:-------|-----:|
@foreach($report['push_errors'] as $err)
| #{{ $err['id'] }} · {{ $err['type'] }} | {{ $err['label'] }} | {{ $err['attempts'] }} | {{ $err['error'] }} | {{ $err['updated_at'] }} |
@endforeach
</x-mail::table>
@endif

---

## Observer-Aktivität

Neue Sync-Tasks im Berichtszeitraum: **{{ $report['tasks_created'] }}**

@if(!empty($report['tasks_created_by_type']))
<x-mail::table>
| Typ | Neu erstellt |
|:----|-------------:|
@foreach($report['tasks_created_by_type'] as $type => $cnt)
| {{ $type }} | {{ $cnt }} |
@endforeach
</x-mail::table>
@endif

---

## Ninox-Import-Läufe (manuell / ninox:pull)

@if($report['sync_runs_total'] > 0)
<x-mail::table>
| Gestartet | Status | Datensätze | Dauer |
|:----------|:-------|:----------:|------:|
@foreach($report['sync_runs'] as $run)
| {{ $run['started_at'] }} | {{ $run['status'] }} | {{ $run['records'] }} | {{ $run['duration'] }} |
@endforeach
</x-mail::table>
@else
Keine Import-Läufe im Berichtszeitraum.
@endif

---

## ⚠ Problematische Datensätze

@if(empty($report['unlinked_stubs']) && empty($report['orders_without_items']) && empty($report['stuck_tasks']))
Keine Auffälligkeiten gefunden.
@endif

@if(!empty($report['unlinked_stubs']))
### Kunden-Stubs ohne Abgleich

Diese Kunden wurden aus Ninox importiert, konnten aber nicht automatisch mit einem bestehenden Kunden verknüpft werden. Bitte manuell prüfen und ggf. `ninox_kunden_id` setzen.

<x-mail::table>
| Kundennummer | Name | Ninox-ID | Importiert |
|:-------------|:-----|:--------:|----------:|
@foreach($report['unlinked_stubs'] as $stub)
| {{ $stub['customer_number'] }} | {{ $stub['name'] }} | {{ $stub['ninox_kunden_id'] }} | {{ $stub['created_at'] }} |
@endforeach
</x-mail::table>
@endif

@if(!empty($report['orders_without_items']))
### Bestellungen ohne Positionen

Diese Ninox-Bestellungen haben keine Artikelpositionen — der Inhalt liegt nur als Freitext in den Notizen.

<x-mail::table>
| Bestellnummer | Kunde | Lieferdatum | Ninox-ID | Notiz |
|:--------------|:------|:-----------:|:--------:|:------|
@foreach($report['orders_without_items'] as $o)
| {{ $o['order_number'] }} | {{ $o['customer_name'] }} | {{ $o['delivery_date'] }} | {{ $o['ninox_id'] }} | {{ $o['notes'] }} |
@endforeach
</x-mail::table>
@endif

@if(!empty($report['stuck_tasks']))
### Feststeckende Tasks (pending &gt; 2 h)

<x-mail::table>
| Task | Typ | Datensatz | Versuche | Seit |
|:-----|:----|:----------|:--------:|-----:|
@foreach($report['stuck_tasks'] as $t)
| #{{ $t['id'] }} | {{ $t['type'] }} | {{ $t['label'] }} | {{ $t['attempts'] }} | {{ $t['since'] }} |
@endforeach
</x-mail::table>
@endif

@if($report['push_failed'] > 0 || $report['sync_runs_failed'] > 0 || !empty($report['stuck_tasks']))

---

> ⚠️ **Es gab Fehler.** Bitte unter Admin → Import & Sync prüfen.

@endif

{{ config('app.name') }}

</x-mail::message>
