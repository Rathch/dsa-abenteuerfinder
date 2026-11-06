<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdventureImportService;
use App\Services\WikiHarvest\WikiAdventureMapper;
use App\Services\WikiHarvest\WikiBlurbParser;
use App\Services\WikiHarvest\WikiCatalogSyncService;
use App\Services\WikiHarvest\WikiHttpClient;
use App\Services\WikiHarvest\WikiIndexTableParser;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(
    name: 'adventures:sync-wiki',
    description: 'Prüft VA-/A-Reihe im Wiki: neue Abenteuer importieren, entfernte als missing markieren',
)]
class SyncWikiCatalogCommand extends Command
{
    /** @var string */
    protected $signature = 'adventures:sync-wiki
                            {--from-cache : Nur Cache unter storage/app/wiki-cache nutzen}
                            {--pause=1000 : Pause zwischen HTTP-Requests in Millisekunden}
                            {--skip-blurbs : Keine Klappentexte laden (neue Einträge nur mit Platzhalter)}
                            {--dry-run : Nur Bericht, keine JSON-/DB-Änderungen}';

    private const SOURCES = [
        [
            'url' => 'https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe',
            'series_key' => 'va-reihe',
        ],
        [
            'url' => 'https://de.wiki-aventurica.de/wiki/Abenteuer/A-Reihe',
            'series_key' => 'a-reihe',
        ],
    ];

    public function handle(
        WikiIndexTableParser $parser,
        WikiBlurbParser $blurbParser,
        AdventureImportService $importer,
    ): int {
        $cacheDir = storage_path('app/wiki-cache');
        $client = new WikiHttpClient(
            cacheDirectory: $cacheDir,
            pauseMilliseconds: max(0, (int) $this->option('pause')),
            fromCacheOnly: (bool) $this->option('from-cache'),
        );

        $rows = [];
        foreach (self::SOURCES as $source) {
            $this->info('Lade Index '.$source['url'].' …');
            try {
                $html = $client->get($source['url']);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $parsed = $parser->parse($html, $source['series_key']);
            $this->line(sprintf('  → %d DSA4/DSA5-Zeilen', count($parsed)));
            $rows = array_merge($rows, $parsed);
        }

        if ($rows === []) {
            $this->error('Wiki-Index lieferte keine Zeilen – Abbruch, um Fehlmarkierungen zu vermeiden.');

            return self::FAILURE;
        }

        $dataPath = database_path('data');
        $existing = $this->existingIdentities($dataPath);
        $newIndexes = [];
        foreach ($rows as $index => $row) {
            $title = (string) ($row['title'] ?? '');
            $path = isset($row['wiki_path']) && is_string($row['wiki_path']) ? $row['wiki_path'] : null;
            $key = mb_strtolower($title).'|'.($path ?? '');
            if (! isset($existing['titles'][mb_strtolower($title)]) && ! ($path !== null && isset($existing['paths'][$path]))) {
                $newIndexes[] = $index;
            }
            $rows[$index]['short_description_raw'] = null;
        }

        if (! $this->option('skip-blurbs') && $newIndexes !== []) {
            $this->info(sprintf('Lade Klappentexte für %d neue Einträge …', count($newIndexes)));
            foreach ($newIndexes as $progress => $index) {
                $row = $rows[$index];
                $path = $row['wiki_path'] ?? null;
                $articlePath = is_string($path) && $path !== ''
                    ? $path
                    : '/wiki/'.rawurlencode(str_replace(' ', '_', (string) $row['title']));
                $articleUrl = str_starts_with($articlePath, 'http')
                    ? $articlePath
                    : 'https://de.wiki-aventurica.de'.$articlePath;

                $description = null;
                $blurbHtml = $client->tryGet($blurbParser->klappentextUrl($articlePath));
                if ($blurbHtml !== null) {
                    $description = $blurbParser->extract($blurbHtml);
                }
                if ($description === null) {
                    $articleHtml = $client->tryGet($articleUrl);
                    if ($articleHtml !== null) {
                        $description = $blurbParser->extractFromArticle($articleHtml);
                    }
                }
                $rows[$index]['short_description_raw'] = $description;

                if (($progress + 1) % 10 === 0 || $progress + 1 === count($newIndexes)) {
                    $this->line(sprintf('  … %d/%d', $progress + 1, count($newIndexes)));
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-Run: keine Änderungen geschrieben.');
            $this->line(sprintf('Indexzeilen: %d, davon potenziell neu: %d', count($rows), count($newIndexes)));

            return self::SUCCESS;
        }

        /** @var array{places: array<string, array{place: string, region: string}>, regions: array<string, string>} $locationAliases */
        $locationAliases = $this->loadJson(database_path('data/wiki-harvest/location-aliases.json'));
        /** @var array{aliases: array<string, string>, fallback: string} $genreAliases */
        $genreAliases = $this->loadJson(database_path('data/wiki-harvest/genre-aliases.json'));

        $mapper = new WikiAdventureMapper($locationAliases, $genreAliases);
        $sync = new WikiCatalogSyncService($dataPath, $mapper);

        try {
            $summary = $sync->sync($rows);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $reportPath = storage_path('logs/wiki-catalog-sync.txt');
        file_put_contents(
            $reportPath,
            sprintf(
                "synced_at=%s\nadded=%d\nmarked_missing=%d\nrestored=%d\nskipped=%d\nunmapped=%d\n%s",
                now()->toIso8601String(),
                $summary['added'],
                $summary['marked_missing'],
                $summary['restored'],
                $summary['skipped'],
                count($summary['unmapped']),
                $summary['unmapped'] === [] ? '' : implode("\n", $summary['unmapped'])."\n",
            ),
        );

        try {
            $import = $importer->import();
        } catch (Throwable $e) {
            $this->error('Import nach Sync fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wiki-Sync: %d neu, %d als fehlend markiert, %d wiederhergestellt, %d kuratiert übersprungen. Import: %d Abenteuer.',
            $summary['added'],
            $summary['marked_missing'],
            $summary['restored'],
            $summary['skipped'],
            $import['adventures'],
        ));
        $this->line('Bericht: '.$reportPath);

        return self::SUCCESS;
    }

    /**
     * @return array{titles: array<string, true>, paths: array<string, true>}
     */
    private function existingIdentities(string $dataPath): array
    {
        $doc = $this->loadJson($dataPath.'/adventures.json');
        $titles = [];
        $paths = [];
        foreach ($doc['adventures'] ?? [] as $adventure) {
            if (! is_array($adventure)) {
                continue;
            }
            $title = mb_strtolower((string) ($adventure['title'] ?? ''));
            if ($title !== '') {
                $titles[$title] = true;
            }
            $url = (string) ($adventure['wiki_url'] ?? '');
            if (preg_match('~/wiki/([^?#]+)~', $url, $matches) === 1) {
                $paths['/wiki/'.rawurldecode($matches[1])] = true;
            }
        }

        return ['titles' => $titles, 'paths' => $paths];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
