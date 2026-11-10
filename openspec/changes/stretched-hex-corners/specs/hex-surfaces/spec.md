# Spec Delta

## Purpose

Legt fest, dass die Flächen des Finders als gestreckte Hexfelder mit 45-Grad-Schnitten erscheinen und Tastaturfokus sowie Trefferfläche dabei erhalten bleiben.

## ADDED Requirements

### Requirement: Kurze Flächen sind gestreckte Hexfelder

Buttons, Tags, einzeilige Eingaben der öffentlichen Seiten, der Link zum Hauptinhalt, die Leiste des Designschalters und der Fortschrittsbalken MUST als gestrecktes Sechseck erscheinen. Die kurzen Seiten MUST in einer Spitze auslaufen, deren Schnitt etwa 45 Grad beträgt.

#### Scenario: Button auf der Startseite

- **WHEN** die Startseite einen Button zeigt
- **THEN** laufen seine kurzen Seiten in einer Spitze aus
- **AND** die Fläche ist keine abgerundete Pille

#### Scenario: Tag an einer Empfehlung

- **WHEN** eine Empfehlung einen Tag zeigt
- **THEN** hat der Tag dieselbe gestreckte Sechseckform

#### Scenario: Öffentliche einzeilige Eingabe

- **WHEN** eine öffentliche Seite ein einzeiliges Eingabefeld zeigt
- **THEN** ist das Feld ein gestrecktes Sechseck

### Requirement: Hohe Flächen tragen eine feste 45-Grad-Fase

Karten, Antwortzeilen, Hinweise, die Ortsliste und ihre Einträge, Legendenkästchen, der Rechtshinweis, Verwaltungsfelder einschließlich mehrzeiliger Eingaben und Umschlagbilder MUST an den Ecken eine gerade 45-Grad-Fase fester Größe tragen. Diese Fase MUST die Fläche nicht zu einer Raute schließen.

#### Scenario: Karte im Assistenten

- **WHEN** der Assistent eine Karte zeigt, die höher ist als ein Button
- **THEN** sind ihre Ecken gerade im 45-Grad-Winkel angeschnitten
- **AND** die Seitenmitten bleiben stumpf

#### Scenario: Mehrzeiliges Verwaltungsfeld

- **WHEN** die Verwaltung ein mehrzeiliges Eingabefeld zeigt
- **THEN** trägt es dieselbe feste Fase
- **AND** seine kurzen Seiten laufen nicht in einer Spitze aus

### Requirement: Text und Trefferfläche bleiben nutzbar

Beschriftungen MUST vollständig in der sichtbaren Fläche liegen. Die Mindesthöhe von Buttons, Eingaben und Antwortzeilen MUST mindestens der Höhe vor dieser Änderung entsprechen.

#### Scenario: Beschrifteter Button

- **WHEN** ein Button eine Beschriftung trägt
- **THEN** schneidet die Spitze den Text nicht

#### Scenario: Antwortzeile bleibt treffbar

- **WHEN** eine Antwortzeile angezeigt wird
- **THEN** ist ihre Höhe mindestens so groß wie vor der neuen Silhouette

### Requirement: Fokus folgt der Silhouette

Ein Tastaturfokus auf Buttons, Antwortzeilen, dem Link zum Hauptinhalt, der Fehlerzusammenfassung und den Überschriften, die der Assistent nach einem Schritt fokussiert, MUST entlang der jeweiligen Silhouette sichtbar bleiben. Der Ring MUST den bisherigen Kontrast behalten.

#### Scenario: Button per Tastatur

- **WHEN** ein Button den Tastaturfokus erhält
- **THEN** ist ein Fokusring entlang der Hexform sichtbar

#### Scenario: Erzwungene Farben

- **WHEN** der Browser erzwungene Farben verwendet und ein Button den Fokus erhält
- **THEN** bleibt ein Fokusring sichtbar

### Requirement: Abbildungen und der Tintenstrich bleiben unverändert

Das Auge im Logo, die Tortenstücke, die Farblegenden der Diagramme und der Knopf des Designschalters MUST rund bleiben. Der Strich unter Überschriften MUST ein Strich bleiben.

#### Scenario: Katalogdiagramm

- **WHEN** die Startseite ein Kreisdiagramm zeigt
- **THEN** bleiben Tortenstücke und Farblegenden rund

#### Scenario: Designschalter

- **WHEN** der Designschalter sichtbar ist
- **THEN** ist seine Leiste ein gestrecktes Hexfeld
- **AND** sein Knopf bleibt rund
