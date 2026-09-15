<?php

namespace App\Enums;

enum Language: string
{
    case Ru = 'ru';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Ru => 'Русский',
            self::En => 'English',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Ru => 'RU',
            self::En => 'EN',
        };
    }

    /** Подсказка кандидату о том, как отвечать в этом языке. */
    public function speakingHint(): string
    {
        return match ($this) {
            self::Ru => 'Сначала ответьте вслух, как на собеседовании. Тезисы можно записать сюда — они попадут в отчёт.',
            self::En => 'Answer out loud in English first — speaking is the skill being trained. Jot down the key phrases you used.',
        };
    }

    public function isEnglish(): bool
    {
        return $this === self::En;
    }
}
