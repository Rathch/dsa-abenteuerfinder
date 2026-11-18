# Tasks

## 1. Golden Files

- [x] 1.1 Einen Pest-Lauf ergänzen, der aus dem bestehenden `MatchFinder` Golden Files schreibt (Katalogausschnitt, abgeschlossene Antworten, Titelreihenfolge, Begründungen, Lockerungshinweise), und prüfen, dass ein zweiter Lauf dieselben Dateien ergibt und `php artisan test --compact` grün bleibt.

## 2. Bewertung in TypeScript

- [x] 2.1 `app/Domain/Matching` nach `frontend/src/domain/` übersetzen, ohne DOM und ohne Netz, und mit Vitest gegen die Golden Files prüfen, dass Titelreihenfolge, Begründungen und Lockerungshinweise übereinstimmen.
- [x] 2.2 Die Vitest-Abdeckung von `frontend/src/domain/` auf mindestens 90 % Zeilen bringen und den Abdeckungsbericht prüfen.

## 3. Katalog im Build

- [x] 3.1 Ein Node-Skript schreiben, das aus `database/data/*.json` eine `catalog.json` erzeugt, Einträge mit `missing_from_wiki` weglässt, und mit einem Test prüfen, dass ein fehlender Wiki-Eintrag fehlt und ein vorschlagbarer Eintrag enthalten ist.
- [x] 3.2 Den Vite-Build so verdrahten, dass `catalog.json` gleichherkunftlich in `dist/` liegt und die Startseite sie erst nach dem ersten Paint lädt. Prüfen: `npm run build` legt die Datei ab, und ein Test sieht vor dem Laden kein blockierendes Katalog-Skript im ersten HTML.

## 4. Statische Seiten

- [x] 4.1 `frontend/` mit Vite (`base: '/'`, ohne `laravel-vite-plugin`), Tailwind 4, `index.html`, `impressum/index.html`, `datenschutz/index.html`, `404.html` und einer `CNAME` mit genau `dsa-abenteuerfinder.rath-ulrich.de` anlegen. Prüfen: `npm run build` erzeugt diese Dateien unter `dist/` und kein Stylesheet oder Skript verweist auf eine andere Herkunft.
- [x] 4.2 Den Assistenten in TypeScript gegen dieses HTML bauen: `localStorage`-Schlüssel `adventure_wizard` und `theme`, Neu starten löscht nur den Durchlauf, die Rangliste kommt aus `frontend/src/domain`. Mit Playwright gegen `vite preview` prüfen: Neuladen stellt die Antworten wieder her, Neu starten entfernt sie, und nach geladenem Katalog geht für die Rangliste keine weitere Dokument-Anfrage an den Host.
- [x] 4.3 Die Datenschutzerklärung auf den lokalen Durchlauf umschreiben, ohne Server-Sitzung und ohne Verwaltungspasswort. Mit Playwright prüfen, dass `/datenschutz/` den lokalen Verbleib nennt und keines der beiden alten Konzepte beschreibt.
- [x] 4.4 Hex-Flächen und das Vergrößern des Covers aus der aktuellen Oberfläche mitnehmen, ohne ein zweites Cover zu laden. Die bestehenden Playwright-Abläufe für Tastatur, Ablauf und Cover auf `vite preview` umstellen und prüfen, dass sie grün sind. Bildbaselines einmal neu ziehen und die aktualisierten Schnappschüsse im Baum haben.
- [x] 4.5 axe für die öffentlichen Ansichten in hell und dunkel gegen `vite preview` laufen lassen und prüfen, dass keine AA-Verstöße bleiben.

## 5. Öffentliche PHP-Oberfläche entfernen

- [x] 5.1 Livewire, Admin, Web-Routen der Seite, SQLite-Laufzeit der Seite, `deploy/` für Docker/Traefik und `scripts/playwright-serve.sh` entfernen. Die Wiki-Kommandos bleiben. Prüfen: `php artisan test --compact` für die Pipeline ist grün, und eine Suche findet keine Livewire-Komponente und keine Route `verwaltung` mehr.
- [x] 5.2 Den PHP-Finder und `AdventureMatchService` löschen, nachdem Aufgabe 2.1 grün ist und die Seite die TypeScript-Bewertung nutzt. Prüfen: Vitest ist weiterhin grün und unter `app/` gibt es kein `Domain/Matching` mehr.

## 6. Verfassung, CI und Pages

- [x] 6.1 Die Verfassung auf 2.0.0 heben: Vitest und 90 % für `frontend/src/domain`, Pest nur noch für die Wiki-Pipeline, Playwright gegen `vite preview`, die Budgets aus dem Design, die Lücken aus Complexity Tracking. Prüfen, dass die Datei Version 2.0.0 trägt und Prinzip I nicht mehr die öffentliche Seite an Pest bindet.
- [x] 6.2 README und verbliebene Deploy-Hinweise auf den statischen Build und die Domain-Schritte aus `design.md` umstellen (CNAME `dsa-abenteuerfinder.rath-ulrich.de`, Ziel `rathch.github.io`, Enforce HTTPS erst nach erfolgreicher DNS-Prüfung, Secret `CONTENTS_TOKEN`). Prüfen, dass die README diese Schritte nennt und das Docker-Compose-Deployment nicht mehr als Produktion beschreibt.
- [x] 6.3 Die CI-Workflows auf Vitest, Playwright gegen `vite preview` und die verbleibende PHP-Pipeline umstellen. Prüfen, dass der Workflow kein `php artisan serve` und keine Playwright-SQLite mehr startet und `npm run build` darin vorkommt.
- [x] 6.4 Eine Pages-Action auf `main` anlegen (`npm ci`, Build, Artifact `dist/`, `deploy-pages`, Rechte `contents: read`, `pages: write`, `id-token: write`) und eine geplante Wiki-Sync-Action, die mit `CONTENTS_TOKEN` committet. Prüfen, dass beide Workflow-Dateien diese Schritte enthalten und `dist/CNAME` im Pages-Artifact liegt.

## 7. Zusammenspiel

- [x] 7.1 Gegen `vite preview` prüfen: `/` zeigt den Assistenten, `/impressum/` und `/datenschutz/` die Texte, `/verwaltung` kein Formular, und eine abgeschickte Rangliste nach geladenem Katalog ohne weitere Host-Anfrage erscheint. Der Befehl für diesen Lauf ist im Aufgabenkommentar der Playwright-Datei genannt und endet grün.

## Workflow follow-up

- Den Change archivieren, sobald die Aufgaben abgehakt sind und die Review-Kriterien der Verfassung erfüllt sind.
- Nach dem Archiv prüfen, dass die Delta-Specs in `openspec/specs/` angekommen sind.
