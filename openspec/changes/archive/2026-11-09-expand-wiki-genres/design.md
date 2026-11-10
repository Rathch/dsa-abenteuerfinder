# Design

## Context

Siehe `proposal.md` für den Anlass. `WikiAdventureMapper::mapGenres()` teilt die Zelle an Komma, Semikolon, Schrägstrich und „ und “. Ein Teil wird einem der acht Genres aus `database/data/wiki-harvest/genre-aliases.json` zugeordnet, wenn er die Alias-Formulierung enthält oder die Formulierung ihn enthält. Trifft nichts, ist das einzige Genre `sonstiges`. `WikiIndexTableParser` schreibt bei fehlender Genre-Spalte dieselbe leere Zeichenkette wie bei einer leeren Zelle. `WikiCatalogSyncService` lässt vollständige bestehende Zeilen unverändert und legt in `tags.json` nur `sonstiges` nach. Der Import lehnt Genre-Slugs ab, die dort fehlen.

## Goals / Non-Goals

**Goals:**

- Unbekannte, nicht leere Wiki-Formulierungen als eigene Tags anlegen, ohne die acht bestehenden Zuordnungen zu ändern.
- Leere Zelle und fehlende Spalte auseinanderhalten.
- Beim Abgleich nur das Genre-Feld bestehender Zeilen aus einer vorhandenen Genre-Zelle neu setzen.

**Non-Goals:**

- Die acht Aliasse auf die wörtlichen Wiki-Bezeichnungen umstellen.
- Einmalige Formulierungen wieder in „Sonstiges“ zusammenfassen.
- Wizard-Ablauf, Regelfrage oder das Diagramm-Layout ändern.

## Decisions

1. **Zuordnungsreihenfolge.** Zuerst Bindestrich-Zusammensetzungen ergänzen, dann teilen, dann Aliasse, dann neue Tags. Ein Alias trifft nur, wenn der Zellteil die Formulierung enthält. Die umgekehrte Prüfung entfällt, weil sonst kurze Teile jede längere Formulierung treffen und kein neues Genre mehr entsteht. Alternative: die 81 unbekannten Teile aus dem alten Auszug fest in die Alias-Datei schreiben. Verworfen, weil der volle Katalog mehr Indexseiten hat als dieser Auszug.

2. **Neue Tags.** Slug über den deutschen Slug der Formulierung, sichtbarer Name in der Schreibung der Wiki-Zelle. Derselbe Slug behält den zuerst gesehenen Namen. „Sonstiges“ wird nicht zusätzlich gesetzt, sobald mindestens ein Genre erkannt wurde.

3. **Bindestrich.** Endet ein Teil auf einen Bindestrich und der nächste Teil auf `abenteuer`, `szenario`, `reise`, `queste`, `mission` oder `epos`, wird diese Endung an den Stamm gehängt. „Reise- und Wildnisabenteuer“ wird dadurch „Reiseabenteuer“ und „Wildnisabenteuer“. Ein hängender Stamm ohne solche Endung bleibt ein ganzes Wort ohne Bindestrich und wird kein Genre namens „Reise-“.

4. **Spalte oder leere Zelle.** Die geparste Zeile trägt ein Kennzeichen, ob die Tabelle eine Genre-Spalte hat. Leere Zelle bei vorhandener Spalte ergibt nur `sonstiges`. Ohne Spalte ersetzt der Abgleich keine vorhandenen Genres. Ein neu angelegtes Abenteuer, das nur aus Tabellen ohne Genre-Spalte kommt, erhält `sonstiges`, weil der Import mindestens ein Genre verlangt. Eine spätere Zeile mit Genre-Spalte ersetzt es.

5. **Bestehende Zeilen.** Trifft eine Wiki-Zeile mit Genre-Spalte ein vorhandenes Abenteuer, wird nur `genres` ersetzt. Das gilt auch für vollständige Zeilen und für Quellen, die sonst nicht überschreiben. Titel, Ort, Regeln und die übrigen Felder bleiben. Jeder neue Slug wird vor dem Schreiben der Abenteuer in `tags.json` angelegt.

## Risks / Trade-offs

- [Die Genre-Frage im Wizard wird länger.] → Optionen kommen weiter aus dem verbleibenden Pool. Das Diagramm zeigt weiter nur die häufigsten Genres.
- [Ein kuratierter Genre-Eintrag weicht von der Wiki-Zelle ab und wird ersetzt.] → Es ändert sich nur `genres`, und nur wenn die Quelle eine Genre-Spalte hat.
- [Einmalige oder schiefe Wiki-Formulierungen werden eigene Genres.] → Das entspricht der Vorgabe, nur leere Zellen auf „Sonstiges“ zu legen.
- [Der Katalogabgleich dauert so lange wie der letzte vollständige Lauf.] → Tests laufen gegen feste Zellen. Der Live-Abgleich steht erst am Ende und kann bei Abbruch erneut gestartet werden.

## Migration Plan

Nach den Tests den bestehenden Befehl `php artisan adventures:sync-wiki` ausführen und den Katalog importieren. Der Lauf schreibt die neuen Tags und setzt die Genres neu. Ein Rückrollen stellt den Code sowie `adventures.json` und `tags.json` aus Git wieder her und importiert erneut.

## Open Questions

Keine.
