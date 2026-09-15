<?php

namespace App\Enums;

enum SessionMode: string
{
    case Screening = 'screening';
    case TechInterview = 'tech_interview';
    case Quiz = 'quiz';
    case Drill = 'drill';
    case EnglishInterview = 'english_interview';

    public function label(): string
    {
        return match ($this) {
            self::Screening => 'Скрининг (30 минут)',
            self::TechInterview => 'Техническое интервью (60 минут)',
            self::Quiz => 'Блиц-тест',
            self::Drill => 'Карточки и повторение',
            self::EnglishInterview => 'Интервью на английском',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Screening => 'Короткий разговор с рекрутером и техлидом: знакомство, база по PHP и Laravel, пара практических вопросов.',
            self::TechInterview => 'Полноценная техническая секция: углублённая теория, SQL, фронтенд, архитектура и задача на живое кодирование.',
            self::Quiz => 'Быстрые вопросы с вариантами ответов и автоматической проверкой.',
            self::Drill => 'Проработка слабых тем карточками с интервальным повторением.',
            self::EnglishInterview => 'Скрининг на английском: small talk, рассказ о себе, техническое объяснение и отработка фраз.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Screening => '📞',
            self::TechInterview => '🧑‍💻',
            self::Quiz => '⚡',
            self::Drill => '🃏',
            self::EnglishInterview => '🇬🇧',
        };
    }

    /** Язык, на котором проходит сессия этого режима. */
    public function language(): Language
    {
        return $this === self::EnglishInterview ? Language::En : Language::Ru;
    }
}
