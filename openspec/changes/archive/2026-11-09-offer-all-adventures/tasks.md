# Tasks

## 1. Kurzbeschreibung

- [x] 1.1 Fehlt die Wiki-Kurzbeschreibung, speichert die Zuordnung genau „-“, lässt das Abenteuer unvollständig und behält den Hinweis. Eine vorhandene Beschreibung bleibt. Das Schema akzeptiert „-“. Verify: `tests/Unit/Services/WikiHarvest/WikiAdventureMapperTest.php` prüft „-“ samt Unvollständig, und eine vorhandene Beschreibung bleibt. `tests/Feature/AdventuresImportTest.php` akzeptiert die kürzere Beschreibung.

## 2. Wiki-Schauplatz

- [x] 2.1 Die Wiki-Zelle wird als Schauplatztext gespeichert. Havena bleibt der Katalogort. Eine unbekannte Formulierung erscheint auf der Karte, kommt nicht in die Ortsliste des Assistenten, und ohne erkannte Region bleibt Mittelaventurien. Verify: `tests/Unit/Services/WikiHarvest/WikiAdventureMapperTest.php` deckt Havena und eine unbekannte Formulierung ab. Ein Test der Ergebniskarte zeigt den Wiki-Wortlaut.

## 3. Finder und Diagramme

- [x] 3.1 Finder, Facetten und Diagramme schließen unvollständige Abenteuer nicht mehr aus. Im Wiki fehlende bleiben draußen. Verify: `tests/Unit/Domain/Matching/MatchFinderTest.php` und `tests/Unit/Domain/Matching/WizardFacetFilterTest.php` lassen ein unvollständiges Abenteuer zu und lassen ein im Wiki fehlendes draußen. `tests/Feature/AdventureWizardTest.php` zählt in den Diagrammen dieselben Abenteuer wie der Finder.

## 4. Verwaltung

- [x] 4.1 Der Filter „Unvollständig“ listet Abenteuer mit „-“ und Abenteuer ohne Katalogort. Ein Eintrag mit Beschreibung und Katalogort erscheint dort nicht. Verify: ein Feature-Test der Verwaltung prüft beide Fälle und den vollständigen Eintrag.

## 5. Katalogstand

- [x] 5.1 `php artisan adventures:sync-wiki` ausführen. Verify: die Katalogzahl auf der Startseite liegt über 577, ein Abenteuer mit „-“ kann vorgeschlagen werden, und die Verwaltung listet es unter „Unvollständig“. Pint und die Tests aus den Gruppen 1 bis 4 sind grün.
