# Proposal

## Why

Der Katalog enthält inzwischen Abenteuer für DSA 1 bis DSA 5 und editionsunabhängige Titel. Der Wizard fragt bei den Regeln aber weiterhin nur DSA 5, DSA 4.1 oder Egal ab, sodass ältere Editionen nur über „Egal“ erreichbar sind. Gleichzeitig sind fast alle übrigen Fragen Einfachauswahlen, obwohl eine Runde mehrere Editionen, Regionen, Dauern oder Settings akzeptieren kann.

## What Changes

- Die Regelfrage bietet jede Edition an, die im noch möglichen Katalog vorkommt: DSA 1, DSA 2, DSA 3, DSA 4 und DSA 5. DSA 4 umfasst die gespeicherte Edition DSA 4 einschließlich 4.1. „Editionsunabhängig“ ist keine eigene Wahl; solche Abenteuer bleiben bei jeder gewählten Edition und bei „Egal“ zulässig.
- Mehrfachauswahl mit ODER-Bedeutung für Regeln, Regionen, Dauer und Exotik. Genres bleiben Mehrfachauswahl. Eine leere konkrete Auswahl filtert dieses Feld nicht.
- „Egal“ bzw. „alle“ ist auf diesen Mehrfachfeldern exklusiv: es hebt konkrete Wahlen auf und setzt selbst keine Einschränkung. Eine konkrete Wahl hebt „Egal“ auf.
- Helden-Erfahrung, Spielleitung und Gruppengröße bleiben Einfachauswahl. Eine Gruppe und eine Spielleitung haben je einen Wert; eine Oder-Verknüpfung würde die Empfehlung fast ungefiltert machen.
- Der optionale Ort bleibt nur wählbar, wenn genau eine konkrete Region gewählt ist. Bei mehreren Regionen oder bei „Egal“ entfällt der Ort.
- Sichtbare Optionen entstehen weiter aus dem noch möglichen Abenteuer-Pool. Die Regionen bleiben die bestehenden Katalogregionen; diese Änderung führt keine neue Regionensystematik ein.
- **BREAKING**: Gespeicherte Wizard-Antworten, die Regeln, Region, Dauer oder Exotik als einzelnen Textwert halten, gelten nicht mehr. Ein laufender Durchlauf beginnt diese Felder leer.

## Capabilities

### New Capabilities

- `adventure-wizard`: Fragen, Mehrfachauswahl und Katalogbezug des Abenteuerfinders, einschließlich der Bedeutung der Antworten für die noch möglichen Abenteuer.

### Modified Capabilities

- Keine. Unter `openspec/specs/` liegen noch keine Capabilities.

## Impact

- Livewire-Wizard, Antwortmodell, Options- und Facettenlogik sowie die Bewertung der Wünsche gegen den Katalog.
- Die Wizard-Ansicht wechselt bei den neuen Mehrfachfeldern von Radios auf Checkboxen, analog zu den Genres.
- Bestehende Feature- und Matching-Tests für Einzelfelder und gespeicherte Antworten müssen die neue Antwortform abdecken.
- Keine neue Abhängigkeit und keine Änderung am Wiki-Import.
