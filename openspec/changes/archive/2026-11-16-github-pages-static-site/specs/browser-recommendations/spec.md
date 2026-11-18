# Spec Delta

## Purpose

Die Rangliste passender Abenteuer entsteht im Browser aus dem mitgelieferten Katalog. Antworten eines Durchlaufs bleiben auf dem Gerät.

## ADDED Requirements

### Requirement: Rangliste bleibt gleich

Für denselben Katalog und dieselben abgeschlossenen Antworten MUST Titelreihenfolge, Begründungen und Hinweise zur Lockerung mit der Empfehlung übereinstimmen, die der Finder vor diesem Umbau ausgibt.

#### Scenario: Gleicher Katalog und gleiche Antworten

- **WHEN** Katalog und abgeschlossene Antworten festliegen
- **THEN** sind Titelreihenfolge, Begründungen und Lockerungshinweise dieselben wie vor dem Umbau

#### Scenario: Wiederholtes Abschicken

- **WHEN** dieselben Antworten erneut abgeschickt werden
- **THEN** ist die Rangliste identisch

### Requirement: Bewertung ohne Host-Programm

Nachdem Seite und Katalog geladen sind, MUST eine Rangliste ohne Programm auf dem Host entstehen.

#### Scenario: Ergebnis nach dem Laden

- **WHEN** Seite und Katalog geladen sind und die Antworten abgeschickt werden
- **THEN** erscheint die Rangliste
- **AND** dafür läuft kein Programm auf dem Host

### Requirement: Katalog reist mit der Veröffentlichung

Die veröffentlichten Dateien MUST den vorschlagbaren Katalog enthalten. Die Rangliste MUST NOT eine dynamische Schnittstelle verlangen.

#### Scenario: Rangliste aus den Dateien

- **WHEN** nur die veröffentlichten Dateien verfügbar sind
- **THEN** kann der Finder daraus eine Rangliste bilden

### Requirement: Antworten bleiben auf dem Gerät

Ein laufender oder abgeschlossener Durchlauf MUST auf dem Gerät erhalten bleiben und MUST NOT an den Host gesendet werden.

#### Scenario: Seite neu laden

- **WHEN** die Seite nach eingegebenen Antworten neu geladen wird
- **THEN** sind diese Antworten wieder da
- **AND** der Host hat sie nicht erhalten

### Requirement: Neu starten verwirft den Durchlauf

Neu starten MUST die gespeicherten Antworten dieses Durchlaufs auf dem Gerät entfernen.

#### Scenario: Neu starten

- **WHEN** ein Durchlauf gespeichert ist und Neu starten gewählt wird
- **THEN** beginnt der Assistent ohne diese Antworten

### Requirement: Ohne Konto nutzbar

Der Finder MUST ohne Anmeldung nutzbar sein.

#### Scenario: Erster Besuch

- **WHEN** jemand die Startseite ohne Konto öffnet
- **THEN** kann der Assistent bis zur Rangliste durchlaufen werden
