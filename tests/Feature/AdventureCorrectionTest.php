<?php

use App\Domain\Matching\LocationType;
use App\Livewire\Admin\AdventureEdit;
use App\Livewire\Admin\AdventureIndex;
use App\Models\Adventure;
use App\Models\AdventureCorrection;
use App\Services\AdventureCorrectionService;
use App\Services\AdventureImportService;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function (): void {
    (new AdventureImportService)->import();
});

function adminLogin(): void
{
    test()->post('/verwaltung/login', ['password' => 'secret'])->assertRedirect();
}

it('saves a wiki url correction and keeps it after reimport', function (): void {
    $adventure = Adventure::query()->firstOrFail();
    $slug = $adventure->slug;
    $correctedUrl = 'https://de.wiki-aventurica.de/wiki/Korrigierter_Artikel';

    adminLogin();

    Livewire::test(AdventureEdit::class, ['slug' => $slug])
        ->set('wiki_url', $correctedUrl)
        ->set('title', $adventure->title)
        ->call('save')
        ->assertHasNoErrors();

    expect(Adventure::query()->where('slug', $slug)->value('wiki_url'))->toBe($correctedUrl)
        ->and(AdventureCorrection::query()->where('adventure_slug', $slug)->exists())->toBeTrue();

    $dir = sys_get_temp_dir().'/adventure-data-'.uniqid();
    File::copyDirectory(database_path('data'), $dir);
    $data = json_decode(File::get($dir.'/adventures.json'), true);
    foreach ($data['adventures'] as &$row) {
        if (($row['slug'] ?? null) === $slug) {
            $row['title'] = 'Import-Titel-Neu';
            $row['wiki_url'] = 'https://de.wiki-aventurica.de/wiki/Import_Original';
        }
    }
    unset($row);
    File::put($dir.'/adventures.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    app()->instance(AdventureImportService::class, new AdventureImportService($dir));
    (new AdventureImportService($dir))->import();

    $fresh = Adventure::query()->where('slug', $slug)->firstOrFail();

    expect($fresh->wiki_url)->toBe($correctedUrl)
        ->and($fresh->title)->toBe('Import-Titel-Neu');

    File::deleteDirectory($dir);
});

it('stores only fields that differ from the json source', function (): void {
    $adventure = Adventure::query()->firstOrFail();
    $service = app(AdventureCorrectionService::class);

    $payload = $service->editPayload($adventure->slug)['adventure'];
    $payload['wiki_url'] = 'https://de.wiki-aventurica.de/wiki/Korrigierter_Artikel';

    $service->save($adventure->slug, $payload);

    $correction = AdventureCorrection::query()->where('adventure_slug', $adventure->slug)->firstOrFail();

    expect($correction->fields)->toHaveKey('wiki_url')
        ->and($correction->fields)->not->toHaveKey('title');
});

it('removes the correction row when values match the json source again', function (): void {
    $adventure = Adventure::query()->firstOrFail();
    $service = app(AdventureCorrectionService::class);
    $payload = $service->editPayload($adventure->slug)['adventure'];

    $payload['wiki_url'] = 'https://de.wiki-aventurica.de/wiki/Zwischenstand';
    $service->save($adventure->slug, $payload);
    expect(AdventureCorrection::query()->where('adventure_slug', $adventure->slug)->exists())->toBeTrue();

    $source = (new AdventureImportService)->sourceAdventuresBySlug()[$adventure->slug];
    $payload['wiki_url'] = $source['wiki_url'];
    $service->save($adventure->slug, $payload);

    expect(AdventureCorrection::query()->where('adventure_slug', $adventure->slug)->exists())->toBeFalse();
});

it('lists a dash and an unmapped place under incomplete', function (): void {
    adminLogin();

    $template = Adventure::query()
        ->where('incomplete', false)
        ->whereNotNull('primary_location_id')
        ->firstOrFail();

    $dash = $template->replicate();
    $dash->slug = 'platzhalter-strich';
    $dash->title = 'Platzhalterstrich Abenteuer';
    $dash->short_description = '-';
    $dash->incomplete = true;
    $dash->missing_from_wiki = false;
    $dash->source_note = 'Kurzbeschreibung fehlt';
    $dash->save();

    $unmapped = $template->replicate();
    $unmapped->slug = 'ohne-katalogort';
    $unmapped->title = 'Ohne Katalogort Abenteuer';
    $unmapped->short_description = 'Eine vorhandene Kurzbeschreibung mit mehr als zwanzig Zeichen.';
    $unmapped->primary_location_id = null;
    $unmapped->location_type = LocationType::Traveling;
    $unmapped->incomplete = true;
    $unmapped->missing_from_wiki = false;
    $unmapped->source_note = 'Schauplatz nicht zugeordnet';
    $unmapped->save();

    Livewire::test(AdventureIndex::class)
        ->set('linkFilter', 'incomplete')
        ->set('search', 'Platzhalterstrich Abenteuer')
        ->assertSee('Platzhalterstrich Abenteuer')
        ->set('search', 'Ohne Katalogort Abenteuer')
        ->assertSee('Ohne Katalogort Abenteuer')
        ->set('search', $template->title)
        ->assertDontSee($template->title);
});

it('filters adventures with a correction', function (): void {
    adminLogin();

    Livewire::test(AdventureIndex::class)
        ->set('linkFilter', 'corrected')
        ->assertOk()
        ->assertSee('Mit Korrektur');
});
