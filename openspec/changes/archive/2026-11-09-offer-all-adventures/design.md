# Design

## Context

Siehe `proposal.md` für den Anlass. `WikiAdventureMapper` setzt `incomplete`, wenn die Kurzbeschreibung fehlt oder `mapLocation()` den Schauplatz keinem Katalogort und keiner Region sicher zuordnet. Fehlt die Beschreibung, steht heute ein Platzhaltertext in `short_description`. Ein unbekannter Ort fällt auf die Reise durch Mittelaventurien zurück und bleibt ungemappt. `MatchFinder`, `WizardFacetFilter`, `AdventureMatchService` und `CatalogStatsService` schließen `incomplete` aus. Die Verwaltung filtert dieselben Zeilen unter „Unvollständig“. `short_description` verlangt im Schema mindestens 20 Zeichen. Die Karte zeigt den Katalogort oder die Region, nicht den Wortlaut der Wiki-Zelle. Der Abgleich ersetzt unvollständige Zeilen und lässt vollständige kuratierte Zeilen stehen.

## Goals / Non-Goals

**Goals:**

- Unvollständige Abenteuer in Finder, Facetten und Diagramme aufnehmen, im Wiki fehlende weiter ausschließen.
- Fehlende Beschreibung als „-“ speichern und unbekannte Wiki-Schauplätze auf der Karte zeigen, ohne die Ortsliste des Assistenten zu erweitern.
- Das Kennzeichen unvollständig und den Verwaltungsfilter behalten.

**Non-Goals:**

- Neue Katalogorte oder eine andere Regionslogik.
- Zeilen löschen, die in der Datenbank stehen, aber nicht in `adventures.json`.
- Genre-Zuordnung oder die Wizard-Fragen ändern.

## Decisions

1. **Ausschluss nur noch über „fehlt im Wiki“.** Finder, Facettenpool, Match-Dienst und Diagramme filtern `incomplete` nicht mehr. `missing_from_wiki` bleibt. Alternative: `incomplete` für die behobenen Fälle auf falsch setzen. Verworfen, weil die Verwaltung diese Fälle weiter listen soll.

2. **Beschreibung.** Fehlt der Wiki-Text, wird `short_description` genau „-“. Die Mindestlänge im Schema sinkt auf 1. `source_note` bleibt gesetzt, damit ein unvollständiger Eintrag weiter einen Hinweis trägt. Eine vorhandene Beschreibung wird nicht ersetzt. Alternative: den heutigen langen Platzhalter lassen. Verworfen, weil die Vorgabe der einzelne Strich ist.

3. **Schauplatztext.** Die Zeile erhält ein optionales Textfeld mit dem Wortlaut der Wiki-Zelle. Ist ein Katalogort erkannt, zeigt die Karte weiter dessen Namen. Sonst zeigt sie diesen Wortlaut und danach die Region wie bisher. `primary_location` bleibt leer, die Regionszuordnung bleibt die heutige, und die Ortsliste des Assistenten liest weiter nur `locations.json`. Alternative: jede Wiki-Formulierung als neuen Ort anlegen. Verworfen, weil Zellen oft ganze Sätze sind und die Ortsauswahl damit unbenutzbar wird.

4. **Unvollständig.** Weiter wahr, wenn die Beschreibung „-“ ist oder kein Katalogort erkannt wurde. Beschreibung und Katalogort vorhanden heißt vollständig. Der Verwaltungsfilter bleibt an diesem Kennzeichen.

5. **Bestehende Zeilen.** Der nächste Katalogabgleich schreibt Beschreibung, Schauplatztext und das Kennzeichen auf unvollständige Zeilen neu. Vollständige kuratierte Zeilen bleiben, inklusive ihrer Beschreibung und ihres Orts. Danach importiert der bestehende Befehl den Katalog. Das neue Textfeld braucht eine Spalte; ohne sie bleibt der Import beim alten Kartenverhalten.

## Risks / Trade-offs

- [Die Katalogzahl liegt über 1478, weil der Import keine verwaisten Datenbankzeilen löscht.] → Außerhalb dieses Änderung. Die Zahl zählt gespeicherte, nicht als fehlend markierte Abenteuer.
- [Karten zeigen „-“ statt eines Satzes.] → Entspricht der Vorgabe. Die Verwaltung findet diese Zeilen weiter.
- [Unbekannte Orte bleiben bei der Region Mittelaventurien, wenn die Zelle keine Region nennt.] → Die Regionsfrage verhält sich wie bisher. Sichtbar ist zusätzlich der Wiki-Wortlaut.
- [Der Vorschlagspool wird größer.] → Dieselbe Wunschlogik, nur ohne den Unvollständig-Ausschluss.

## Migration Plan

Schema und Spalte ergänzen, Zuordnung und Filter anpassen, dann `php artisan adventures:sync-wiki` ausführen. Der Befehl importiert am Ende. Ein Rückrollen stellt den Code, die JSON-Dateien und die Migration zurück und importiert erneut.

## Open Questions

Keine.
