# Design

## Context

Siehe `proposal.md`. Das Umschlagbild sitzt im Kopf der Ergebniskarte als `<img class="cover-thumb">` mit 96×128 Pixeln, leerem `alt` und ohne Steuerung. Die Quelle ist `coverUrl` oder `public/images/cover-fallback.svg`. `.cover-thumb` setzt `clip-path: none`, damit die Hex-Fase das Motiv nicht schneidet. `resources/js/app.js` steuert bestehende Kleinigkeiten über Dokument-Listener.

## Goals / Non-Goals

**Goals:**

- Dieselbe Bildadresse in der Karte vergrößern und mit der zweiten Aktivierung wieder auf 96×128 setzen.
- Der Zustand liegt am Button (`aria-expanded`) und übersteht keinen bewussten Server-Roundtrip. Ein Neuaufbau der Liste setzt ihn auf klein zurück.

**Non-Goals:**

- Keine schärfere Wiki-Datei als die gespeicherte Adresse. Ein 250-Pixel-Thumbnail bleibt so scharf, wie die Datei ist.
- Kein Dialog, kein Abdunkeln, keine Galerie und kein Wischen zur nächsten Karte.
- Das Ersatzbild ist keine Steuerung. Es bleibt 6rem×8rem.

## Decisions

1. **Der Umschlag ist ein Button und toggelt nur sich selbst.** `aria-expanded` wechselt zwischen `false` und `true`. Enter und Leertaste lösen den Klick eines echten Buttons aus, deshalb braucht es keinen eigenen Tastatur-Listener. Eine Ebene über der Seite würde die Karte verlassen.
2. **Die Größe steuert CSS am Button.** Klein bleibt 6rem×8rem mit `object-fit: cover`. Groß setzt `width: auto`, `height: auto`, `max-width: 18rem`, `max-height: 24rem` und `object-fit: contain`. Der Button bleibt auf schmalen Bildschirmen bei `max-width: 100%` und ab 640px bei `18rem`. Ein Foto bleibt bei seiner natürlichen Größe, wenn sie darunter liegt. Das Ersatz-SVG hat keine Pixelgröße und würde sonst die ganze Karte füllen; die Obergrenze hält es bei 18rem×24rem. Ab 640px bleibt der Titel neben dem Bild, auch in der großen Darstellung. Auf schmaleren Bildschirmen stehen Überschrift und Reihe über dem Bild, der übrige Karteninhalt folgt darunter.
3. **Dieselbe Adresse.** Die gespeicherte URL wird nicht in eine Vollauflösung umgeschrieben.
4. **Rechteck bleibt.** Weder der Button noch das Bild kommen in die Hex-`clip-path`-Gruppe. Der Button trägt kein `.btn`, damit die Pill-Fase das Motiv nicht schneidet.

## Risks / Trade-offs

- [Thumbnail bleibt weich, wenn es nur 250 Pixel breit ist] → Die Ansicht geht höchstens auf die natürliche Breite, begrenzt durch die Karte.
- [Livewire ersetzt die Liste] → Der Zustand liegt nur im DOM. Ein späterer Morph setzt den Umschlag auf klein zurück, statt eine verwaiste Ebene zu halten.
- [`stretched-hex-corners` will Umschläge anfasen] → Diese Änderung fasst sie nicht an. Ein späteres Anwenden jener Änderung darf `.cover-thumb` und `.cover-preview` nicht in die Fasen-Gruppe ziehen.
