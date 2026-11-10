<?php

namespace App\Console\Commands;

use App\Services\WikiHarvest\WikiBlurbParser;
use App\Services\WikiHarvest\WikiCatalogSources;
use App\Services\WikiHarvest\WikiHttpClient;
use App\Services\WikiHarvest\WikiIndexTableParser;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'adventures:harvest-wiki', description: 'Lädt die Wiki-Indexseiten und Klappentexte von Wiki Aventurica in database/data/wiki-harvest/')]
class HarvestWikiAdventuresCommand extends Command
{
    /** @var string */
    protected $signature = 'adventures:harvest-wiki
                            {--from-cache : Nur Cache unter storage/app/wiki-cache nutzen}
                            {--pause=1000 : Pause zwischen HTTP-Requests in Millisekunden}
                            {--skip-blurbs : Keine Klappentext-Seiten laden}';

    public function handle(WikiIndexTableParser $parser, WikiBlurbParser $blurbParser): int
    {
        $cacheDir = storage_path('app/wiki-cache');
        $client = new WikiHttpClient(
            cacheDirectory: $cacheDir,
            pauseMilliseconds: max(0, (int) $this->option('pause')),
            fromCacheOnly: (bool) $this->option('from-cache'),
        );

        $rows = [];
        foreach (WikiCatalogSources::all() as $source) {
            $this->info('Lade '.$source['url'].' …');
            try {
                $html = $client->get($source['url']);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $parsed = $parser->parse($html, $source['series_key'], $source['series_name'], $source['split_by_heading']);
            $this->line(sprintf('  → %d Indexzeilen', count($parsed)));
            $rows = array_merge($rows, $parsed);
        }

        $withBlurbs = 0;
        if (! $this->option('skip-blurbs')) {
            $this->info(sprintf('Lade Klappentexte für %d Abenteuer …', count($rows)));
            foreach ($rows as $index => $row) {
                $path = $row['wiki_path'];
                $articlePath = $path ?? '/wiki/'.rawurlencode(str_replace(' ', '_', $row['title']));
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
                if ($description !== null) {
                    $withBlurbs++;
                }

                if (($index + 1) % 25 === 0 || $index + 1 === count($rows)) {
                    $this->line(sprintf('  … %d/%d (mit Text: %d)', $index + 1, count($rows), $withBlurbs));
                }
            }
        } else {
            foreach ($rows as $index => $row) {
                $rows[$index]['short_description_raw'] = null;
            }
        }

        $outDir = database_path('data/wiki-harvest');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }

        $payload = [
            'harvested_at' => now()->toIso8601String(),
            'sources' => array_column(WikiCatalogSources::all(), 'url'),
            'adventures' => $rows,
        ];

        $path = $outDir.'/adventures-raw.json';
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info(sprintf(
            'Rohdaten geschrieben: %s (%d Einträge, %d mit Kurzbeschreibung)',
            $path,
            count($rows),
            $withBlurbs,
        ));

        return self::SUCCESS;
    }
}
