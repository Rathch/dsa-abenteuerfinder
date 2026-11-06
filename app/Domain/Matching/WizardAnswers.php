<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Antworten des Wizards. Rohwerte bleiben Strings, damit sie direkt aus der UI stammen können;
 * die typisierten Zugriffe fallen bei unbekannten Werten auf neutrale Defaults zurück.
 */
final readonly class WizardAnswers
{
    /**
     * @param  string  $experienceLevel  einsteiger|fortgeschritten|erfahren|meister
     * @param  ?string  $groupSize  '2'|'3-5'|'6+'|null für egal
     * @param  string  $timeframe  evening_short|evening_long|multi|campaign
     * @param  ?string  $regionSlug  null = egal
     * @param  string  $exotic  no|extra_aventurian|unusual_cultures|any
     * @param  list<string>  $genres
     * @param  string  $rules  dsa5|dsa4|any
     * @param  string  $gmExperience  einsteiger|fortgeschritten|erfahren|meister
     */
    public function __construct(
        public string $experienceLevel,
        public ?string $groupSize,
        public string $timeframe,
        public ?string $regionSlug,
        public ?string $placeSlug,
        public string $exotic,
        public array $genres,
        public string $rules,
        public string $gmExperience,
    ) {}

    public function experience(): ExperienceLevel
    {
        return ExperienceLevel::tryFrom($this->experienceLevel) ?? ExperienceLevel::Einsteiger;
    }

    public function gmLevel(): ExperienceLevel
    {
        return ExperienceLevel::tryFrom($this->gmExperience) ?? ExperienceLevel::Einsteiger;
    }

    public function groupSizeWish(): ?GroupSizeWish
    {
        return $this->groupSize === null ? null : GroupSizeWish::tryFrom($this->groupSize);
    }

    public function durationWish(): DurationWish
    {
        return DurationWish::tryFrom($this->timeframe) ?? DurationWish::EveningLong;
    }

    public function exoticWish(): ExoticWish
    {
        return ExoticWish::tryFrom($this->exotic) ?? ExoticWish::Any;
    }

    public function rulesWish(): RulesWish
    {
        return RulesWish::tryFrom($this->rules) ?? RulesWish::Any;
    }

    public function hasRegionWish(): bool
    {
        return $this->regionSlug !== null || $this->placeSlug !== null;
    }
}
