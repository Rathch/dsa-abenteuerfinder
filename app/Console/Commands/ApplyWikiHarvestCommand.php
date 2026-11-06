<?php

namespace App\Console\Commands;

use App\Services\WikiHarvest\WikiAdventureMapper;
use App\Services\WikiHarvest\WikiHarvestApplicator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'adventures:apply-wiki-harvest', description: 'Mappt wiki-harvest Rohdaten nach adventures.json (complete kuratiert bleibt erhalten)')]
class ApplyWikiHarvestCommand extends Command
{
    /** @var string */
    protected $signature = 'adventures:apply-wiki-harvest
                            {--raw= : Pfad zur adventures-raw.json (Standard: database/data/wiki-harvest/adventures-raw.json)}
                            {--keep-harvested : Vorherige VA-/A-Reihe-Harvest-Einträge nicht entfernen}';

    public function handle(): int
    {
        $rawPath = $this->option('raw') ?: database_path('data/wiki-harvest/adventures-raw.json');
        if (! is_file($rawPath)) {
            $this->error('Rohdaten fehlen: '.$rawPath.' (zuerst adventures:harvest-wiki ausführen)');

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($rawPath), true);
        if (! is_array($decoded) || ! isset($decoded['adventures']) || ! is_array($decoded['adventures'])) {
            $this->error('Ungültige Rohdaten: '.$rawPath);

            return self::FAILURE;
        }

        /** @var array{places: array<string, array{place: string, region: string}>, regions: array<string, string>} $locationAliases */
        $locationAliases = $this->loadJson(database_path('data/wiki-harvest/location-aliases.json'));
        /** @var array{aliases: array<string, string>, fallback: string} $genreAliases */
        $genreAliases = $this->loadJson(database_path('data/wiki-harvest/genre-aliases.json'));

        $mapper = new WikiAdventureMapper($locationAliases, $genreAliases);
        $applicator = new WikiHarvestApplicator(database_path('data'), $mapper);

        if (! $this->option('keep-harvested')) {
            $purged = $applicator->purgeHarvestedSeries(['va-reihe', 'a-reihe']);
            $this->line(sprintf('Vorherige Harvest-Einträge entfernt: %d', $purged));
        }

        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = array_values(array_filter(
            $decoded['adventures'],
            static fn (mixed $row): bool => is_array($row),
        ));

        try {
            $summary = $applicator->apply($rawRows);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $reportPath = storage_path('logs/wiki-harvest-unmapped.txt');
        $reportDir = dirname($reportPath);
        if (! is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }
        file_put_contents(
            $reportPath,
            $summary['unmapped'] === []
                ? "Keine ungemappten Orte.\n"
                : implode("\n", $summary['unmapped'])."\n"
        );

        $this->info(sprintf(
            'Merge abgeschlossen: %d hinzugefügt/aktualisiert, %d übersprungen (kuratiert). Ungemappte Orte: %d (siehe %s)',
            $summary['added'],
            $summary['skipped'],
            count($summary['unmapped']),
            $reportPath,
        ));

        return self::SUCCESS;
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
