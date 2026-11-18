# Spec Delta

## Purpose

Startseite, Impressum und Datenschutz sind statische Dokumente. Ein Datei-Host und die eigene Domain liefern sie an der Wurzel aus, ohne Programm pro Anfrage.

## ADDED Requirements

### Requirement: Öffentliche Seiten sind Dateien

Startseite, Impressum und Datenschutz MUST als Dateien auslieferbar sein. Ein Host, der nur Dateien ausliefert, MUST sie zeigen können, ohne pro Anfrage ein Programm auszuführen.

#### Scenario: Startseite

- **WHEN** ein solcher Host die veröffentlichte Seite ausliefert und `/` geöffnet wird
- **THEN** erscheint die Startseite mit dem Assistenten

#### Scenario: Impressum

- **WHEN** `/impressum` geöffnet wird
- **THEN** erscheint das Impressum

#### Scenario: Datenschutz

- **WHEN** `/datenschutz` geöffnet wird
- **THEN** erscheint die Datenschutzerklärung

### Requirement: Eigene Domain an der Wurzel

Auf der eigenen Domain MUST dieselben Seiten an der Wurzel liegen. Der Repository-Name MUST NOT Teil des Pfads sein.

#### Scenario: Startseite auf der eigenen Domain

- **WHEN** die Wurzel der eigenen Domain geöffnet wird
- **THEN** erscheint die Startseite mit dem Assistenten

#### Scenario: Impressum ohne Repository-Pfad

- **WHEN** `/impressum` auf der eigenen Domain geöffnet wird
- **THEN** erscheint das Impressum

### Requirement: Keine Verwaltung

Die Veröffentlichung MUST keine Anmeldung und keinen Katalog-Editor anbieten.

#### Scenario: Bisheriger Verwaltungspfad

- **WHEN** `/verwaltung` geöffnet wird
- **THEN** erscheint kein Anmeldeformular und kein Editor

### Requirement: Datenschutz ohne Server-Sitzung

Die Datenschutzerklärung MUST den lokalen Durchlauf beschreiben. Sie MUST NOT eine Server-Sitzung oder eine Verwaltung mit Passwort behaupten.

#### Scenario: Lokale Antworten

- **WHEN** `/datenschutz` geöffnet wird
- **THEN** steht dort, dass die Assistenten-Antworten auf dem Gerät bleiben und nicht an den Host gesendet werden

#### Scenario: Keine Verwaltungs-Sitzung

- **WHEN** `/datenschutz` geöffnet wird
- **THEN** ist keine passwortgeschützte Verwaltung beschrieben

### Requirement: Skripte und Stile von derselben Herkunft

Die öffentlichen Seiten MUST Skripte und Stylesheets nur von derselben Herkunft laden.

#### Scenario: Geladene Skripte

- **WHEN** die Startseite angezeigt wird
- **THEN** stammt jedes Stylesheet und jedes Skript von derselben Herkunft wie die Seite
