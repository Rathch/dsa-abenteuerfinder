# Design

## Context

Siehe `proposal.md` für den Anlass und `specs/hex-surfaces/spec.md` für das Verhalten. Die Flächen sitzen in `resources/css/app.css`: `.btn` und `.theme-toggle` sind Pillen (`border-radius: 9999px`, Mindesthöhe 2,85rem bzw. 2,75rem), `.pill` ebenfalls, `.field-input` öffentlich auch. `.card`, `.choice`, `.callout`, `.listbox` und die Listeneinträge nutzen Radien zwischen 0,85rem und 1,25rem. Der Fokus ist ein 3px-`outline` in `--focus` plus ein weicher `box-shadow`; bei Legenden greift zusätzlich `fieldset:focus-within`, weil Chrome eine fokussierte Legende nicht auf `:focus` passen lässt. `corner-shape: bevel` kann eine gerade Fase zeichnen und Rahmen, Outline und Schatten mitziehen, ist im November 2026 aber nur in Chromium stabil. Firefox und Safari können es nicht.

## Goals / Non-Goals

**Goals:**

- Eine Silhouette für kurze Flächen und eine für hohe Flächen, beide mit 45-Grad-Schnitten, in allen aktuellen Browsern gleich.
- Fokus, Rahmen und Schatten bleiben an der beschnittenen Form sichtbar. Die bestehenden Mindesthöhen bleiben.

**Non-Goals:**

- Eine zweite Form nur in Chromium über `corner-shape`.
- Neue Markup-Hülle um jedes Bedienelement.
- Das Auge, die Tortengrafik, die Farblegenden und der Tintenstrich.

## Decisions

1. **Zwei Polygone, ein Schnittwinkel.** Kurze Flächen (`.btn`, `.btn-sm`, `.pill`, öffentliches `.field-input`, `.skip-link`, `.theme-toggle-track`, `.progress-track` und `.progress-bar`) bekommen ein Sechseck: die Punkte sitzen auf der Mitte der kurzen Seiten. Die horizontale Einrückung ist die halbe Mindesthöhe der jeweiligen Fläche, damit der Schnitt etwa 45 Grad hat. Beispiel Button: Mindesthöhe 2,85rem, Einrückung 1,45rem; `.btn-sm` 2,55rem und 1,28rem; die Schalterleiste ist 1,7rem hoch, also 0,85rem. Hohe Flächen (`.card`, `.choice`, `.callout`, `.listbox` und seine Optionen, `.legal-note`, `.pie-legend`, `.admin-area .field-input`, Umschlagbild) bekommen ein Achteck mit fester Fase `--hex-cut: 1.35rem` auf beiden Achsen, also 45 Grad, ohne dass die Schnitte in der Seitenmitte zusammenlaufen. Der äußere `.theme-toggle` ist ein zusammengesetztes Bedienelement und nimmt dieselbe feste Fase, nicht die Spitze, damit Leiste und Beschriftung stehen bleiben. Alternative: überall die Spitze bei 50 Prozent der Höhe. Verworfen, weil eine hohe Karte zur Raute wird. Alternative: `corner-shape: bevel`. Verworfen, weil Firefox und Safari die Form dann nicht zeigen.

2. **`clip-path`, nicht eine zusätzliche Hülle.** Beide Formen sind `polygon()`-Werte an der Fläche selbst. Gleiche Einrückung auf x und y hält die Fase bei 45 Grad. `min(var(--hex-cut), 40%)` begrenzt die feste Fase, falls eine Fläche niedriger als die doppelte Fase ist, damit das Polygon sich nicht selbst schneidet. Rahmen bleiben am Element und werden mitbeschnitten. Der Schein von `.btn::after` bleibt und wird ebenfalls beschnitten. Alternative: Innen-Element mit der Form und Außen-Element für den Fokus. Verworfen, weil dafür jede Ansicht eine Hülle brauchte.

3. **Fokus und Schatten folgen der Alpha.** `clip-path` schneidet `outline` und `box-shadow` ab. Ein `outline-offset` von 3px läge vollständig außerhalb und verschwände. Sichtbare Ringe und die bisherigen Hub-Schatten wechseln auf gestapelte `filter: drop-shadow()`, die der beschnittenen Form folgen: ein harter Ring in `--focus` und der bestehende weiche Hof. Das gilt für Buttons einschließlich Hover, Antwortzeilen, den Skip-Link, die Fehlerzusammenfassung und den Designschalter. Überschriften, `#place-list` und das Fieldset der Legende bleiben unbeschnitten; ihr heutiger Outline-Ring bleibt, inklusive `fieldset:focus-within:not(:has(:focus))`. Unter `forced-colors: active` setzt die Fläche zusätzlich ein `outline`, weil Filter dort oft entfallen. Alternative: den rechteckigen Outline stehen lassen. Verworfen, weil die Spec den Ring an der Silhouette verlangt.

4. **Innenabstand.** Die Inline-Polsterung kurzer Flächen wächst um etwa die Einrückung, damit Beschriftungen die Spitze nicht schneiden. Mindesthöhen bleiben 2,85rem, 2,55rem und 2,75rem. Die Trefferfläche endet an der Form; die abgeschnittenen Rechteckecken waren bisher schon außerhalb der sichtbaren Pille.

5. **Umschlag und Seitenwahl.** Das Umschlagbild tauscht `rounded-md` gegen die feste Fase. Die Seitenwahl nutzt bereits `.btn`; die umgebende `rounded-md`-Spanne verliert den Radius, ohne eine eigene Form zu bekommen.

6. **Was rund bleibt.** `.theme-toggle-thumb`, `.pie-swatch`, `.logo-eye`, `.edition-eye` und der Tortenpfad behalten ihren Kreis. `.ink-rule::after` behält die runde Strichkappe. `.site-brand` und `.text-link` sind keine Flächen; ihr Fokusradius folgt dem unveränderten Outline der Überschriften und wird nicht zum Hex.

## Risks / Trade-offs

- [Enge Buttons, etwa die Seitenzahl, verlieren durch die Spitze Platz.] → `.btn-sm` nutzt die kleinere Einrückung. Die Seitenwahl wird im Browser geprüft; reicht der Platz nicht, wächst nur die Inline-Polsterung.
- [`drop-shadow` zeichnet auch den Inhalt und kann auf vielen Karten teurer sein als `box-shadow`.] → Der Finder zeigt wenige Karten zugleich. Die Schatten bleiben eine Stufe, nicht mehrere animierte Filter.
- [Hover-Ringe, die heute `outline` nutzen, verschwinden an beschnittenen Flächen.] → Dieselben Stellen bekommen den Drop-Shadow-Ring, nicht zusätzlich einen Outline.
- [Bildvergleiche schlagen nach der Formänderung fehl.] → Die Aufnahmen in `tests/visual/public/screenshots.spec.ts` werden neu erzeugt, erst nachdem die Fläche im Browser stimmt.

## Migration Plan

Die Änderung liegt im Stylesheet und an zwei Klassen in den Ansichten. Ein Rückrollen stellt `app.css` und diese Klassen wieder her. Es gibt keine gespeicherten Daten und keinen Import.

## Open Questions

Keine. Die genaue Fase darf beim Umsetzen innerhalb der Spec feinjustiert werden, solange kurze Flächen spitz und hohe Flächen stumpf bleiben.
