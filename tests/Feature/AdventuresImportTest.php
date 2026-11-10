<?php

use App\Domain\Matching\LocationKind;
use App\Domain\Matching\LocationType;
use App\Models\Adventure;
use App\Models\GenreTag;
use App\Models\Location;
use App\Models\Series;
use App\Services\AdventureImportService;
use App\Services\InvalidAdventureDataException;
use Illuminate\Support\Facades\File;

/**
 * Kopiert database/data in ein temporäres Verzeichnis und wendet eine Änderung an den Abenteuern an.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $mutate
 */
function copiedDataDir(?callable $mutate = null, ?string $adventuresRaw = null): string
{
    $dir = sys_get_temp_dir().'/adventure-data-'.uniqid();
    File::copyDirectory(database_path('data'), $dir);

    if ($adventuresRaw !== null) {
        File::put($dir.'/adventures.json', $adventuresRaw);
    } elseif ($mutate !== null) {
        $data = json_decode(File::get($dir.'/adventures.json'), true);
        File::put($dir.'/adventures.json', json_encode($mutate($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    return $dir;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/adventure-data-*') ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

describe('shipped fixtures', function (): void {
    it('validates against the JSON schema and references', function (): void {
        expect((new AdventureImportService)->validate())->toBe([]);
    });

    it('imports series, tags, locations and adventures', function (): void {
        $summary = (new AdventureImportService)->import();

        expect($summary['adventures'])->toBeGreaterThanOrEqual(20)
            ->and(Adventure::count())->toBe($summary['adventures'])
            ->and(Series::count())->toBe($summary['series'])
            ->and(GenreTag::count())->toBeGreaterThanOrEqual(8)
            ->and(Location::count())->toBe($summary['locations'])
            ->and(Location::where('type', LocationKind::Continent)->pluck('slug')->all())->toContain('aventurien', 'myranor')
            ->and(Location::where('type', LocationKind::Region)->pluck('slug')->all())
            ->toContain('nordmarken', 'mittelaventurien', 'tulamidenlande', 'suedaventurien', 'suedwest', 'seefahrt')
            ->and(Location::where('type', LocationKind::Place)->pluck('slug')->all())
            ->toContain('havena', 'gareth', 'al-anfa', 'fasar');
    });

    it('builds the location hierarchy', function (): void {
        (new AdventureImportService)->import();

        $havena = Location::where('slug', 'havena')->firstOrFail();

        expect($havena->parent?->slug)->toBe('nordmarken')
            ->and($havena->parent?->parent?->slug)->toBe('aventurien')
            ->and(Location::where('slug', 'myranor')->firstOrFail()->parent_id)->toBeNull();
    });

    it('flags at least two adventures as incomplete with a source note', function (): void {
        $summary = (new AdventureImportService)->import();

        $incomplete = Adventure::where('incomplete', true)->get();

        expect($summary['incomplete'])->toBeGreaterThanOrEqual(2)
            ->and($incomplete)->toHaveCount($summary['incomplete']);

        foreach ($incomplete as $adventure) {
            expect($adventure->source_note)->not->toBeEmpty();
        }
    });

    it('contains point, corridor and traveling adventures with matching relations', function (): void {
        (new AdventureImportService)->import();

        expect(Adventure::where('location_type', LocationType::Traveling)->count())->toBeGreaterThan(0)
            ->and(Adventure::where('location_type', LocationType::Corridor)->count())->toBeGreaterThan(0)
            ->and(Adventure::where('location_type', LocationType::Point)->count())->toBeGreaterThan(0);

        foreach (Adventure::where('location_type', LocationType::Corridor)->get() as $corridor) {
            expect($corridor->regions()->count())->toBeGreaterThanOrEqual(2);
        }

        foreach (Adventure::where('location_type', LocationType::Point)->get() as $point) {
            expect($point->primary_location_id)->not->toBeNull()
                ->and($point->primaryLocation?->parent_id)->toBe($point->primary_region_id);
        }
    });

    it('is diverse across series, experience levels and durations', function (): void {
        (new AdventureImportService)->import();

        expect(Adventure::distinct()->pluck('series_id')->count())->toBeGreaterThanOrEqual(6)
            ->and(Adventure::distinct()->pluck('experience_level')->count())->toBe(4)
            ->and(Adventure::distinct()->pluck('duration_category')->count())->toBe(4)
            ->and(Adventure::distinct()->pluck('rule_version')->count())->toBe(6);
    });

    it('stores spoiler-free descriptions and urls', function (): void {
        (new AdventureImportService)->import();

        foreach (Adventure::all() as $adventure) {
            expect($adventure->short_description)->not->toBeEmpty()
                ->and($adventure->wiki_url)->toStartWith('https://de.wiki-aventurica.de/wiki/');
        }
    });

    it('attaches genres to every adventure', function (): void {
        (new AdventureImportService)->import();

        foreach (Adventure::withCount('genreTags')->get() as $adventure) {
            expect($adventure->genre_tags_count)->toBeGreaterThan(0);
        }
    });

    it('is idempotent when imported twice', function (): void {
        $importer = new AdventureImportService;
        $importer->import();
        $counts = [Adventure::count(), Series::count(), Location::count(), GenreTag::count(), DB::table('adventure_genre_tag')->count()];

        $importer->import();

        expect([Adventure::count(), Series::count(), Location::count(), GenreTag::count(), DB::table('adventure_genre_tag')->count()])->toBe($counts);
    });

    it('updates existing rows on re-import', function (): void {
        $importer = new AdventureImportService;
        $importer->import();
        Adventure::where('slug', 'salz-und-silber')->update(['title' => 'Manipuliert']);

        $importer->import();

        expect(Adventure::where('slug', 'salz-und-silber')->firstOrFail()->title)->toBe('Salz und Silber');
    });
});

describe('schema validation', function (): void {
    it('rejects an unknown experience level', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['experience_level'] = 'legende';

            return $data;
        });

        $errors = (new AdventureImportService($dir))->validate();

        expect($errors)->not->toBeEmpty()
            ->and(implode(' ', $errors))->toContain('/adventures/0/experience_level');
    });

    it('rejects missing required fields and unknown properties', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            unset($data['adventures'][1]['short_description']);
            $data['adventures'][2]['geheim'] = true;

            return $data;
        });

        $errors = implode(' ', (new AdventureImportService($dir))->validate());

        expect($errors)->toContain('short_description')->toContain('geheim');
    });

    it('requires a primary location for point adventures', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['primary_location'] = null;

            return $data;
        });

        expect((new AdventureImportService($dir))->validate())->not->toBeEmpty();
    });

    it('requires at least two regions for corridors', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            foreach ($data['adventures'] as &$adventure) {
                if ($adventure['location_type'] === 'corridor') {
                    $adventure['regions'] = ['nordmarken'];
                }
            }

            return $data;
        });

        expect((new AdventureImportService($dir))->validate())->not->toBeEmpty();
    });

    it('accepts a dash when the short description is missing', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['short_description'] = '-';

            return $data;
        });

        expect((new AdventureImportService($dir))->validate())->toBe([]);
    });

    it('requires a source note for incomplete adventures', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            foreach ($data['adventures'] as &$adventure) {
                if ($adventure['incomplete']) {
                    $adventure['source_note'] = null;
                }
            }

            return $data;
        });

        expect((new AdventureImportService($dir))->validate())->not->toBeEmpty();
    });

    it('rejects non-https wiki urls and malformed slugs', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['wiki_url'] = 'ftp://example.org/x';
            $data['adventures'][1]['slug'] = 'Ungültiger Slug';

            return $data;
        });

        $errors = implode(' ', (new AdventureImportService($dir))->validate());

        expect($errors)->toContain('wiki_url')->toContain('slug');
    });

    it('reports invalid JSON and missing files', function (): void {
        $broken = copiedDataDir(adventuresRaw: '{ nicht json');
        $missing = copiedDataDir();
        File::delete($missing.'/tags.json');

        expect((new AdventureImportService($broken))->validate())->toHaveCount(1)
            ->and(implode(' ', (new AdventureImportService($missing))->validate()))->toContain('tags.json');
    });

    it('validates a decoded document directly', function (): void {
        $service = new AdventureImportService;

        expect($service->validateAdventuresSchema((object) ['adventures' => []]))->not->toBeEmpty()
            ->and($service->validateAdventuresSchema((object) []))->not->toBeEmpty();
    });
});

describe('reference validation', function (): void {
    it('detects unknown series, genres, locations and regions', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['series'] = 'gibt-es-nicht';
            $data['adventures'][0]['genres'] = ['unbekannt'];
            $data['adventures'][1]['primary_location'] = 'nirgendwo';
            $data['adventures'][2]['regions'] = ['nordmarken', 'atlantis'];

            return $data;
        });

        $errors = implode(' | ', (new AdventureImportService($dir))->validate());

        expect($errors)->toContain('unbekannte Serie')
            ->toContain('unbekanntes Genre')
            ->toContain('kein bekannter Ort')
            ->toContain('unbekannte Region');
    });

    it('detects a place outside its primary region', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['primary_region'] = 'suedwest';

            return $data;
        });

        expect(implode(' ', (new AdventureImportService($dir))->validate()))->toContain('liegt nicht in primary_region');
    });

    it('detects duplicate slugs and inverted group sizes', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][1]['slug'] = $data['adventures'][0]['slug'];
            $data['adventures'][2]['group_size_min'] = 6;
            $data['adventures'][2]['group_size_max'] = 3;

            return $data;
        });

        $errors = implode(' | ', (new AdventureImportService($dir))->validate());

        expect($errors)->toContain('doppelter Slug')->toContain('group_size_min');
    });

    it('validates series, tags and location files', function (): void {
        $dir = copiedDataDir();
        File::put($dir.'/series.json', json_encode(['series' => [['slug' => 'ok', 'name' => 'Ok'], ['slug' => 'ok', 'name' => 'Doppelt'], ['slug' => 'Kaputt!', 'name' => '']]]));
        File::put($dir.'/tags.json', json_encode(['tags' => []]));
        File::put($dir.'/locations.json', json_encode(['locations' => [
            ['slug' => 'welt', 'name' => 'Welt', 'type' => 'region', 'children' => [
                ['slug' => 'ort', 'name' => 'Ort', 'type' => 'place'],
                ['slug' => 'ort', 'name' => 'Ort 2', 'type' => 'place'],
                ['slug' => 'komisch', 'name' => 'Komisch', 'type' => 'galaxie'],
                'kein-objekt',
            ]],
        ]]));

        $errors = implode(' | ', (new AdventureImportService($dir))->validate());

        expect($errors)->toContain('doppelter Slug "ok"')
            ->toContain('series.json @ /series/2')
            ->toContain('tags.json')
            ->toContain('doppelter Slug "ort"')
            ->toContain('ungültigem slug/name/type')
            ->toContain('kein Objekt')
            ->toContain('darf nicht unter');
    });
});

describe('import errors', function (): void {
    it('refuses to import invalid data and writes nothing', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['series'] = 'gibt-es-nicht';

            return $data;
        });

        expect(fn () => (new AdventureImportService($dir))->import())->toThrow(InvalidAdventureDataException::class)
            ->and(Adventure::count())->toBe(0)
            ->and(Series::count())->toBe(0);
    });

    it('exposes the collected errors on the exception', function (): void {
        $dir = copiedDataDir(adventuresRaw: '[]');

        try {
            (new AdventureImportService($dir))->import();
            $this->fail('Exception expected');
        } catch (InvalidAdventureDataException $e) {
            expect($e->errors)->not->toBeEmpty()->and($e->getMessage())->toContain('ungültig');
        }
    });
});

describe('artisan commands', function (): void {
    it('imports via adventures:import', function (): void {
        $this->artisan('adventures:import')
            ->expectsOutputToContain('Import abgeschlossen')
            ->assertSuccessful();

        expect(Adventure::count())->toBeGreaterThanOrEqual(20);
    });

    it('validates via adventures:validate without touching the database', function (): void {
        $this->artisan('adventures:validate')
            ->expectsOutputToContain('gültig')
            ->assertSuccessful();

        expect(Adventure::count())->toBe(0);
    });

    it('exits with failure when validation fails', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['complexity'] = 'extrem';

            return $data;
        });
        app()->instance(AdventureImportService::class, new AdventureImportService($dir));

        $this->artisan('adventures:validate')
            ->expectsOutputToContain('Fehler gefunden')
            ->assertExitCode(1);
    });

    it('exits with failure when import data is invalid', function (): void {
        $dir = copiedDataDir(function (array $data): array {
            $data['adventures'][0]['complexity'] = 'extrem';

            return $data;
        });
        app()->instance(AdventureImportService::class, new AdventureImportService($dir));

        $this->artisan('adventures:import')
            ->expectsOutputToContain('Import abgebrochen')
            ->assertExitCode(1);

        expect(Adventure::count())->toBe(0);
    });
});
