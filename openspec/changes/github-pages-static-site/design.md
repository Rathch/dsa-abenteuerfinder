# Design

## Context

Siehe `proposal.md` für den Anlass. Die öffentliche Seite ist Livewire (`AdventureWizard`) mit PHP-Session. Die Bewertung liegt in `app/Domain/Matching` (`MatchFinder`, `ScoreCalculator`, `ReasonBuilder`, `WizardFacetFilter`) und ist per Pest abgedeckt; `AdventureMatchService` lädt Kandidaten aus SQLite. Der Katalog selbst ist schon JSON unter `database/data/` (`adventures.json` etwa 2,2 MB, dazu Orte, Tags, Reihen). Die Wiki-Pipeline (`adventures:harvest-wiki`, `adventures:apply-wiki-harvest`, `adventures:sync-wiki`, `adventures:validate`) schreibt dieses JSON und nutzt Laravel nur als Kommandozeile. Cover bleiben Verweise auf `https://de.wiki-aventurica.de/...`. Es gibt noch keinen Produktivbetrieb. Die Verfassung 1.1.0 bindet die Seite an PHP, Pest, Deptrac, SQLite-Playwright und Nginx-Header.

## Goals / Non-Goals

**Goals:**

- Ein Vite-Build, den GitHub Pages als Dateien ausliefert, mit Assistent, Impressum und Datenschutz an der Wurzel der Domain `dsa-abenteuerfinder.rath-ulrich.de`.
- Dieselbe Rangliste wie der heutige PHP-Finder, nachgewiesen durch Golden Files, bevor der PHP-Finder gelöscht wird.
- Antworten und die Designwahl nur in `localStorage`. Die Datenschutzerklärung beschreibt genau das.

**Non-Goals:**

- Die Wiki-Parser nach TypeScript zu übersetzen.
- Cover ins Repository zu kopieren.
- Die Verwaltung durch ein anderes Login zu ersetzen.
- `https://rathch.github.io/dsa-abenteuerfinder/` als zweite produktive Basis. Sobald die eigene Domain hängt, ist sie die Wurzel; vorher prüft man die Seite lokal.

## Decisions

1. **Zwei Artefakte, ein Repository.** Veröffentlicht wird nur das Vite-Verzeichnis `dist/`. Die Wiki-Pipeline bleibt PHP (`app/Services/WikiHarvest`, Artisan) und läuft in einer geplanten Action, die JSON committet. Alternative: die Parser mit umschreiben. Verworfen, weil `wiki-genres` unverändert bleiben soll und die Parser nichts mit dem Host der Seite zu tun haben. Alternative: Laravel weiter als Server betreiben. Verworfen, weil GitHub Pages kein PHP ausführt.

2. **Bewertung in TypeScript.** `app/Domain/Matching` wird nach `frontend/src/domain/` übersetzt, ohne DOM und ohne Netz. Vor dem Löschen schreibt ein Pest-Lauf Golden Files (Katalogausschnitt, Antworten, Titelreihenfolge, Begründungen, Lockerungshinweise). Vitest muss sie bestehen. Die Zeilenabdeckung dieses Verzeichnisses bleibt mindestens 90 %. Alternative: PHP im Browser. Verworfen, weil dafür eine zweite Laufzeit nötig wäre und die Domain klein genug für eine Übersetzung ist.

3. **Katalog zur Build-Zeit.** Ein Node-Skript liest `database/data/*.json` und schreibt eine gleichherkunftliche `catalog.json` ins Ausgabeverzeichnis. Einträge, die im Wiki-Index fehlen, bleiben draußen, wie heute. `catalog.json` wird nach dem ersten Paint geladen, damit der Titel nicht auf 2,2 MB wartet. Die Rangliste danach ist lokal; Ziel auf dem CI-Browser: unter 300 ms, nachdem das JSON geparst ist. LCP ≤ 2,5 s, INP ≤ 200 ms, CLS ≤ 0,1 bleiben. Alternative: den gesamten Katalog als Modul in den ersten Script-Block packen. Verworfen, weil das den ersten Inhalt blockiert.

4. **Seiten ohne Framework.** Start, Impressum und Datenschutz werden HTML unter `frontend/`. Saubere Pfade entstehen als `impressum/index.html` und `datenschutz/index.html`. Der Assistent ist TypeScript gegen dieses HTML, Zustand unter dem Schlüssel `adventure_wizard`, Designwahl weiter unter `theme`. Tailwind 4 bleibt, `laravel-vite-plugin` entfällt. `base` ist `/`, passend zur eigenen Domain. Sichtbares Verhalten, das in der Arbeitskopie schon steckt (Hex-Flächen, Cover vergrößern), wird mit dem Markup und `resources/css/app.css` mitgenommen. Ein Klick auf ein Cover lädt kein zweites Bild. Alternative: ein neues UI-Framework. Verworfen, weil die Seite schon aus HTML, CSS und wenig Script besteht.

5. **Verwaltung ersatzlos weg.** `/verwaltung` ist keine gebaute Seite. GitHub Pages zeigt dafür `404.html`, ohne Formular. Unvollständig bleibt Kurzbeschreibung „-“ oder fehlender Katalogort, weiter vorschlagbar und in den Kennzahlen. Kuratieren heißt JSON im Repository ändern.

6. **Tests.** Vitest für die Bewertung, Playwright gegen `vite preview` für Ablauf, Tastatur, axe (hell und dunkel) und Bildvergleiche. Die Bildbaselines werden einmal neu gezogen, wenn das Markup steht. Pest, Pint, PHPStan und Deptrac bleiben für die PHP-Pipeline. Der Playwright-Server mit `database/playwright.sqlite` entfällt.

7. **Verfassung 2.0.0.** Prinzipien I, II, IV, V und VI werden auf diese zwei Artefakte umgeschrieben. Fachliche Garantien aus dem Proposal bleiben. Bewusste Lücken stehen unter Complexity Tracking.

8. **Veröffentlichung.** Action auf `main`: `npm ci`, Build, Artifact `dist/`, `actions/deploy-pages`. Rechte `contents: read`, `pages: write`, `id-token: write`. In `dist/` liegt eine Datei `CNAME` mit genau `dsa-abenteuerfinder.rath-ulrich.de`, sonst löscht jedes Deployment die Domain. Die Wiki-Sync-Action committet mit einem Fine-grained PAT (`CONTENTS_TOKEN`, nur dieses Repo, Contents schreiben). Ein Push mit `GITHUB_TOKEN` startet die Pages-Action nicht. Das Repository muss für Pages im Free-Tarif öffentlich sein.

## Risks / Trade-offs

- [Die TypeScript-Bewertung driftet vom PHP-Finder ab.] → Golden Files aus dem bestehenden Pest-Lauf sind das Tor. Der PHP-Finder wird erst gelöscht, wenn Vitest sie grün hat.
- [GitHub Pages setzt keine Antwort-Header.] → Siehe Complexity Tracking. HTTPS liefert GitHub. Skripte und Styles kommen von derselben Herkunft. Eine Meta-CSP erlaubt Skripte und Styles nur von `'self'` und Bilder von `'self'` sowie `https://de.wiki-aventurica.de`.
- [Cover bleiben Drittanbieter-Bilder.] → Wie heute, wegen Lizenz nicht kopiert. Kein neues Script, keine Schrift, kein Stylesheet von außen.
- [`catalog.json` ist öffentlich, wie die Seite.] → Der Katalog ist schon die öffentliche Empfehlung. Es stehen keine Konten und keine Geheimnisse darin.
- [Antworten liegen in `localStorage` auf dem Gerät.] → Die Datenschutzerklärung sagt das und erwähnt keine Server-Sitzung und kein Verwaltungspasswort mehr. Neu starten löscht den Schlüssel `adventure_wizard`.
- [DNS oder Zertifikat braucht Stunden.] → Die Seite ist vorher über `vite preview` prüfbar. „Enforce HTTPS“ erst einschalten, wenn GitHub das Zertifikat als bereit zeigt.

## Migration Plan

Es gibt keine Produktivdaten. Rückrollen heißt den Umbau per Git zurücknehmen und Pages in den Repository-Einstellungen abschalten. DNS kann liegen bleiben; ohne Pages-Seite liefert die Domain dann nichts aus diesem Projekt.

### Eigene Domain

Zielname: `dsa-abenteuerfinder.rath-ulrich.de`. Ein anderer Name, den du kontrollierst, funktioniert gleich: denselben Namen in `CNAME`, in den Pages-Einstellungen und im DNS eintragen. Die folgenden Schritte machst du selbst; der Build legt nur die `CNAME`-Datei.

1. Repository auf GitHub öffentlich halten (Pages im Free-Tarif).
2. Nach dem ersten grünen Pages-Lauf: **Settings → Pages → Build and deployment → Source: GitHub Actions**.
3. **Custom domain** auf `dsa-abenteuerfinder.rath-ulrich.de` setzen und speichern. Der Wert muss der Zeile in `CNAME` gleichen.
4. Beim DNS-Anbieter der Zone `rath-ulrich.de` einen Eintrag anlegen, und zwar nur diesen für den Hostnamen:
   - Typ: `CNAME`
   - Host: `dsa-abenteuerfinder` (manche Oberflächen wollen den vollen Namen `dsa-abenteuerfinder.rath-ulrich.de`)
   - Ziel: `rathch.github.io`
   - Nicht `rathch.github.io/dsa-abenteuerfinder` und kein zweiter A- oder AAAA-Eintrag für denselben Host.
5. Proxy vor GitHub auslassen (bei Cloudflare „DNS only“). Ein Proxy verhindert das Zertifikat.
6. In den Pages-Einstellungen warten, bis die DNS-Prüfung erfolgreich ist. Das kann bis zu einem Tag dauern, meist Minuten.
7. Danach **Enforce HTTPS** einschalten. Vorher bleibt es aus, sonst scheitert die Zertifikatstellung.
8. `https://dsa-abenteuerfinder.rath-ulrich.de/`, `/impressum/` und `/datenschutz/` öffnen. GitHub hängt bei Verzeichnis-Seiten oft einen Schrägstrich an; beide Formen müssen die jeweilige Seite zeigen, nicht das Repository-Präfix.

Die Apex-Domain `rath-ulrich.de` ist hier nicht das Ziel. Falls sie später auf Pages zeigen soll, sind das vier A-Records (`185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`) und vier AAAA-Records (`2606:50c0:8000::153`, `2606:50c0:8001::153`, `2606:50c0:8002::153`, `2606:50c0:8003::153`), kein CNAME auf der Apex, sofern der Anbieter das nicht ausdrücklich kann.

## Open Questions

Keine. Der Domainname ist der schon im Deploy-Dokument genannte, weil es noch keinen anderen Produktivnamen gibt.

## Complexity Tracking

- **Prinzip VI, Antwort-Header.** GitHub Pages lässt CSP, `X-Frame-Options`, `Referrer-Policy` und `Permissions-Policy` nicht als HTTP-Header setzen. Ersatz: HTTPS durch GitHub, keine Cookies, keine Verwaltung, Meta-CSP und `<meta name="referrer" content="no-referrer">`. `frame-ancestors` kann eine Meta-CSP nicht setzen. Akzeptiert, weil der gewählte Host das nicht kann.
- **Prinzip VII, Cover.** Bilder von `de.wiki-aventurica.de` bleiben, wie heute, Fremdabrufe. Keine weiteren Fremdressourcen.
- **Zwei Testläufer.** Vitest für die Bewertung, Pest für die Wiki-Pipeline. Deptrac gilt nur noch für das verbleibende PHP.
