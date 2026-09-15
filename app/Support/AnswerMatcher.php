<?php

namespace App\Support;

/**
 * Сверка свободного ответа с допустимыми вариантами для упражнений с пропуском.
 *
 * Кандидат вписывает слово руками, поэтому регистр, пробелы, точка в конце
 * и типографские апострофы не должны считаться ошибкой — важно само слово.
 */
class AnswerMatcher
{
    /** @param  list<string>  $accepted */
    public static function matches(?string $given, array $accepted): bool
    {
        if (blank($given)) {
            return false;
        }

        $normalized = static::normalize($given);

        foreach ($accepted as $variant) {
            if ($normalized === static::normalize($variant)) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        // Типографские апострофы и кавычки приводим к простым.
        $value = strtr($value, ['’' => "'", '‘' => "'", '«' => '', '»' => '', '“' => '', '”' => '']);

        // Убираем знаки препинания по краям и схлопываем пробелы.
        $value = preg_replace('/[\p{P}\p{S}]+$/u', '', $value) ?? $value;
        $value = preg_replace('/^[\p{P}\p{S}]+/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
