<!--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only

Sync Impact Report
- Version change: 1.1.0 → 2.0.0
- Geändert: I, II, IV, V, VI – öffentliche Seite statisch, Bewertung in Vitest, Pest nur für die Wiki-Pipeline, Playwright gegen vite preview, Header-Lücken unter Complexity Tracking
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

Die öffentliche Bewertung wird testgetrieben in TypeScript entwickelt. Zuerst schlägt ein
Vitest-Test fehl (Red), dann folgt die minimale Implementierung (Green), dann Refactoring.
Die Schicht **`frontend/src/domain`** MUSS eine Zeilenabdeckung von **mindestens 90 %**
aufweisen. Die öffentliche Seite ist nicht an Pest gebunden.

Die Wiki-Pipeline bleibt PHP und wird mit Pest entwickelt: Zuerst schlägt der Pest-Test
fehl, dann die Implementierung. Fehler MÜSSEN zuerst durch einen fehlschlagenden
Regressionstest reproduziert werden. Für das verbleibende PHP MÜSSEN **Deptrac**
(`deptrac.yaml`), **PHPStan/Larastan** (Level 8, `phpstan.neon.dist`) und **Laravel Pint**
fehlerfrei bestehen (`composer run lint`).

### II. Architektur und Codequalität

Zwei Artefakte, ein Repository. Die veröffentlichte Seite ist das statische Vite-Build
(`dist/`). Die Bewertung liegt in `frontend/src/domain` und kennt weder DOM noch Netz.
Die Wiki-Pipeline bleibt PHP und wird nicht veröffentlicht. `app/Domain` enthält nur die
Katalogtypen der Pipeline und bleibt rein: keine Abhängigkeit auf Eloquent, Models,
Services oder HTTP. Abhängigkeitsrichtung der Pipeline: Console → Services → Models →
Domain. Öffentliche Schnittstellen MÜSSEN klar benannt und typisiert sein; Fehlerfälle
werden deterministisch behandelt (keine stillen Failures). Bewusste Abweichungen MÜSSEN
im Plan unter „Complexity Tracking“ begründet werden.

### III. Barrierefreiheit (WCAG 2.2 AA)

Alle nutzerberührenden Oberflächen MÜSSEN **WCAG 2.2 Level AA** erfüllen: semantisches
HTML, ausreichender Kontrast, vollständige Tastaturbedienung, sichtbarer Fokus, sinnvolle
Labels/ARIA, Zoom/Reflow bis 400 %, `prefers-reduced-motion` respektieren. Automatisierte
Prüfungen (axe-core über Playwright) MÜSSEN ohne Verstöße der Stufe AA laufen.

### IV. Visuelle und Ende-zu-Ende-Tests (Playwright)

Nutzerrelevante Abläufe MÜSSEN mit **Playwright** gegen **`vite preview`** abgedeckt sein,
inklusive **Screenshot-Baselines** (`npm run test:visual`, Aktualisierung mit
`npm run test:visual:update`, Baselines werden eingecheckt). Es gibt keinen
Playwright-Server mit SQLite. Visuelle Änderungen MÜSSEN im PR bewusst und
nachvollziehbar sein.

### V. Performance

Features MÜSSEN messbare Budgets einhalten oder definieren. Für die öffentliche Seite:
Largest Contentful Paint ≤ 2,5 s, Interaction to Next Paint ≤ 200 ms, CLS ≤ 0,1. Die
Rangliste MUSS auf dem CI-Browser in unter 300 ms stehen, nachdem `catalog.json` geparst
ist. Der Katalog wird erst nach dem ersten Paint geladen. Budgets stehen im Plan;
Regressionen werden im Review mit Messung adressiert.

### VI. Sicherheit

Eingaben MÜSSEN validiert, Ausgaben escaped werden. Die Seite läuft über HTTPS. Skripte
und Styles kommen von derselben Herkunft. Eine Meta-CSP erlaubt Skripte und Styles nur
von `'self'` und Bilder von `'self'` sowie `https://de.wiki-aventurica.de`. Der Referrer
ist `no-referrer`. GitHub Pages kann CSP, `X-Frame-Options`, `Referrer-Policy` und
`Permissions-Policy` nicht als HTTP-Header setzen; diese Lücke steht unter Complexity
Tracking. Geheimnisse gehören nie ins Repository (Gitleaks in CI). Abhängigkeiten werden
mit `composer audit` und `npm audit` geprüft; bekannte Schwachstellen mit Schweregrad
„high“ oder höher blockieren den Merge.

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

- Vor Merge MÜSSEN für die Wiki-Pipeline Pint, PHPStan, Deptrac und Pest grün sein.
  Für die öffentliche Seite MÜSSEN Vitest (Zeilenabdeckung von `frontend/src/domain`
  ≥ 90 %), `npm run build` und Playwright gegen `vite preview` grün sein (CI) oder
  Ausnahmen dokumentiert werden.
- Reviews prüfen explizit: Tests zuerst?, Barrierefreiheit?, Performance-Budgets?,
  Sicherheit?, Datensparsamkeit?
- Bei Konflikt zwischen Verfassung und Spezifikation gilt die Verfassung.

## Governance

Änderungen an Prinzipien erfordern eine neue semantische Version in dieser Datei, ein
Datum der letzten Änderung und ggf. Kommunikation im Team. PRs MÜSSEN die Einhaltung der
Prinzipien adressieren oder Abweichungen unter „Complexity Tracking“ begründen.

## Complexity Tracking

- **Prinzip VI, Antwort-Header.** GitHub Pages lässt CSP, `X-Frame-Options`, `Referrer-Policy` und `Permissions-Policy` nicht als HTTP-Header setzen. Ersatz: HTTPS durch GitHub, keine Cookies, keine Verwaltung, Meta-CSP und `<meta name="referrer" content="no-referrer">`. `frame-ancestors` kann eine Meta-CSP nicht setzen.
- **Prinzip VII, Cover.** Bilder von `de.wiki-aventurica.de` bleiben Fremdabrufe. Keine weiteren Fremdressourcen.
- **Zwei Testläufer.** Vitest für die Bewertung, Pest für die Wiki-Pipeline. Deptrac gilt nur für das verbleibende PHP.

**Version**: 2.0.0 | **Ratified**: 2026-11-05 | **Last Amended**: 2026-11-15
