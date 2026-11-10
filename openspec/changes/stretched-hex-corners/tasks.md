# Tasks

## 1. Silhouette

- [x] 1.1 In `resources/css/app.css` kurze Flächen auf das Sechseck setzen: `.btn`, `.btn-sm`, `.pill`, öffentliches `.field-input`, `.skip-link`, `.theme-toggle-track`, `.progress-track` und `.progress-bar`. Die Einrückung ist die halbe Mindesthöhe, also 1,45rem, bei `.btn-sm` 1,28rem und bei der Schalterleiste 0,85rem. Hohe Flächen auf das Achteck mit `--hex-cut: 1.35rem` und `min(..., 40%)` setzen: `.card`, `.choice`, `.callout`, `.listbox` samt Optionen, `.legal-note`, `.pie-legend`, `.admin-area .field-input` und der äußere `.theme-toggle`. Auge, Tortenpfad, `.pie-swatch`, `.theme-toggle-thumb` und `.ink-rule::after` bleiben rund bzw. ein Strich. Verify: im Browser hat ein Button ein `clip-path`, dessen Spitzen auf halber Höhe sitzen, und eine Karte ein Achteck mit stumpfen Seitenmitten. Die ausgenommenen Grafiken behalten ihren Kreis oder Strich.

- [x] 1.2 Die Inline-Polsterung der kurzen Flächen so vergrößern, dass Beschriftungen die Spitze nicht schneiden. Die Mindesthöhen 2,85rem, 2,55rem und 2,75rem bleiben. Das Umschlagbild in `resources/views/livewire/adventure-wizard.blade.php` tauscht `rounded-md` gegen die feste Fase. Die `rounded-md`-Spanne in `resources/views/vendor/pagination/tailwind.blade.php` verliert den Radius. Verify: Button- und Tag-Text liegen vollständig in der Fläche, die berechnete Mindesthöhe der Buttons bleibt, und das Umschlagbild trägt die Fase.

## 2. Fokus und Schatten

- [x] 2.1 Hub-Schatten sowie Hover- und Fokusringe der beschnittenen Flächen auf `filter: drop-shadow()` umstellen, der der Form folgt. An diesen Flächen keinen zusätzlichen `outline` stehen lassen. Überschriften, `#place-list` und `fieldset:focus-within:not(:has(:focus))` behalten den bisherigen Outline-Ring. Unter `forced-colors: active` bekommen die beschnittenen Flächen zusätzlich einen Outline. Verify: Tastaturfokus auf Button, Antwortzeile, Skip-Link und Fehlerzusammenfassung zeigt den Ring an der Form. Eine fokussierte Legende behält den bisherigen Ring. In erzwungenen Farben bleibt ein Outline sichtbar.

## 3. Seitenübergreifende Prüfung

- [ ] 3.1 Startseite, Assistent, Empfehlung mit Tag, Fußzeile und eine Verwaltungsseite im hellen und im dunklen Design prüfen. Enge Seitenwahl-Buttons prüfen und bei Bedarf nur die Inline-Polsterung von `.btn-sm` vergrößern. Danach die Aufnahmen mit `npm run build && playwright test tests/visual/public/screenshots.spec.ts --update-snapshots` neu erzeugen und dieselbe Datei ohne `--update-snapshots` erneut laufen lassen. Verify: die Aufnahmen stimmen mit der neuen Form überein. Läuft die Suite lokal nicht, den Grund nennen. `tests/visual/public/keyboard-guardrails.spec.ts` bleibt grün, sofern sie lokal läuft.
