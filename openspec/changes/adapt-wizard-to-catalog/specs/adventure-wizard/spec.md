# Spec Delta

## Purpose

Beschreibt, welche Fragen der Abenteuerfinder stellt, welche davon eine Mehrfachauswahl sind und wie diese Antworten den noch möglichen Katalog eingrenzen.

## ADDED Requirements

### Requirement: Regeloptionen folgen dem Katalog

Der Wizard MUST für die Regeln jede Edition anbieten, die im noch möglichen Katalog vorkommt: DSA 1, DSA 2, DSA 3, DSA 4 und DSA 5. Er MUST NOT „editionsunabhängig“ als eigene Wahl anbieten. Zusätzlich MUST „Egal“ angeboten werden, solange der Pool nicht leer ist.

#### Scenario: Ältere Edition ist wählbar

- **WHEN** der noch mögliche Pool ein DSA-1-Abenteuer enthält und die Regelfrage angezeigt wird
- **THEN** ist DSA 1 eine sichtbare Wahl

#### Scenario: Fehlende Edition wird ausgeblendet

- **WHEN** der noch mögliche Pool kein DSA-2-Abenteuer enthält
- **THEN** wird DSA 2 nicht angezeigt

### Requirement: Editionsunabhängige Abenteuer bleiben zulässig

Ein editionsunabhängiges Abenteuer MUST zulässig bleiben, wenn mindestens eine konkrete Edition gewählt ist. „Egal“ MUST jede Edition und jedes editionsunabhängige Abenteuer zulassen.

#### Scenario: Konkrete Edition schließt editionsunabhängige Titel ein

- **WHEN** nur DSA 5 gewählt ist
- **THEN** bleibt ein editionsunabhängiges Abenteuer zulässig

#### Scenario: Egal schließt ältere Editionen ein

- **WHEN** „Egal“ bei den Regeln gewählt ist
- **THEN** bleibt ein DSA-1-Abenteuer zulässig

### Requirement: DSA 4 schließt DSA 4.1 ein

Die Wahl DSA 4 MUST Abenteuer einschließen, die als DSA 4 gespeichert sind, einschließlich solcher, die als DSA 4.1 beschrieben sind.

#### Scenario: Gespeichertes DSA-4-Abenteuer

- **WHEN** nur DSA 4 gewählt ist
- **THEN** bleibt ein als DSA 4 gespeichertes Abenteuer zulässig

### Requirement: Mehrfachauswahl filtert mit ODER

Bei Regeln, Regionen, Dauer und Exotik MUST mehr als eine konkrete Option wählbar sein. Ein Abenteuer MUST für dieses Feld zulässig bleiben, wenn es mindestens einen der gewählten Werte erfüllt.

#### Scenario: Zwei Editionen

- **WHEN** DSA 3 und DSA 5 gewählt sind
- **THEN** bleiben ein DSA-3-Abenteuer und ein DSA-5-Abenteuer zulässig
- **AND** ein DSA-1-Abenteuer bleibt wegen der Regeln nicht zulässig

#### Scenario: Zwei Regionen

- **WHEN** Nordmarken und Myranor gewählt sind
- **THEN** bleibt ein Abenteuer in einer dieser Regionen zulässig
- **AND** ein Abenteuer nur in Mittelaventurien bleibt wegen der Region nicht zulässig

#### Scenario: Zwei Dauern

- **WHEN** ein kurzer Abend und eine Kampagne gewählt sind
- **THEN** bleibt ein Abenteuer jeder dieser Dauern zulässig

#### Scenario: Zwei Settings

- **WHEN** klassisches Aventurien und außerhalb Aventuriens gewählt sind
- **THEN** bleibt ein Abenteuer, das eines dieser Settings erfüllt, zulässig

### Requirement: Leere Mehrfachauswahl filtert nicht

Eine leere konkrete Auswahl bei Regeln, Regionen, Dauer, Exotik oder Genres MUST NOT Abenteuer dieses Feldes ausschließen.

#### Scenario: Keine Regel gewählt

- **WHEN** keine Edition und nicht „Egal“ gewählt ist
- **THEN** bleiben Abenteuer jeder Edition hinsichtlich der Regeln zulässig

### Requirement: Egal ist exklusiv

Bei Regeln, Regionen und Exotik MUST „Egal“ konkrete Wahlen aufheben und dieses Feld nicht einschränken. Eine konkrete Wahl MUST „Egal“ aufheben.

#### Scenario: Egal ersetzt konkrete Editionen

- **WHEN** DSA 4 und DSA 5 gewählt sind und danach „Egal“ gewählt wird
- **THEN** ist nur „Egal“ gesetzt
- **AND** die Regeln schränken den Pool nicht ein

#### Scenario: Konkrete Region ersetzt Egal

- **WHEN** bei den Regionen „Egal“ gesetzt ist und danach Nordmarken gewählt wird
- **THEN** ist Nordmarken gesetzt und „Egal“ nicht

### Requirement: Genres bleiben Mehrfachauswahl

Genres MUST mehrfach wählbar bleiben. Ein Abenteuer MUST zulässig bleiben, wenn es mindestens ein gewähltes Genre trägt.

#### Scenario: Zwei Genres

- **WHEN** Horror und Dungeon gewählt sind
- **THEN** bleibt ein nur mit Horror gekennzeichnetes Abenteuer zulässig

### Requirement: Erfahrung, Spielleitung und Gruppengröße bleiben einfach

Helden-Erfahrung, Spielleitung und Gruppengröße MUST Einfachauswahlen bleiben. Eine neue Wahl MUST die vorherige Wahl desselben Feldes ersetzen.

#### Scenario: Eine Heldenerfahrung

- **WHEN** nach „Einsteiger“ „Meister“ gewählt wird
- **THEN** ist nur „Meister“ die Heldenerfahrung

### Requirement: Ort nur bei genau einer Region

Die optionale Ortsfrage MUST nur erscheinen, wenn genau eine konkrete Region gewählt ist. Sie MUST NOT erscheinen, wenn „Egal“ oder mindestens zwei Regionen gewählt sind.

#### Scenario: Mehrere Regionen

- **WHEN** zwei Regionen gewählt sind und die Regionsfrage abgeschlossen wird
- **THEN** wird keine Ortsfrage angezeigt

#### Scenario: Eine Region

- **WHEN** nur Nordmarken gewählt ist und die Regionsfrage abgeschlossen wird
- **THEN** kann optional ein Ort in dieser Region genannt werden

### Requirement: Optionen folgen dem verbleibenden Pool

Jede Frage MUST nur Optionen anzeigen, zu denen angesichts der übrigen Antworten noch mindestens ein Abenteuer passt.

#### Scenario: Regeln blenden eine Region aus

- **WHEN** nur DSA 1 gewählt ist und eine Region kein verbleibendes DSA-1-Abenteuer hat
- **THEN** wird diese Region nicht angeboten

### Requirement: Gespeicherte Einzelwerte werden verworfen

Ein früher gespeicherter einzelner Textwert für Regeln, Region, Dauer oder Exotik MUST NOT als aktuelle Antwort wiederhergestellt werden.

#### Scenario: Alte Sitzung

- **WHEN** eine gespeicherte Sitzung die Regeln als einzelnen Wert „dsa5“ enthält
- **THEN** startet die Regelauswahl leer
