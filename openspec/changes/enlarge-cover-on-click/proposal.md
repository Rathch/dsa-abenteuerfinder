# Proposal

## Why

Auf der Ergebniskarte ist das Umschlagbild nur 96×128 Pixel groß. Titel und Motiv sind darin kaum zu erkennen, und ein Klick auf das Bild tut nichts. Wer das Cover genauer sehen will, muss die Wiki-Seite öffnen.

## What Changes

- Ein Klick oder eine Tastaturaktivierung auf dem Umschlag einer Empfehlung vergrößert dasselbe Bild an Ort und Stelle in der Karte.
- Dieselbe Steuerung verkleinert es wieder. Der Fokus bleibt auf dem Umschlag.
- Jede Karte schaltet für sich. Die übrigen Empfehlungen bleiben unverändert sichtbar.
- Das Umschlagbild bleibt rechteckig, klein wie groß.
- Fehlt ein Cover, bleibt das Ersatzbild klein und lässt sich nicht vergrößern.
- Keine neue Route, keine Ebene über der Seite und kein zusätzlicher Bildabruf über die schon gespeicherte Cover-Adresse hinaus. Die Wiki-Seite bleibt ein eigener Link.

## Capabilities

### New Capabilities

- `cover-preview`: Umschlag einer Empfehlung an Ort und Stelle vergrößern und mit derselben Steuerung wieder verkleinern, ohne die Karte oder den Wizard zu verlassen.

### Modified Capabilities

- Keine. `suggested-adventures` regelt, welche Abenteuer vorgeschlagen werden, nicht wie ihr Umschlag gezeigt wird. `wiki-genres` bleibt unberührt.

## Impact

- Die Ergebniskarte in `resources/views/livewire/adventure-wizard.blade.php` und die Darstellung in `resources/css/app.css`.
- Ein Klick-Umschalter in `resources/js/app.js`, ohne Livewire-Roundtrip.
- Sichtprüfung und ein Test, der Vergrößern und Verkleinern abdeckt.
- Keine Änderung an Import, Matching oder den gespeicherten Cover-URLs.

Die laufende Änderung `stretched-hex-corners` verlangt für Umschlagbilder eine 45-Grad-Fase. Die ausgelieferte Karte hält Cover bewusst rechteckig, weil die Fase das Motiv abschneidet. Diese Änderung führt die Fase nicht wieder ein.
