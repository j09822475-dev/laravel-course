<?php

namespace App\Enums;

enum SessionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'В процессе',
            self::Completed => 'Завершена',
            self::Abandoned => 'Прервана',
        };
    }
}
