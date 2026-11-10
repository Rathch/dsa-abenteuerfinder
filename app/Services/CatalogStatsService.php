<?php

namespace App\Services;

use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\RuleVersion;
use App\Models\Adventure;
use App\Models\GenreTag;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kennzahlen der Abenteuer, die der Finder vorschlagen kann.
 */
class CatalogStatsService
{
    /**
     * @return array{total: int, groups: list<array{title: string, items: list<array{label: string, count: int, share: float, path: string}>}>}
     */
    public function overview(): array
    {
        $base = Adventure::query()
            ->where('missing_from_wiki', false);

        return [
            'total' => (clone $base)->count(),
            'groups' => [
                $this->enumGroup('Regeln', $this->counts($base, 'rule_version'), RuleVersion::cases(), fn (RuleVersion $case): string => $case->label()),
                $this->enumGroup('Erfahrung', $this->counts($base, 'experience_level'), ExperienceLevel::cases(), fn (ExperienceLevel $case): string => $case->label()),
                $this->enumGroup('Dauer', $this->counts($base, 'duration_category'), DurationCategory::cases(), fn (DurationCategory $case): string => match ($case) {
                    DurationCategory::Kurz => 'Kurz',
                    DurationCategory::Mittel => 'Mittel',
                    DurationCategory::Lang => 'Lang',
                    DurationCategory::Kampagne => 'Kampagne',
                }),
                $this->genres(),
            ],
        ];
    }

    /**
     * @param  Builder<Adventure>  $base
     * @return array<string, int>
     */
    private function counts(Builder $base, string $column): array
    {
        /** @var array<string, int|string> $counts */
        $counts = (clone $base)
            ->toBase()
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->all();

        $normalized = [];
        foreach ($counts as $key => $count) {
            $normalized[(string) $key] = (int) $count;
        }

        return $normalized;
    }

    /**
     * @template T of RuleVersion|ExperienceLevel|DurationCategory
     *
     * @param  array<string, int>  $counts
     * @param  list<T>  $cases
     * @param  callable(T): string  $label
     * @return array{title: string, items: list<array{label: string, count: int, share: float, path: string}>}
     */
    private function enumGroup(string $title, array $counts, array $cases, callable $label): array
    {
        $items = [];
        foreach ($cases as $case) {
            $count = $counts[$case->value] ?? 0;
            if ($count === 0) {
                continue;
            }

            $items[] = [
                'label' => $label($case),
                'count' => $count,
            ];
        }

        return [
            'title' => $title,
            'items' => $this->withShares($items),
        ];
    }

    /**
     * @return array{title: string, items: list<array{label: string, count: int, share: float, path: string}>}
     */
    private function genres(): array
    {
        $tags = GenreTag::query()
            ->withCount(['adventures' => function (Builder $query): void {
                $query->where('missing_from_wiki', false);
            }])
            ->orderByDesc('adventures_count')
            ->limit(8)
            ->get();

        $items = [];
        foreach ($tags as $tag) {
            $count = (int) $tag->getAttribute('adventures_count');
            if ($count < 1) {
                continue;
            }

            $items[] = [
                'label' => $tag->name,
                'count' => $count,
            ];
        }

        return [
            'title' => 'Genres',
            'items' => $this->withShares($items),
        ];
    }

    /**
     * @param  list<array{label: string, count: int}>  $items
     * @return list<array{label: string, count: int, share: float, path: string}>
     */
    private function withShares(array $items): array
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += $item['count'];
        }

        $scaled = [];
        $offset = 0.0;
        $assigned = 0.0;
        $last = count($items) - 1;

        foreach ($items as $index => $item) {
            $share = $sum === 0 ? 0.0 : ($index === $last
                ? round(100 - $assigned, 2)
                : round($item['count'] / $sum * 100, 2));

            $scaled[] = [
                'label' => $item['label'],
                'count' => $item['count'],
                'share' => $share,
                'path' => $this->piePath($offset, $share),
            ];

            $offset += $share;
            $assigned += $share;
        }

        return $scaled;
    }

    /**
     * Kreisausschnitt. 0 Prozent liegt oben, der Bogen läuft im Uhrzeigersinn.
     */
    private function piePath(float $startPercent, float $share): string
    {
        $radius = 15.9;
        $center = 18.0;

        if ($share >= 99.99) {
            return sprintf(
                'M %.3F %.3F m -%.3F 0 a %.3F %.3F 0 1 1 %.3F 0 a %.3F %.3F 0 1 1 -%.3F 0',
                $center,
                $center,
                $radius,
                $radius,
                $radius,
                $radius * 2,
                $radius,
                $radius,
                $radius * 2,
            );
        }

        $start = deg2rad(-90) + ($startPercent / 100) * 2 * M_PI;
        $end = $start + ($share / 100) * 2 * M_PI;
        $large = $share > 50 ? 1 : 0;

        return sprintf(
            'M %.3F %.3F L %.3F %.3F A %.3F %.3F 0 %d 1 %.3F %.3F Z',
            $center,
            $center,
            $center + $radius * cos($start),
            $center + $radius * sin($start),
            $radius,
            $radius,
            $large,
            $center + $radius * cos($end),
            $center + $radius * sin($end),
        );
    }
}
