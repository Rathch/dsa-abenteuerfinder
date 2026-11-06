<?php

namespace App\Console\Commands;

use App\Services\AdventureImportService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adventures:validate', description: 'Validiert database/data/*.json gegen das Schema (ohne Datenbankzugriff)')]
class ValidateAdventuresCommand extends Command
{
    public function handle(AdventureImportService $importer): int
    {
        $errors = $importer->validate();

        if ($errors === []) {
            $this->info('Alle Datendateien sind gültig.');

            return self::SUCCESS;
        }

        $this->error(count($errors).' Fehler gefunden:');
        foreach ($errors as $error) {
            $this->line(' - '.$error);
        }

        return self::FAILURE;
    }
}
