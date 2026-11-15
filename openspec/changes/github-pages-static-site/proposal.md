# Proposal

## Why

GitHub Pages kann nur statische Dateien ausliefern. Der Finder ist heute eine Laravel-Anwendung mit Livewire, PHP-Session und SQLite und lässt sich so nicht veröffentlichen. Produktivbetrieb auf `dsa-abenteuerfinder.rath-ulrich.de` gibt es noch nicht, deshalb wird dieses Repository zur statischen Seite umgebaut, statt einen zweiten Stack daneben zu stellen.

## What Changes

- Die öffentliche Seite (Start mit Assistent, Impressum, Datenschutz) wird zu statischen Dateien, die GitHub Pages ausliefert. Eine eigene Domain zeigt auf diese Veröffentlichung und bedient die Seiten an der Wurzel, ohne Repository-Pfad.
- Die Rangliste entsteht im Browser aus dem mitgelieferten Katalog. Dieselben Antworten ergeben dieselbe Reihenfolge und dieselben Begründungen wie der heutige `MatchFinder`. Während des Tippens geht keine Anfrage an einen Server.
- Antworten eines laufenden Durchlaufs bleiben nur auf dem Gerät. Es gibt kein Nutzerkonto, keine Server-Session und kein Tracking.
- **BREAKING**: Die öffentliche Laravel-Oberfläche entfällt: Livewire, Server-Session, SQLite als Seiten-Laufzeit, die Verwaltung unter `/verwaltung` und das Docker-/Traefik-Deployment. Katalogpflege läuft über die JSON-Daten im Repository, nicht über ein Login. Die Wiki-Pipeline bleibt ein PHP-Kommando und wird nicht mit der Seite veröffentlicht.
- **BREAKING**: Die Verfassung setzt PHP, Pest, Deptrac und serverseitige Sicherheits-Header voraus. Sie wird mit diesem Umbau an eine statische Veröffentlichung angepasst. Die fachlichen Garantien (Tests vor Logik, WCAG 2.2 AA, Playwright, Performance-Budgets, Datensparsamkeit, GPL-3.0-only) bleiben.
- Wiki-Import und Genre-Zuordnung bleiben in ihrer Wirkung. Sie laufen als Datenpipeline und erzeugen den Katalog, den die Seite mitliefert. Zur Laufzeit im Browser gibt es keinen Import.

## Capabilities

### New Capabilities

- `browser-recommendations`: Rangliste und Begründungen entstehen im Browser aus dem mitgelieferten Katalog; Antworten bleiben auf dem Gerät.
- `static-publication`: Die öffentlichen Seiten sind statische Dokumente, die ein Datei-Host und eine eigene Domain an der Wurzel ausliefern.

### Modified Capabilities

- `suggested-adventures`: Unvollständige Abenteuer bleiben vorschlagbar und in den Kennzahlen. Die Anforderung, sie in der Verwaltung unter „Unvollständig“ zu listen, entfällt mit der Verwaltung.

## Impact

- Öffentliche Oberfläche, Matching, Katalogauslieferung, Impressum und Datenschutz.
- Entfernt: Livewire, HTTP-Oberfläche, Eloquent, Admin-Login, SQLite als Laufzeit der Seite, `deploy/` für Docker/Traefik, serverseitige Sessions. Die Wiki-Pipeline bleibt PHP-Werkzeug im Repository und wird nicht mit der Seite ausgeliefert.
- Datenpipeline (Wiki-Harvest, Validierung, Katalog-JSON) bleibt erhalten und speist den Build. `wiki-genres` ändert keine Anforderung.
- CI wechselt von PHP-FPM/SQLite-Playwright auf einen statischen Build plus Browser-Tests. Die Domain-Anbindung (DNS, `CNAME`, HTTPS) ist Betriebsanleitung im Design, kein Laufzeitcode.
- Offene UI-Changes (`stretched-hex-corners`, `enlarge-cover-on-click`) beschreiben die heutige Blade-Oberfläche. Ihr sichtbares Verhalten muss die statische Seite mitnehmen, soweit es schon umgesetzt ist.
