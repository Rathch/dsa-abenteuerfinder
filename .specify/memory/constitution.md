<!--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only

Sync Impact Report
- Version change: 1.0.0 → 1.1.0
- Geändert: VIII – Kurzbeschreibungen aus Wiki Aventurica (CC-BY-SA, mit Quellenlink) ausdrücklich erlaubt
- Version change: (neu) → 1.0.0
- Prinzipien: I–VIII initial definiert
-->

# DSA-Abenteuerfinder Constitution

**Kanonisches Repository:** https://github.com/Rathch/dsa-abenteuerfinder (`Rathch/dsa-abenteuerfinder`)

Das Projekt hilft Spielleiter:innen von *Das Schwarze Auge* (DSA), passende Abenteuer
zu finden. Diese Verfassung ist für Spezifikationen, Pläne und Implementierungen bindend.

## Core Principles

### I. Test-Driven Development (NICHT VERHANDELBAR)

Fachlogik wird testgetrieben entwickelt: Zuerst schlägt ein Pest-Test fehl (Red), dann
folgt die minimale Implementierung (Green), dann Refactoring. Fehler MÜSSEN zuerst durch
einen fehlschlagenden Regressionstest reproduziert werden. Die Schicht **`app/Domain`**
MUSS eine Zeilenabdeckung von **mindestens 90 %** aufweisen; die Abdeckung der Gesamt-
Codebasis DARF ohne Begründung im PR nicht sinken. Zusätzlich MUSS die Schichtentrennung
über **Deptrac** (`deptrac.yaml`) und **PHPStan/Larastan** (Level 8, `phpstan.neon.dist`)
sowie **Laravel Pint** fehlerfrei bestehen (`composer run lint`).

### II. Architektur und Codequalität

Die Domain-Schicht (`app/Domain`) ist rein: keine Abhängigkeit auf Eloquent, Models,
Services, Livewire oder HTTP. Abhängigkeitsrichtung: Livewire/Http/Console → Services →
Models → Domain. Livewire-Komponenten greifen nicht direkt auf Models zu. Öffentliche
Schnittstellen MÜSSEN klar benannt und typisiert sein; Fehlerfälle werden deterministisch
behandelt (keine stillen Failures). Bewusste Abweichungen MÜSSEN im Plan unter
„Complexity Tracking“ begründet werden.

### III. Barrierefreiheit (WCAG 2.2 AA)

Alle nutzerberührenden Oberflächen MÜSSEN **WCAG 2.2 Level AA** erfüllen: semantisches
HTML, ausreichender Kontrast, vollständige Tastaturbedienung, sichtbarer Fokus, sinnvolle
Labels/ARIA, Zoom/Reflow bis 400 %, `prefers-reduced-motion` respektieren. Automatisierte
Prüfungen (axe-core über Playwright) MÜSSEN ohne Verstöße der Stufe AA laufen.

### IV. Visuelle und Ende-zu-Ende-Tests (Playwright)

Nutzerrelevante Abläufe MÜSSEN mit **Playwright** abgedeckt sein, inklusive
**Screenshot-Baselines** (`npm run test:visual`, Aktualisierung mit
`npm run test:visual:update`, Baselines werden eingecheckt). Der E2E-Server nutzt eine
isolierte SQLite-Datenbank (`database/playwright.sqlite`, `scripts/playwright-serve.sh`).
Visuelle Änderungen MÜSSEN im PR bewusst und nachvollziehbar sein.

### V. Performance

Features MÜSSEN messbare Budgets einhalten oder definieren (z. B. Largest Contentful
Paint ≤ 2,5 s, Interaction to Next Paint ≤ 200 ms, CLS ≤ 0,1, Suchantwort p95 ≤ 300 ms
serverseitig, JS-Bundle klein halten). Budgets stehen im Plan; Regressionen werden im
Review mit Messung adressiert. Abfragen MÜSSEN N+1-frei und indiziert sein.

### VI. Sicherheit

Eingaben MÜSSEN validiert, Ausgaben escaped werden. Produktion läuft mit
Sicherheits-Headern (CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`), Rate-Limiting und HTTPS. Geheimnisse gehören nie ins Repository
(Gitleaks in CI). Abhängigkeiten werden mit `composer audit` und `npm audit` geprüft;
bekannte Schwachstellen mit Schweregrad „high“ oder höher blockieren den Merge.

### VII. Datensparsamkeit

Es werden nur Daten erhoben und gespeichert, die für den Zweck zwingend erforderlich
sind. Das Produkt MUSS ohne Nutzerkonto, ohne Tracking und ohne Drittanbieter-Ressourcen
zur Laufzeit funktionieren. Logs enthalten keine unnötigen personenbezogenen Daten (z. B.
keine vollständigen IP-Adressen oder Freitext-Suchanfragen mit Personenbezug). Falls
Daten gespeichert werden, sind Feld, Zweck und Aufbewahrungsdauer in der Spezifikation
dokumentiert; nicht mehr benötigte Daten werden gelöscht. Abweichungen werden unter
„Complexity Tracking“ begründet.

### VIII. Lizenz (GPL-3.0-only)

Der Quellcode steht unter **GPL-3.0-only**. Neue Dateien tragen, wo sinnvoll, einen
SPDX-Header (`SPDX-License-Identifier: GPL-3.0-only`). Abenteuer-Metadaten und sonstige
Inhalte DÜRFEN nur aufgenommen werden, wenn ihre Lizenz bzw. rechtliche Grundlage dies
erlaubt. **Kurzbeschreibungen** aus [Wiki Aventurica](https://de.wiki-aventurica.de/)
(CC-BY-SA 3.0) DÜRFEN in den Katalog übernommen werden, sofern jeder Eintrag per
`wiki_url` auf den Ursprungsartikel verweist (Namensnennung über den Link). Cover und
sonstige Bild-/Mediendateien bleiben ausgeschlossen, sofern nicht freigegeben. Neue
Abhängigkeiten MÜSSEN GPL-3.0-kompatibel lizenziert sein.

## Zusätzliche Leitplanken

- Spezifikationen MÜSSEN testbare Akzeptanzkriterien und Grenzfälle nennen.
- Deutsch ist Standardsprache der Oberfläche (`APP_LOCALE=de`); Texte liegen in
  Übersetzungsdateien, nicht hartcodiert in Logik.
- Breaking Changes werden semver-konform kommuniziert.

## Arbeitsweise & Nachweis

- Vor Merge MÜSSEN Pint, PHPStan, Deptrac, Pest (inkl. Domain-Coverage ≥ 90 %),
  Frontend-Build und Playwright grün sein (CI) oder Ausnahmen dokumentiert werden.
- Reviews prüfen explizit: Tests zuerst?, Barrierefreiheit?, Performance-Budgets?,
  Sicherheit?, Datensparsamkeit?
- Bei Konflikt zwischen Verfassung und Spezifikation gilt die Verfassung.

## Governance

Änderungen an Prinzipien erfordern eine neue semantische Version in dieser Datei, ein
Datum der letzten Änderung und ggf. Kommunikation im Team. PRs MÜSSEN die Einhaltung der
Prinzipien adressieren oder Abweichungen unter „Complexity Tracking“ begründen.

**Version**: 1.1.0 | **Ratified**: 2026-11-05 | **Last Amended**: 2026-11-06
