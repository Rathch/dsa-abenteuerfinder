# wiki-genres Specification

## Purpose

Beschreibt, welche Angaben aus der Genre-Spalte des Wikis ein Kataloggenre werden und wann ein Abenteuer nur „Sonstiges“ trägt.

## Requirements

### Requirement: Bekannte Aliasse bleiben

Eine Wiki-Formulierung, die einer bestehenden Zuordnung entspricht, MUST das zugehörige bestehende Genre ergeben. Die acht bestehenden Genres MUST erhalten bleiben.

#### Scenario: Detektivgeschichte bleibt Ermittlung

- **WHEN** die Genre-Zelle „Detektivgeschichte“ enthält
- **THEN** trägt das Abenteuer das Genre Ermittlung

#### Scenario: Stadtabenteuer bleibt Soziales Spiel

- **WHEN** die Genre-Zelle „Stadtabenteuer“ enthält
- **THEN** trägt das Abenteuer das Genre Soziales Spiel

### Requirement: Unbekannte Wiki-Genres werden eigene Genres

Eine nicht leere Wiki-Formulierung ohne bestehende Zuordnung MUST ein eigenes Genre mit dieser Formulierung als sichtbarem Namen werden. Sie MUST NOT als „Sonstiges“ gespeichert werden.

#### Scenario: Mysterienabenteuer

- **WHEN** die Genre-Zelle nur „Mysterienabenteuer“ enthält
- **THEN** trägt das Abenteuer ein Genre mit dem Namen Mysterienabenteuer
- **AND** trägt es nicht „Sonstiges“

#### Scenario: Suchqueste

- **WHEN** die Genre-Zelle nur „Suchqueste“ enthält
- **THEN** trägt das Abenteuer ein Genre mit dem Namen Suchqueste

### Requirement: Mehrere Formulierungen bleiben mehrere Genres

Enthält eine Zelle mehrere Formulierungen, MUST das Abenteuer jedes daraus erkannte Genre tragen. „Sonstiges“ MUST NOT ergänzt werden, sobald mindestens eine Formulierung ein Genre ergibt.

#### Scenario: Bekanntes und neues Genre

- **WHEN** die Genre-Zelle „Reiseabenteuer, Suchqueste“ enthält
- **THEN** trägt das Abenteuer Erkundung und Suchqueste
- **AND** trägt es nicht „Sonstiges“

### Requirement: Nur leere Genre-Zellen werden Sonstiges

Ist die Genre-Spalte vorhanden und die Zelle leer, MUST das Abenteuer genau das Genre „Sonstiges“ tragen.

#### Scenario: Leere Zelle

- **WHEN** die Tabelle eine Genre-Spalte hat und die Zelle des Abenteuers leer ist
- **THEN** trägt das Abenteuer nur „Sonstiges“

### Requirement: Fehlende Genre-Spalte ändert nichts

Hat eine Index-Tabelle keine Genre-Spalte, MUST der Abgleich daraus weder „Sonstiges“ setzen noch vorhandene Genres entfernen.

#### Scenario: Tabelle ohne Genre-Spalte

- **WHEN** ein bereits gespeichertes Abenteuer in einer Index-Tabelle ohne Genre-Spalte steht und bisher Erkundung trägt
- **THEN** trägt es danach weiterhin Erkundung

### Requirement: Bindestrich-Zusammensetzungen werden ergänzt

Eine Zusammensetzung aus abgekürztem Erstteil, „und“ und vollständigem Zweitteil MUST vor der Zuordnung zu ganzen Bezeichnungen ergänzt werden. Ein abgeschnittenes Bruchstück MUST NOT als eigenes Genre erscheinen.

#### Scenario: Reise- und Wildnisabenteuer

- **WHEN** die Genre-Zelle „Reise- und Wildnisabenteuer“ enthält
- **THEN** trägt das Abenteuer die Genres der ganzen Bezeichnungen Reiseabenteuer und Wildnisabenteuer
- **AND** erscheint kein Genre namens „Reise-“

### Requirement: Vorhandene Zeilen erhalten die Wiki-Genres

Der Katalogabgleich MUST die Genres eines schon gespeicherten Abenteuers aus seiner Wiki-Genre-Zelle neu setzen. Titel, Ort und die übrigen Felder MUST unverändert bleiben.

#### Scenario: Sonstiges wird ersetzt

- **WHEN** ein gespeichertes Abenteuer nur „Sonstiges“ trägt und die Wiki-Zelle „Kulturerfahrung“ enthält
- **THEN** trägt es danach das Genre Kulturerfahrung
- **AND** Titel und Ort bleiben dieselben
