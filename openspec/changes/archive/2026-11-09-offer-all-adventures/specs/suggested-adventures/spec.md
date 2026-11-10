# Spec Delta

## Purpose

Beschreibt, welche gespeicherten Abenteuer der Finder und die Diagramme berücksichtigen und wie eine fehlende Kurzbeschreibung oder ein Wiki-Schauplatz dabei erscheint.

## ADDED Requirements

### Requirement: Unvollständige Abenteuer sind vorschlagbar

Der Finder MUST ein gespeichertes Abenteuer vorschlagen können, auch wenn es unvollständig ist. Unvollständig allein MUST NOT ein Abenteuer aus den Vorschlägen ausschließen.

#### Scenario: Platzhalterbeschreibung wird vorgeschlagen

- **WHEN** ein Abenteuer die Kurzbeschreibung „-“ trägt und die übrigen Wünsche erfüllt
- **THEN** darf der Finder es vorschlagen

#### Scenario: Im Wiki fehlend bleibt draußen

- **WHEN** ein Abenteuer als im Wiki-Index fehlend markiert ist
- **THEN** schlägt der Finder es nicht vor

### Requirement: Diagramme zählen dieselben Abenteuer

Die Kennzahl und die Diagramme auf der Startseite MUST dieselben Abenteuer zählen, die der Finder vorschlagen darf.

#### Scenario: Unvollständiges Abenteuer zählt mit

- **WHEN** ein unvollständiges Abenteuer nicht als im Wiki fehlend markiert ist
- **THEN** erhöht es die Katalogzahl
- **AND** geht es in die Diagramme ein

#### Scenario: Im Wiki fehlend zählt nicht

- **WHEN** ein Abenteuer als im Wiki-Index fehlend markiert ist
- **THEN** fehlt es in der Katalogzahl und in den Diagrammen

### Requirement: Fehlende Kurzbeschreibung wird ein Strich

Fehlt die Kurzbeschreibung, MUST der Katalog genau „-“ speichern. Eine vorhandene Beschreibung MUST unverändert bleiben.

#### Scenario: Leere Beschreibung

- **WHEN** die Wiki-Kurzbeschreibung fehlt
- **THEN** trägt das Abenteuer die Kurzbeschreibung „-“

#### Scenario: Vorhandene Beschreibung bleibt

- **WHEN** eine Kurzbeschreibung vorhanden ist
- **THEN** bleibt dieser Text erhalten

### Requirement: Wiki-Schauplatz wird gezeigt

Die Ortsangabe der Wiki-Zelle MUST auf der Abenteuerkarte erscheinen. Entspricht sie einem Katalogort, MUST dieser Katalogort gelten. Eine unbekannte Formulierung MUST als Text erhalten bleiben und MUST NOT in die Ortsliste des Assistenten aufgenommen werden.

#### Scenario: Bekannter Ort bleibt Katalogort

- **WHEN** die Wiki-Zelle Havena nennt
- **THEN** zeigt die Karte den Katalogort Havena

#### Scenario: Unbekannte Formulierung bleibt lesbar

- **WHEN** die Wiki-Zelle eine Formulierung nennt, die kein Katalogort ist
- **THEN** zeigt die Karte diese Formulierung
- **AND** die Ortsliste des Assistenten enthält sie nicht

### Requirement: Regionszuordnung bleibt

Eine erkannte Region MUST die Region des Abenteuers bleiben. Nennt die Zelle keine erkannte Region, MUST das Abenteuer Mittelaventurien zugeordnet sein.

#### Scenario: Keine erkannte Region

- **WHEN** die Wiki-Zelle keine erkannte Region nennt
- **THEN** ist das Abenteuer Mittelaventurien zugeordnet

### Requirement: Unvollständig bleibt in der Verwaltung sichtbar

Ein Abenteuer mit der Kurzbeschreibung „-“ oder ohne Katalogort MUST unvollständig sein. Die Verwaltung MUST diese Einträge unter dem Filter „Unvollständig“ listen. Ein Abenteuer mit vorhandener Beschreibung und Katalogort MUST dadurch nicht unvollständig werden.

#### Scenario: Platzhalter bleibt unvollständig

- **WHEN** die Kurzbeschreibung „-“ ist
- **THEN** erscheint das Abenteuer in der Verwaltung unter „Unvollständig“

#### Scenario: Unbekannter Schauplatz bleibt unvollständig

- **WHEN** der Schauplatz kein Katalogort ist
- **THEN** erscheint das Abenteuer in der Verwaltung unter „Unvollständig“

#### Scenario: Vollständiger Eintrag bleibt vollständig

- **WHEN** Beschreibung und Katalogort vorhanden sind
- **THEN** ist das Abenteuer nicht unvollständig
