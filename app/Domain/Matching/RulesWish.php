<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Regelwunsch aus dem Wizard.
 */
enum RulesWish: string
{
    case Dsa5 = 'dsa5';
    case Dsa4 = 'dsa4';
    case Any = 'any';

    public function accepts(RuleVersion $version): bool
    {
        return match ($this) {
            self::Any => true,
            self::Dsa5 => $version === RuleVersion::Dsa5 || $version === RuleVersion::EditionUnabhaengig,
            self::Dsa4 => $version === RuleVersion::Dsa4 || $version === RuleVersion::EditionUnabhaengig,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Dsa5 => 'DSA 5',
            self::Dsa4 => 'DSA 4',
            self::Any => 'beliebigen Regeln',
        };
    }
}
