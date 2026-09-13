<?php

namespace App\Enums;

enum SessionMode: string
{
    case Screening = 'screening';
    case TechInterview = 'tech_interview';
    case Quiz = 'quiz';
    case Drill = 'drill';

    public function label(): string
    {
        return match ($this) {
            self::Screening => 'Скрининг (30 минут)',
            self::TechInterview => 'Техническое интервью (60 минут)',
            self::Quiz => 'Блиц-тест',
            self::Drill => 'Карточки и повторение',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Screening => 'Короткий разговор с рекрутером и техлидом: знакомство, база по PHP и Laravel, пара практических вопросов.',
            self::TechInterview => 'Полноценная техническая секция: углублённая теория, SQL, фронтенд, архитектура и задача на живое кодирование.',
            self::Quiz => 'Быстрые вопросы с вариантами ответов и автоматической проверкой.',
            self::Drill => 'Проработка слабых тем карточками с интервальным повторением.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Screening => '📞',
            self::TechInterview => '🧑‍💻',
            self::Quiz => '⚡',
            self::Drill => '🃏',
        };
    }
}
