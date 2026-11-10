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
- Die Fachlogik (Matching, Bewertung) liegt rein in `app/Domain` und ist testgetrieben.
- Die Oberfläche ist mit Laravel, Livewire und Tailwind gebaut, deutschsprachig und
  nach WCAG 2.2 AA zugänglich.
- Abenteuerdaten werden aus kuratierten Metadaten importiert (`adventures:import`).

## Stack

Laravel 13 · PHP 8.5 · Livewire 4 / Volt · Tailwind 4 · Vite · SQLite · Pest · Playwright

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
composer setup          # Abhängigkeiten, .env, Key, Migrationen, npm install + build
composer dev            # Server, Logs und Vite parallel → http://127.0.0.1:8000
php artisan adventures:import   # Abenteuer aus database/data/*.json
```

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

Die Playwright-Läufe nutzen `scripts/playwright-serve.sh` mit isolierter SQLite-Datenbank
(`database/playwright.sqlite`), frischen Migrationen und `adventures:import`.

CI (`.github/workflows/ci.yml`): Pint, PHPStan, Deptrac, `composer validate`, Vite-Build,
Pest mit Coverage (Domain ≥ 90 %), Playwright. Sicherheit (`security.yml`): Gitleaks,
`composer audit`, `npm audit`.

## Architektur

Abhängigkeitsrichtung (per Deptrac erzwungen):

```text
Livewire / Http / Console → Services → Models → Domain
```

`app/Domain` ist rein: kein Eloquent, keine Models, Services, Livewire oder HTTP.

## Deployment

Produktion: Docker Compose hinter Traefik auf
`https://dsa-abenteuerfinder.rath-ulrich.de` (Details: [`deploy/README.md`](deploy/README.md)).

```bash
make build     # Image lokal bauen
make push      # bauen + nach GHCR pushen
make deploy    # bauen, pushen, Server aktualisieren
make remote    # nur Server-Update
```

## Datensparsamkeit

- Kein Nutzerkonto, kein Tracking, keine Drittanbieter-Ressourcen zur Laufzeit.
- Nur zwingend erforderliche Daten werden verarbeitet; Logs bleiben minimal.
- Nginx setzt Sicherheits-Header (CSP, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`) und begrenzt Anfragen pro IP.

## Barrierefreiheit und Performance

- **WCAG 2.2 AA**: Tastaturbedienung, Kontrast, Fokus, Reflow; axe-core in Playwright.
- **Budgets**: LCP ≤ 2,5 s · INP ≤ 200 ms · CLS ≤ 0,1 · serverseitige Antwort p95 ≤ 300 ms.

## Lizenz

GPL-3.0-only – siehe https://www.gnu.org/licenses/gpl-3.0.html.
