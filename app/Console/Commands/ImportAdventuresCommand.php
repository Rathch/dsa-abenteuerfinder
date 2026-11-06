<?php

namespace App\Console\Commands;

use App\Services\AdventureImportService;
use App\Services\InvalidAdventureDataException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adventures:import', description: 'Importiert Serien, Orte, Genres und Abenteuer aus database/data/*.json')]
class ImportAdventuresCommand extends Command
{
    public function handle(AdventureImportService $importer): int
    {
        try {
            $summary = $importer->import();
        } catch (InvalidAdventureDataException $e) {
            $this->error('Import abgebrochen: Daten sind ungültig.');
            foreach ($e->errors as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import abgeschlossen: %d Serien, %d Orte, %d Genres, %d Abenteuer (davon %d unvollständig).',
            $summary['series'],
            $summary['locations'],
            $summary['tags'],
            $summary['adventures'],
            $summary['incomplete'],
        ));

        return self::SUCCESS;
    }
}
