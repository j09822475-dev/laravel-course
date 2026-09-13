<?php

namespace App\Enums;

enum QuestionType: string
{
    case Theory = 'theory';
    case Quiz = 'quiz';
    case Coding = 'coding';
    case Behavioral = 'behavioral';
    case SystemDesign = 'system_design';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Теория',
            self::Quiz => 'Тест',
            self::Coding => 'Живое кодирование',
            self::Behavioral => 'HR / поведенческий',
            self::SystemDesign => 'Дизайн системы',
        };
    }

    /** Вопросы, которые проверяются автоматически. */
    public function isAutoGraded(): bool
    {
        return $this === self::Quiz;
    }
}
