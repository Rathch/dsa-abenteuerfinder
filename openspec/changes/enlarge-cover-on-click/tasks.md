# Tasks

## 1. Umschlag als Steuerung

- [x] 1.1 In `resources/views/livewire/adventure-wizard.blade.php` wird das Umschlagbild jeder Empfehlung ein `type="button"` mit dem Namen „Umschlag von {Titel}“, `data-cover-preview` und `aria-expanded="false"`. Das Bild darin behält die Cover-Adresse oder `images/cover-fallback.svg`. Es gibt kein `dialog`. Verify: `tests/Feature/AdventureWizardTest.php` sieht bei gesetzter Cover-URL den Button-Namen des ersten Titels, `aria-expanded="false"` und diese URL. Ein zweiter Fall setzt `cover_url` auf null und erwartet `images/cover-fallback.svg`.

- [x] 1.2 `.cover-preview` und `.cover-thumb` bleiben außerhalb der Hex-Fasen-Gruppen und bekommen kein `.btn`. Klein bleibt 6rem×8rem mit `object-fit: cover`. Groß setzt `width: auto`, `height: auto`, `max-width: min(100%, 18rem)`, `max-height: 24rem`, `object-fit: contain` und `clip-path: none`. Verify: die Fasen-Selektoren in `resources/css/app.css` enthalten `.cover-thumb` und `.cover-preview` nicht, und die Cover-Regel setzt `clip-path: none`.

## 2. Umschalten

- [x] 2.1 `resources/js/app.js` toggelt bei Klick auf `[data-cover-preview]` nur das `aria-expanded` dieses Buttons. Enter und Leertaste nutzen den Klick des Buttons. Verify: `tests/visual/public/wizard-flow.spec.ts` klickt den ersten Umschlag, sieht ihn größer, die Empfehlung weiter sichtbar und `clip-path: none`, lässt die nächste Karte klein, klickt ein zweites Mal und sieht ihn wieder klein. Enter und Leertaste schalten denselben Button um, und der Fokus bleibt darauf.
