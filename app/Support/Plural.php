<?php

namespace App\Support;

/**
 * Склонение существительных после числительных.
 *
 * Встроенный trans_choice для русской локали выбирает форму по упрощённому правилу
 * и даёт «9 вопроса», поэтому счётные формы считаем сами.
 */
class Plural
{
    /**
     * @param  string  $one  форма для 1 (вопрос)
     * @param  string  $few  форма для 2–4 (вопроса)
     * @param  string  $many  форма для 5–20 и остальных (вопросов)
     */
    public static function ru(int $count, string $one, string $few, string $many): string
    {
        $absolute = abs($count);
        $mod100 = $absolute % 100;
        $mod10 = $absolute % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        if ($mod10 === 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }

    /** Число вместе со склонённым словом: «9 вопросов». */
    public static function count(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.static::ru($count, $one, $few, $many);
    }
}
