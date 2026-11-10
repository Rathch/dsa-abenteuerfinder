# Proposal

## Why

293 von 577 auffindbaren Abenteuern tragen nur das Genre „Sonstiges“. Das Wiki schreibt in die Genre-Spalte eigene Bezeichnungen wie Mysterienabenteuer, Suchqueste oder Kulturerfahrung. Die Zuordnung kennt davon nur acht Gruppen und legt jeden unbekannten oder leeren Text auf „Sonstiges“.

## What Changes

- Die Genre-Liste übernimmt die Bezeichnungen aus den Genre-Zellen der Wiki-Indexseiten. Bekannte Aliasse bleiben: „Detektivgeschichte“ bleibt Ermittlung, „Stadtabenteuer“ bleibt Soziales Spiel, und die übrigen bestehenden Zuordnungen ebenso.
- Eine nicht leere Zelle, die keinem Alias entspricht, wird selbst zum Genre. Der sichtbare Name ist die Wiki-Formulierung. Mehrere Teile einer Zelle bleiben mehrere Genres. „Sonstiges“ kommt dabei nicht dazu.
- Nur eine leere Genre-Zelle in einer Tabelle, die eine Genre-Spalte hat, wird „Sonstiges“. Fehlt die Spalte, bleibt das Genre des Abenteuers unverändert.
- Zusammensetzungen wie „Reise- und Wildnisabenteuer“ werden vor der Zuordnung zu ganzen Bezeichnungen ergänzt, damit Bruchstücke wie „Reise-“ kein eigenes Genre werden.
- **BREAKING**: Bereits importierte Abenteuer, deren Genre nur wegen einer unbekannten Wiki-Bezeichnung „Sonstiges“ ist, erhalten beim nächsten Katalogabgleich die Wiki-Genres. Der Abgleich überschreibt bei diesen Zeilen nur die Genres, nicht die übrigen kuratierten Felder.

## Capabilities

### New Capabilities

- `wiki-genres`: Welche Wiki-Genre-Angaben ein Kataloggenre werden und wann „Sonstiges“ gilt.

### Modified Capabilities

- Keine. Unter `openspec/specs/` liegen noch keine Capabilities.

## Impact

- Genre-Zuordnung im Wiki-Mapper, Anlegen der neuen Tags beim Katalogabgleich und erneutes Setzen der Genres auf schon importierten Zeilen.
- Import prüft Genre-Slugs gegen `tags.json`. Neue Slugs müssen dort stehen, bevor der Import sie annimmt.
- Wizard und Startseiten-Diagramm lesen die Tags aus dem Katalog. Die Genre-Frage zeigt dadurch mehr Optionen. Das Diagramm zeigt weiter die häufigsten Genres.
- Keine neue Abhängigkeit. Die Wizard-Mehrfachauswahl aus `adapt-wizard-to-catalog` bleibt unberührt.
