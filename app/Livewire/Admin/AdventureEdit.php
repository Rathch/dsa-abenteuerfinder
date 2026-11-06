<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Livewire\Admin;

use App\Domain\Matching\Complexity;
use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\RuleVersion;
use App\Services\AdventureCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AdventureEdit extends Component
{
    public string $slug = '';

    public string $title = '';

    public string $series = '';

    public string $experience_level = '';

    public ?int $page_count = null;

    public string $duration_category = '';

    public string $rule_version = '';

    public bool $exotic = false;

    public string $complexity = '';

    public ?int $group_size_min = null;

    public ?int $group_size_max = null;

    public string $location_type = '';

    public string $primary_location = '';

    public string $primary_region = '';

    /** @var list<string> */
    public array $regions = [];

    /** @var list<string> */
    public array $genres = [];

    public string $short_description = '';

    public string $wiki_url = '';

    public bool $incomplete = false;

    public bool $missing_from_wiki = false;

    public string $source_note = '';

    public bool $hasCorrection = false;

    public bool $saved = false;

    /** @var list<array{slug: string, name: string}> */
    public array $seriesOptions = [];

    /** @var list<array{slug: string, name: string}> */
    public array $placeOptions = [];

    /** @var list<array{slug: string, name: string}> */
    public array $regionOptions = [];

    /** @var list<array{slug: string, name: string}> */
    public array $genreOptions = [];

    public function mount(string $slug, AdventureCorrectionService $corrections): void
    {
        $payload = $corrections->editPayload($slug);
        $adventure = $payload['adventure'];

        $this->slug = $adventure['slug'];
        $this->title = $adventure['title'];
        $this->series = $adventure['series'];
        $this->experience_level = $adventure['experience_level'];
        $this->page_count = $adventure['page_count'];
        $this->duration_category = $adventure['duration_category'];
        $this->rule_version = $adventure['rule_version'];
        $this->exotic = $adventure['exotic'];
        $this->complexity = $adventure['complexity'];
        $this->group_size_min = $adventure['group_size_min'];
        $this->group_size_max = $adventure['group_size_max'];
        $this->location_type = $adventure['location_type'];
        $this->primary_location = $adventure['primary_location'] ?? '';
        $this->primary_region = $adventure['primary_region'] ?? '';
        $this->regions = $adventure['regions'];
        $this->genres = $adventure['genres'];
        $this->short_description = $adventure['short_description'];
        $this->wiki_url = $adventure['wiki_url'];
        $this->incomplete = $adventure['incomplete'];
        $this->missing_from_wiki = $adventure['missing_from_wiki'];
        $this->source_note = $adventure['source_note'] ?? '';
        $this->hasCorrection = $payload['has_correction'];
        $this->seriesOptions = $payload['options']['series'];
        $this->placeOptions = $payload['options']['places'];
        $this->regionOptions = $payload['options']['regions'];
        $this->genreOptions = $payload['options']['genres'];
    }

    public function save(AdventureCorrectionService $corrections): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'series' => ['required', 'string', Rule::in(array_column($this->seriesOptions, 'slug'))],
            'experience_level' => ['required', Rule::enum(ExperienceLevel::class)],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'duration_category' => ['required', Rule::enum(DurationCategory::class)],
            'rule_version' => ['required', Rule::enum(RuleVersion::class)],
            'exotic' => ['boolean'],
            'complexity' => ['required', Rule::enum(Complexity::class)],
            'group_size_min' => ['nullable', 'integer', 'min:1', 'max:20'],
            'group_size_max' => ['nullable', 'integer', 'min:1', 'max:20'],
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'primary_location' => ['nullable', 'string', Rule::in(array_merge([''], array_column($this->placeOptions, 'slug')))],
            'primary_region' => ['nullable', 'string', Rule::in(array_merge([''], array_column($this->regionOptions, 'slug')))],
            'regions' => ['array'],
            'regions.*' => [Rule::in(array_column($this->regionOptions, 'slug'))],
            'genres' => ['array'],
            'genres.*' => [Rule::in(array_column($this->genreOptions, 'slug'))],
            'short_description' => ['required', 'string', 'max:5000'],
            'wiki_url' => ['required', 'url', 'max:2048'],
            'incomplete' => ['boolean'],
            'missing_from_wiki' => ['boolean'],
            'source_note' => ['nullable', 'string', 'max:500'],
        ]);

        $corrections->save($this->slug, [
            'title' => $this->title,
            'series' => $this->series,
            'experience_level' => $this->experience_level,
            'page_count' => $this->page_count,
            'duration_category' => $this->duration_category,
            'rule_version' => $this->rule_version,
            'exotic' => $this->exotic,
            'complexity' => $this->complexity,
            'group_size_min' => $this->group_size_min,
            'group_size_max' => $this->group_size_max,
            'location_type' => $this->location_type,
            'primary_location' => $this->primary_location,
            'primary_region' => $this->primary_region,
            'regions' => $this->regions,
            'genres' => $this->genres,
            'short_description' => $this->short_description,
            'wiki_url' => $this->wiki_url,
            'incomplete' => $this->incomplete,
            'missing_from_wiki' => $this->missing_from_wiki,
            'source_note' => $this->source_note,
        ]);

        $this->hasCorrection = $corrections->editPayload($this->slug)['has_correction'];
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.admin.adventure-edit', [
            'experienceLevels' => ExperienceLevel::cases(),
            'durationCategories' => DurationCategory::cases(),
            'ruleVersions' => RuleVersion::cases(),
            'complexities' => Complexity::cases(),
            'locationTypes' => LocationType::cases(),
        ])->layout('layouts.admin', [
            'title' => 'Abenteuer bearbeiten',
        ]);
    }
}
