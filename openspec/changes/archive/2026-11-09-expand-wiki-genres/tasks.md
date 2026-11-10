# Tasks

## 1. Genre-Zuordnung

- [x] 1.1 `mapGenres()` ergänzt Bindestrich-Zusammensetzungen, ordnet einen Teil nur zu, wenn er die Alias-Formulierung enthält, und macht jeden übrigen nicht leeren Teil zu einem eigenen Genre mit Wiki-Namen. Eine leere Zelle ergibt nur `sonstiges`. Verify: `tests/Unit/Services/WikiHarvest/WikiAdventureMapperTest.php` deckt Detektivgeschichte, Stadtabenteuer, Mysterienabenteuer, Suchqueste, „Reiseabenteuer, Suchqueste“, eine leere Zelle und „Reise- und Wildnisabenteuer“ ab.

## 2. Genre-Spalte

- [x] 2.1 Der Index-Parser kennzeichnet, ob die Tabelle eine Genre-Spalte hat, und unterscheidet das von einer leeren Zelle. Verify: `tests/Unit/Services/WikiHarvest/WikiIndexTableParserTest.php` prüft eine Tabelle mit leerer Genre-Zelle und eine Tabelle ohne Genre-Spalte.

## 3. Katalogabgleich

- [x] 3.1 Der Abgleich ersetzt bei einer vorhandenen Genre-Spalte nur `genres` einer bestehenden Zeile, auch wenn sie vollständig ist, und legt jeden neuen Slug in `tags.json` an. Ohne Genre-Spalte bleiben vorhandene Genres. Ein neues Abenteuer ohne Genre-Spalte erhält `sonstiges`. Verify: `tests/Feature/WikiCatalogSyncTest.php` zeigt Kulturerfahrung statt Sonstiges bei unverändertem Titel und Ort, einen neuen Tag-Eintrag und eine Zeile ohne Genre-Spalte, deren Erkundung bleibt.

## 4. Katalogstand

- [x] 4.1 `php artisan adventures:sync-wiki` ausführen und den Katalog importieren. Verify: danach gibt es ein Genre Mysterienabenteuer, und die Zahl auffindbarer Abenteuer, die nur Sonstiges tragen, liegt unter 293. Pint und die Tests aus den Gruppen 1 bis 3 sind grün.
