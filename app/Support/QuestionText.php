<?php

namespace App\Support;

use App\Enums\Language;

/**
 * Текст вопроса на выбранном языке.
 *
 * Если перевода нет, отдаётся русский оригинал и флаг translated = false —
 * так переключатель языка не оставляет пустых экранов на неполном переводе.
 */
readonly class QuestionText
{
    public function __construct(
        public Language $language,
        public string $prompt,
        public string $answer,
        public ?string $explanation,
        public ?array $options,
        public ?array $followUps,
        public bool $translated,
    ) {}

    /** Язык, на котором реально показан текст. */
    public function shownIn(): Language
    {
        return $this->translated ? $this->language : Language::Ru;
    }

    public function needsTranslation(): bool
    {
        return $this->language->isEnglish() && ! $this->translated;
    }
}
