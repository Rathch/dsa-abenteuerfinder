# Design

## Context

Siehe `proposal.md` für den Anlass. Heute hält `WizardAnswers` Regeln, Region, Dauer und Exotik als je einen Textwert. `RulesWish` kennt nur `dsa5`, `dsa4` und `any`. Die Regelfrage beschriftet `dsa4` mit „DSA 4.1“. Der Katalog speichert dagegen `dsa1` bis `dsa5` und `editionunabhaengig`. Genres sind bereits eine Liste und werden in der Ansicht per Checkbox gewählt. Die übrigen Fragen sind Radios und springen nach der Wahl weiter. `WizardFacetFilter` blendet Optionen aus, die im Pool der übrigen Antworten keinen Treffer mehr haben. Die Regionsfacette prüft nur die bestehenden `RegionKey`-Fälle.

## Goals / Non-Goals

**Goals:**

- Regelwünsche als Menge der Katalogeditionen DSA 1 bis DSA 5 ausdrücken, ohne eine eigene Wahl „editionsunabhängig“.
- Dieselbe ODER-Semantik für Regionen, Dauer und Exotik, ohne das Genre-Verhalten zu ändern.
- Facetten weiterhin aus dem verbleibenden Pool ableiten, wobei eine Edition nur erscheint, wenn ein Abenteuer genau diese Edition trägt.

**Non-Goals:**

- Neue Regionen, Nachbarschaften oder ein Import zusätzlicher Katalogfelder.
- Mehrfachauswahl für Helden-Erfahrung, Spielleitung oder Gruppengröße.
- Eine Datenbankmigration.

## Decisions

1. **Antwortform.** `WizardAnswers` speichert für Regeln, Regionen, Dauer und Exotik je eine Liste konkreter Werte plus ein Kennzeichen „Egal“ bei Regeln, Regionen und Exotik. Die Dauer hat kein „Egal“. Eine leere Liste ohne „Egal“ und „Egal“ selbst filtern das Feld nicht. Eine nicht leere Liste schließt „Egal“ aus. Alternative: nur die Liste, „Egal“ als besonderer Eintrag darin. Verworfen, weil die Spec „Egal“ und die leere Auswahl als getrennte Zustände mit demselben Filterergebnis führt und die Zusammenfassung „Egal“ anzeigen können muss.

2. **Regelabgleich.** Ein Abenteuer passt zu den Regeln, wenn keine Einschränkung gilt oder seine Edition in der gewählten Menge liegt oder es editionsunabhängig ist. DSA 4 bleibt der eine gespeicherte Wert für DSA 4 und DSA 4.1. `RulesWish` wird dafür nicht um drei weitere Fälle erweitert. Alternative: pro Edition ein Wunsch mit der heutigen `accepts()`-Methode. Verworfen, weil editionsunabhängige Titel dann in jedem Wunsch stecken und die Facette jede Edition anzeigen würde, sobald nur noch solche Titel übrig sind.

3. **Facetten.** Eine konkrete Option wird angezeigt, wenn ein Probe-Pool mit genau dieser einen Option und den übrigen Antworten nicht leer ist. Für Editionen zählt nur ein Abenteuer mit genau dieser `rule_version`, nicht ein editionsunabhängiges. „Egal“ bleibt sichtbar, solange der Pool nicht leer ist. Regionen bleiben auf `RegionKey` beschränkt.

4. **Ort.** `placeSlug` wird geleert, sobald nicht genau eine konkrete Region ohne „Egal“ gewählt ist. Die Ortsfrage erscheint nur in diesem Fall. Der bisherige Ortsabgleich bleibt auf diese eine Region beschränkt.

5. **Bedienung.** Konkrete Mehrfachfelder nutzen Checkboxen und einen Abschluss „Weiter“, analog zu den Genres. „Egal“ hebt die konkreten Werte sofort auf, schließt das Feld ab und führt zur nächsten offenen Frage, damit der bisherige Ein-Klick-Weg erhalten bleibt. Radios für Erfahrung, Spielleitung und Gruppengröße bleiben und springen weiterhin nach der Wahl weiter.

6. **Sitzung.** Beim Laden wird ein gespeicherter Textwert für Regeln, Region, Dauer oder Exotik verworfen. Eine bereits gespeicherte Liste wird übernommen. Die übrigen Felder bleiben erhalten.

7. **Bewertung.** Der harte Filter für mehrere Regionen ist ein exakter Treffer in einer der gewählten Regionen, ohne Nachbarn. Die bestehende Nachbarschaftsbewertung gilt, wenn keine gewählte Region exakt trifft und die Region des Abenteuers Nachbar mindestens einer gewählten Region ist.

## Risks / Trade-offs

- [Laufende Sitzungen verlieren Regeln, Region, Dauer und Exotik.] → Nur diese vier Felder werden verworfen. Die übrigen Antworten bleiben. Es gibt keine stillschweigende Abbildung von `dsa5` auf die neue Liste.
- [Viele gewählte Editionen plus editionsunabhängige Titel verbreitern das Ergebnis stark.] → Das ist die gewollte ODER-Semantik. Die Facetten der folgenden Fragen begrenzen den weiteren Pool.
- [„Egal“ und eine leere Auswahl filtern gleich.] → Die Zusammenfassung zeigt „Egal“ nur, wenn das Kennzeichen gesetzt ist. Eine noch nicht abgeschlossene leere Auswahl erscheint nicht als Antwort.

## Migration Plan

Keine Schemaänderung. Nach dem Ausrollen starten betroffene Wizard-Durchläufe die vier Felder leer und fragen sie erneut. Ein Rückrollen des Codes stellt die alte Antwortform wieder her. Zwischenstände der neuen Listen werden dann ignoriert.

## Open Questions

Keine. Die Frage, ob „Egal“ sofort weiterführt, ändert die Spec nicht und ist in Entscheidung 5 festgelegt.
