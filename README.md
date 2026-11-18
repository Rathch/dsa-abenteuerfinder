<!--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
-->

# DSA-Abenteuerfinder

Empfehlungen für Spielleiter:innen von *Das Schwarze Auge* (DSA): Passende Abenteuer
nach Gruppe, Erfahrung, Region, Stil und Spieldauer finden – barrierefrei, schnell und
ohne Tracking.

Lizenz: GPL-3.0-only · Verfassung: [`.specify/memory/constitution.md`](.specify/memory/constitution.md)

## Vision

- Spielleitungen beantworten wenige Fragen und erhalten eine nachvollziehbare, erklärte
  Rangliste passender Abenteuer.
- Die Bewertung liegt in `frontend/src/domain` und läuft im Browser.
- Die Oberfläche ist eine statische Vite-Seite, deutschsprachig und nach WCAG 2.2 AA
  zugänglich.
- Die Wiki-Pipeline bleibt PHP und schreibt `database/data/*.json`. Sie wird nicht
  veröffentlicht.

## Stack

Öffentliche Seite: Vite · TypeScript · Tailwind 4 · Vitest · Playwright

Wiki-Pipeline: Laravel 13 · PHP 8.5 · SQLite · Pest

## Setup

Voraussetzungen: PHP ≥ 8.5, Composer 2, Node ≥ 24.

Unter Ubuntu greifen ohne Setup oft System-PHP 8.3 und Node 18 – dann scheitert
`composer dev` (Laravel braucht PHP ≥ 8.5, `concurrently` braucht Node ≥ 20).

```bash
# PHP 8.5 (Herd / php.new)
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"

# Node 24 (nvm)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
# Terminal neu öffnen, dann:
nvm install 24 && nvm use 24
```

`composer dev` nutzt `scripts/dev.sh` und aktiviert Herd-PHP sowie nvm Node 24
automatisch, sofern installiert.

```bash
npm ci
npm run build           # catalog.json und dist/
npx vite preview --host 127.0.0.1 --port 4173
```

Die Wiki-Kommandos brauchen PHP 8.5 und Composer (`composer install`).

### Wiki-Harvest

Abenteuer aus den Indexseiten auf
[Wiki Aventurica](https://de.wiki-aventurica.de/) werden als Metadaten übernommen:
VA-Reihe, A-Reihe, Alveraniars, Boten, Boxen, HW-Reihe, Inoffizielle, Kostenlose,
Weitere Reihen und Wettbewerbe. Die chronologische Übersicht ergänzt nur fehlende Titel.
Kurzbeschreibungen stammen aus Wiki-Klappentext bzw. Artikelabschnitten (CC-BY-SA,
Attribution über `wiki_url`). Einträge ohne Wiki-Text werden nicht übernommen; ungemappte
Orte bleiben `incomplete` und fließen nicht ins Matching ein. Cover werden als Vorschaubild von der Wiki-Galerie verlinkt, nicht kopiert.

```bash
php artisan adventures:harvest-wiki              # Index + Klappentexte (Cache: storage/app/wiki-cache/, Pause ~1s)
php artisan adventures:harvest-wiki --from-cache # nur Cache, kein Netz
php artisan adventures:harvest-wiki --skip-blurbs
php artisan adventures:apply-wiki-harvest        # VA-/A-Harvest ersetzen, kuratierte complete bleiben
php artisan adventures:validate
php artisan adventures:import
```

Alias-Dateien: `database/data/wiki-harvest/location-aliases.json`,
`genre-aliases.json`. Ungemappte Orte: `storage/logs/wiki-harvest-unmapped.txt`.

Playwright einmalig installieren:

```bash
npm run test:visual:install
```

## Qualitätssicherung

```bash
composer run lint       # Pint (check), PHPStan/Larastan Level 8, Deptrac
composer run format     # Pint anwenden
composer run test       # Pest
composer run qa         # lint + test + axe (jede Ansicht, light und dark)
composer run qa:full    # zusätzlich visuelle Playwright-Tests

npm run test:a11y             # festes Gate: axe für alle Ansichten, light und dark
npm run test:a11y:ci          # dasselbe ohne Build (CI hat das Vite-Artefakt)
npm run test:visual           # Build + gesamter Playwright-Lauf
npm run test:visual:update    # Screenshot-Baselines aktualisieren
npm run test:visual:ci        # Playwright ohne Build (CI)
```

Playwright startet `vite preview`. Es gibt keine Playwright-SQLite.

```bash
npm run test:domain       # Vitest, Zeilenabdeckung von frontend/src/domain mindestens 90 %
```

CI (`.github/workflows/ci.yml`): Pint, PHPStan, Deptrac und Pest für die Wiki-Pipeline,
`npm run build`, Vitest und Playwright gegen `vite preview`. Sicherheit (`security.yml`):
Gitleaks, `composer audit`, `npm audit`.

## Architektur

Abhängigkeitsrichtung (per Deptrac erzwungen):

```text
Console → Services → Models → Domain
```

`app/Domain/Catalog` sind die Katalogtypen der Pipeline: kein Eloquent, keine Models,
keine Services. Die Bewertung liegt in `frontend/src/domain`.

## Deployment

Produktion ist GitHub Pages. Docker Compose ist nicht der Produktionsweg.

Der Build legt `dist/CNAME` mit genau `dsa-abenteuerfinder.rath-ulrich.de` ab. Die
Pages-Action auf `main` führt `npm ci` und den Build aus und veröffentlicht `dist/`.
Die geplante Wiki-Synchronisation committet den Katalog mit dem Workflow-Token.
Ein solcher Push startet die Pages-Action nicht erneut; nach einem erfolgreichen
Lauf veröffentlicht `workflow_run` die Seite trotzdem.

1. Repository öffentlich halten (Pages im Free-Tarif).
2. Nach dem ersten grünen Pages-Lauf: **Settings → Pages → Build and deployment → Source: GitHub Actions**.
3. **Custom domain** auf `dsa-abenteuerfinder.rath-ulrich.de` setzen. Der Wert muss der Zeile in `CNAME` gleichen.
4. Beim DNS-Anbieter der Zone `rath-ulrich.de` nur diesen Eintrag für den Hostnamen anlegen:
   - Typ: `CNAME`
   - Host: `dsa-abenteuerfinder`
   - Ziel: `rathch.github.io`
   - Nicht `rathch.github.io/dsa-abenteuerfinder` und kein zweiter A- oder AAAA-Eintrag für denselben Host.
5. Proxy vor GitHub auslassen (bei Cloudflare „DNS only“).
6. Warten, bis die DNS-Prüfung in den Pages-Einstellungen erfolgreich ist.
7. Danach **Enforce HTTPS** einschalten. Vorher bleibt es aus.
8. `https://dsa-abenteuerfinder.rath-ulrich.de/`, `/impressum/` und `/datenschutz/` öffnen.

Die Apex-Domain `rath-ulrich.de` ist nicht das Ziel.

## Datensparsamkeit

- Kein Nutzerkonto, kein Tracking. Antworten bleiben unter `adventure_wizard` auf dem Gerät.
- Cover bleiben Verweise auf `https://de.wiki-aventurica.de`. Weitere Fremdressourcen gibt es nicht.
- GitHub Pages setzt keine Sicherheits-Header. Die Seite nutzt eine Meta-CSP und `no-referrer`.

## Barrierefreiheit und Performance

- **WCAG 2.2 AA**: Tastaturbedienung, Kontrast, Fokus, Reflow; axe-core in Playwright.
- **Budgets**: LCP ≤ 2,5 s · INP ≤ 200 ms · CLS ≤ 0,1 · Rangliste unter 300 ms nach dem Parsen von `catalog.json`.

## Lizenz

GPL-3.0-only – siehe https://www.gnu.org/licenses/gpl-3.0.html.
