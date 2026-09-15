<?php

namespace App\Enums;

enum QuestionType: string
{
    case Theory = 'theory';
    case Quiz = 'quiz';
    case Coding = 'coding';
    case Behavioral = 'behavioral';
    case SystemDesign = 'system_design';
    case Cloze = 'cloze';
    case Shadowing = 'shadowing';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Теория',
            self::Quiz => 'Тест',
            self::Coding => 'Живое кодирование',
            self::Behavioral => 'HR / поведенческий',
            self::SystemDesign => 'Дизайн системы',
            self::Cloze => 'Фраза с пропуском',
            self::Shadowing => 'Проговаривание вслух',
        };
    }

    /** Вопросы, которые проверяются автоматически. */
    public function isAutoGraded(): bool
    {
        return $this === self::Quiz || $this === self::Cloze;
    }

    /** Упражнения на язык, а не на технические знания. */
    public function isLanguageDrill(): bool
    {
        return $this === self::Cloze || $this === self::Shadowing;
    }

    /**
     * Приём обучения, на котором построен этот формат — показывается кандидату,
     * чтобы он понимал, зачем делает упражнение.
     */
    public function technique(): string
    {
        return match ($this) {
            self::Theory, self::SystemDesign => 'Активное вспоминание: сначала ответ, потом эталон.',
            self::Quiz => 'Тестирующий эффект: проверка знания быстрее его перечитывания.',
            self::Coding => 'Осознанная практика: решение вслух с разбором по чек-листу.',
            self::Behavioral => 'Заготовленные речевые блоки: ответ собирается из отработанных фраз.',
            self::Cloze => 'Эффект генерации: недостающее слово нужно достать из памяти, а не узнать.',
            self::Shadowing => 'Шэдоуинг: проговаривание образца вслух ставит темп, ритм и произношение.',
        };
    }
}
