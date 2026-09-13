<?php

namespace App\Enums;

/** Самооценка ответа кандидата — она же оценка качества для алгоритма повторений. */
enum SelfRating: int
{
    case Failed = 0;
    case Shaky = 1;
    case Good = 2;
    case Confident = 3;

    public function label(): string
    {
        return match ($this) {
            self::Failed => 'Не знал',
            self::Shaky => 'Плавал',
            self::Good => 'Ответил',
            self::Confident => 'Уверенно',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Failed => 'rose',
            self::Shaky => 'amber',
            self::Good => 'sky',
            self::Confident => 'emerald',
        };
    }

    /** Доля от максимального балла за вопрос. */
    public function share(): float
    {
        return $this->value / 3;
    }
}
