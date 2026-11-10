# Proposal

## Why

Der Import enthält 1478 Abenteuer, die Startseite zeigt 577. Der Finder und die Diagramme lassen jeden Eintrag aus, dem die Wiki-Kurzbeschreibung fehlt oder dessen Schauplatz keinem Katalogort entspricht. Diese Abenteuer liegen schon in der Datenbank und sollen vorschlagbar sein.

## What Changes

- Der Finder schlägt auch unvollständige Abenteuer vor. Die Diagramme auf der Startseite zählen dieselben Abenteuer. Abenteuer, die im Wiki-Index fehlen, bleiben draußen.
- Fehlt die Kurzbeschreibung, speichert der Katalog genau „-“. Eine vorhandene Beschreibung bleibt unverändert.
- Die Ortsangabe aus der Wiki-Zelle wird übernommen und auf der Abenteuerkarte gezeigt. Ein bekannter Katalogort wie Havena bleibt der zugeordnete Ort. Eine unbekannte Formulierung bleibt als Text erhalten und wird kein neuer Eintrag in der Ortsliste des Assistenten. Die Regionszuordnung bleibt wie bisher: erkannte Region, sonst Mittelaventurien.
- Unvollständig bleibt ein Abenteuer, wenn die Beschreibung „-“ ist oder der Schauplatz kein Katalogort ist. Die Verwaltung listet diese Einträge weiter unter dem Filter „Unvollständig“.
- **BREAKING**: Bisher ausgeschlossene unvollständige Abenteuer erscheinen in den Vorschlägen und in den Diagrammen. Die angezeigte Zahl steigt über 577. Zeilen, die der Import in der Datenbank gelassen hat, obwohl sie nicht in `adventures.json` stehen, zählen mit, solange sie nicht als im Wiki fehlend markiert sind.

## Capabilities

### New Capabilities

- `suggested-adventures`: Welche gespeicherten Abenteuer der Finder und die Diagramme berücksichtigen, wie eine fehlende Kurzbeschreibung und ein Wiki-Schauplatz erscheinen, und dass die Verwaltung unvollständige Einträge weiter listet.

### Modified Capabilities

- Keine. `wiki-genres` beschreibt nur die Genre-Zuordnung.

## Impact

- Wiki-Zuordnung und Katalogabgleich für Beschreibung, Schauplatztext und das Kennzeichen unvollständig. Kuratierte vollständige Beschreibungen und Orte bleiben.
- Schema von `short_description`: die Mindestlänge 20 lässt „-“ heute nicht zu und muss dafür gesenkt werden.
- Finder, Match-Pool und `CatalogStatsService` filtern `incomplete` nicht mehr. `missing_from_wiki` bleibt ein Ausschluss.
- Ergebniskarte zeigt den Wiki-Schauplatz, wenn kein Katalogort zugeordnet ist.
- Verwaltung behält den Filter „Unvollständig“. Keine neue Abhängigkeit. Die Ortsliste im Assistenten bleibt die kuratierte Liste.
