# Spec Delta

## Purpose

Erlaubt, den Umschlag einer Empfehlung an Ort und Stelle zu vergrößern und mit derselben Steuerung wieder zu verkleinern.

## ADDED Requirements

### Requirement: Umschlag einer Empfehlung lässt sich an Ort und Stelle vergrößern

Jedes eigene Umschlagbild einer Empfehlung MUST eine Steuerung sein. Die erste Aktivierung MUST dasselbe Bild in der Karte vergrößern. Die Empfehlung und die übrigen Karten MUST sichtbar bleiben. Ein Ersatzbild MUST sichtbar bleiben und MUST NOT vergrößerbar sein.

#### Scenario: Klick vergrößert den Umschlag

- **WHEN** eine Empfehlung einen Umschlag zeigt und die Steuerung zum ersten Mal aktiviert wird
- **THEN** ist dasselbe Bild in der Karte größer als zuvor
- **AND** die Empfehlung bleibt sichtbar

#### Scenario: Tastatur vergrößert den Umschlag

- **WHEN** die Umschlag-Steuerung den Tastaturfokus hat und mit Enter oder Leertaste zum ersten Mal aktiviert wird
- **THEN** ist dasselbe Bild in der Karte größer als zuvor
- **AND** der Fokus liegt weiter auf dieser Steuerung

#### Scenario: Ersatzbild bleibt klein

- **WHEN** eine Empfehlung kein eigenes Umschlagbild hat
- **THEN** zeigt sie das Ersatzbild in der kleinen Darstellung
- **AND** dieses Bild hat keine Vergrößerungs-Steuerung

#### Scenario: Andere Karte bleibt klein

- **WHEN** der Umschlag einer Empfehlung vergrößert ist
- **THEN** bleibt der Umschlag einer anderen Empfehlung klein

### Requirement: Dieselbe Steuerung verkleinert den Umschlag wieder

Eine zweite Aktivierung derselben Steuerung MUST das Bild auf die kleine Darstellung zurücksetzen. Der Fokus MUST auf dieser Steuerung liegen.

#### Scenario: Zweiter Klick verkleinert

- **WHEN** ein Umschlag vergrößert ist und dieselbe Steuerung erneut aktiviert wird
- **THEN** ist das Bild wieder klein
- **AND** der Fokus liegt auf dieser Steuerung

### Requirement: Der Umschlag bleibt rechteckig und benannt

Die Steuerung MUST einen zugänglichen Namen tragen, der den Abenteuertitel enthält. Sie MUST `aria-expanded` auf `true` setzen, solange das Bild groß ist, und auf `false`, solange es klein ist. Das Bild MUST an den Ecken nicht abgeschrägt sein.

#### Scenario: Zugänglicher Name

- **WHEN** eine Empfehlung einen Umschlag zeigt
- **THEN** hat die Steuerung einen Namen, der den Titel des Abenteuers enthält
- **AND** `aria-expanded` ist `false`, solange das Bild klein ist

#### Scenario: Rechteckiges Motiv

- **WHEN** der Umschlag vergrößert ist
- **THEN** sind die Ecken des Bildes nicht abgeschrägt
- **AND** die Ecken des kleinen Umschlags sind nicht abgeschrägt
