# Tasks

## 1. Antwortmodell

- [x] 1.1 `WizardAnswers` speichert Regeln, Regionen und Exotik als Liste plus „Egal“-Kennzeichen und die Dauer als Liste ohne „Egal“. Leere Liste und „Egal“ filtern nicht, eine nicht leere Liste schließt „Egal“ aus. Verify: Unit-Test für beide Zustände und für den ODER-Abgleich von DSA 3 plus DSA 5, zwei Regionen, zwei Dauern und zwei Settings, einschließlich editionsunabhängiger Titel und gespeicherter DSA-4-Abenteuer.
- [x] 1.2 Beim Laden einer Sitzung werden Textwerte für diese vier Felder verworfen, Listen übernommen und die übrigen Felder behalten. Ein Ort wird geleert, wenn nicht genau eine konkrete Region ohne „Egal“ gesetzt ist. Verify: Unit-Test mit einer Sitzung, deren Regeln der Text `dsa5` sind, und einem zweiten Fall mit genau einer Region plus Ort.

## 2. Abgleich und Bewertung

- [x] 2.1 `MatchFinder` filtert Regeln, Regionen, Dauer und Exotik nach Entscheidung 2 und 7 in `design.md`. Nachbarn zählen nicht als harter Regionstreffer. Verify: `tests/Unit/Domain/Matching/MatchFinderTest.php` deckt zwei Editionen, eine ausgeschlossene dritte Edition, eine leere Regelauswahl, zwei Regionen und den Nachbarfall ab.
- [x] 2.2 `ScoreCalculator` gibt den Nachbarbonus, wenn keine gewählte Region exakt trifft und die Region des Abenteuers Nachbar mindestens einer gewählten Region ist. Verify: ein Unit-Test zeigt den Bonus für den Nachbarn und keinen Bonus für eine unverbundene Region.

## 3. Facetten und Optionen

- [x] 3.1 Die Regeloptionen sind DSA 1 bis DSA 5 plus „Egal“. Eine Edition erscheint nur, wenn ein Abenteuer genau diese `rule_version` trägt. „Editionsunabhängig“ ist keine Option. Verify: `tests/Unit/Domain/Matching/WizardFacetFilterTest.php` zeigt DSA 1 bei einem DSA-1-Abenteuer und blendet DSA 2 aus, wenn nur ein editionsunabhängiges Abenteuer übrig ist.
- [x] 3.2 Regionen, Dauer und Exotik bieten jede konkrete Option an, für die ein Probe-Pool mit genau dieser Option nicht leer ist. Regionen bleiben auf `RegionKey` beschränkt. Verify: derselbe Facetten-Test blendet eine Region aus, die nach der Wahl nur DSA 1 keinen Treffer mehr hat, und behält eine Dauer, die noch trifft.

## 4. Wizard-Ablauf

- [x] 4.1 Regeln, Regionen, Dauer und Exotik sind Checkboxen mit „Weiter“. „Egal“ leert die konkreten Werte, schließt das Feld ab und springt weiter. Eine konkrete Wahl hebt „Egal“ auf. Erfahrung, Spielleitung und Gruppengröße bleiben Radios mit einer Ersetzung der vorherigen Wahl. Verify: `tests/Feature/AdventureWizardTest.php` prüft die Checkboxen, den Sprung nach „Egal“, das Aufheben von „Egal“ durch Nordmarken und den Ersatz von „Einsteiger“ durch „Meister“.
- [x] 4.2 Die Ortsfrage erscheint nur nach genau einer konkreten Region. Bei zwei Regionen oder „Egal“ fehlt sie, und ein zuvor gesetzter Ort ist weg. Verify: Feature-Test für beide Fälle, plus ein Fall, in dem Nordmarken allein die optionale Ortsfrage zeigt.
- [x] 4.3 Zusammenfassung, gespeicherte Antworten und der visuelle Wizard-Helfer verwenden die neuen Listen. Ein alter Textwert startet das Feld leer. Verify: Feature-Test für die leere Regelauswahl aus `dsa5` und ein Durchlauf des bestehenden visuellen Helfers bis zur Empfehlung, sofern die visuelle Suite lokal ausführbar ist. Sonst den Helfer an den Feature-Test angleichen und das Auslassen im Testergebnis nennen.
