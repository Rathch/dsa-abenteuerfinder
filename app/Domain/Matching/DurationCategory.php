<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum DurationCategory: string
{
    case Kurz = 'kurz';
    case Mittel = 'mittel';
    case Lang = 'lang';
    case Kampagne = 'kampagne';

    /**
     * Grobe Schätzung der Dauerkategorie anhand der Seitenzahl (Proxy).
     */
    public static function fromPageCount(int $pages): self
    {
        return match (true) {
            $pages <= 36 => self::Kurz,
            $pages <= 72 => self::Mittel,
            $pages <= 160 => self::Lang,
            default => self::Kampagne,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Kurz => 'Kurzabenteuer für einen Abend',
            self::Mittel => 'Abenteuer für einen langen Abend bis zwei Sitzungen',
            self::Lang => 'Abenteuer über mehrere Sitzungen',
            self::Kampagne => 'Kampagne',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
