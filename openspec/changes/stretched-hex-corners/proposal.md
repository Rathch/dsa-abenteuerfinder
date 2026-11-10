# Proposal

## Why

Buttons, Karten, Tags und die übrigen Flächen des Finders sind heute rund oder pillenförmig. Das wirkt wie ein generisches Formular und nicht wie die gestreckten Hexfelder, die zum Kartenbild von Aventurien gehören. Die Silhouette soll dieses Motiv tragen, ohne die gerade hergestellte Tastaturführung zu verlieren.

## What Changes

- Buttons, Tags (`.pill`), einzeilige öffentliche Eingaben, der Skip-Link, die Leiste des Designschalters und der Fortschrittsbalken werden gestreckte Hexfelder: die kurzen Seiten laufen in einer Spitze aus, der Schnitt liegt bei etwa 45 Grad.
- Karten, Antwortzeilen, Hinweise, die Ortsliste, ihre Einträge, Legendenkästchen, der Rechtshinweis und die Verwaltungsfelder inklusive Textarea bekommen dieselbe 45-Grad-Fase in fester Größe. Hohe Flächen bleiben Plaketten und fallen nicht zu Rauten zusammen. Umschlagbilder folgen derselben Fase.
- Der sichtbare Fokus folgt der neuen Silhouette und bleibt kontrastreich. Die bisherigen Mindesthöhen der Bedienelemente bleiben erhalten; der Innenabstand hält Text von den Spitzen frei.
- Kreise, die etwas abbilden, bleiben rund: Auge im Logo, Tortenstücke, Farblegenden der Diagramme und der Knopf des Designschalters. Der Tintenstrich unter Überschriften bleibt ein Strich.
- Keine neue Abhängigkeit. `corner-shape: bevel` trägt die Form nicht allein, weil Firefox und Safari sie im November 2026 noch nicht stabil können.

## Capabilities

### New Capabilities

- `hex-surfaces`: Silhouette von Buttons, Karten, Tags und den übrigen Flächen des Finders, einschließlich Fokus und Trefferfläche.

### Modified Capabilities

- Keine. `suggested-adventures` und `wiki-genres` beschreiben Katalog und Vorschläge, nicht die Form der Flächen.

## Impact

- Fast alle Radien liegen in `resources/css/app.css` (`.btn`, `.card`, `.pill`, `.choice`, `.callout`, `.field-input`, `.listbox`, Fortschritt, Designschalter). Die Umschlagbilder nutzen zusätzlich `rounded-md` in der Wizard-Ansicht.
- Öffentliche Seiten und die Verwaltung teilen sich diese Klassen, die Fußzeile eingeschlossen.
- Fokusringe, die heute über `outline` und `box-shadow` laufen, müssen an der beschnittenen Form sichtbar bleiben. Das betrifft Skip-Link, Buttons, Antwortzeilen, Fehlerzusammenfassung und die programmatisch fokussierten Überschriften.
- Die Bildvergleiche in `tests/visual/public/screenshots.spec.ts` ändern sich. Es gibt keine neue Route, kein Datenmodell und keine Importänderung.
