<?php

namespace App\Enums;

enum Difficulty: string
{
    case Junior = 'junior';
    case Middle = 'middle';
    case Senior = 'senior';

    public function label(): string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Middle => 'Middle',
            self::Senior => 'Senior',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Junior => 1,
            self::Middle => 2,
            self::Senior => 3,
        };
    }

    /** Уровни, уместные для кандидата, который целится в этот грейд. */
    public function scope(): array
    {
        return match ($this) {
            self::Junior => [self::Junior, self::Middle],
            self::Middle => [self::Junior, self::Middle, self::Senior],
            self::Senior => [self::Middle, self::Senior],
        };
    }
}
