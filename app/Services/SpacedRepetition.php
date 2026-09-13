<?php

namespace App\Services;

use App\Enums\SelfRating;
use App\Models\Question;
use App\Models\ReviewCard;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Планировщик повторений на основе упрощённого SM-2.
 * Оценка 0..3 (SelfRating) превращается в интервал до следующего показа вопроса.
 */
class SpacedRepetition
{
    public function record(Trainee $trainee, Question $question, SelfRating $rating, ?Carbon $now = null): ReviewCard
    {
        $now ??= Carbon::now();

        $card = ReviewCard::firstOrNew([
            'trainee_id' => $trainee->id,
            'question_id' => $question->id,
        ]);

        $ease = $card->ease_factor ?: 2.5;
        $repetitions = $card->repetitions ?? 0;
        $lapses = $card->lapses ?? 0;

        if ($rating === SelfRating::Failed || $rating === SelfRating::Shaky) {
            // Ответ провален — начинаем цикл заново и снижаем лёгкость карточки.
            $repetitions = 0;
            $lapses++;
            $interval = $rating === SelfRating::Failed ? 0 : 1;
            $ease = max(1.3, $ease - 0.2);
        } else {
            $repetitions++;
            $ease = min(2.8, $ease + ($rating === SelfRating::Confident ? 0.1 : 0.0));
            $interval = match ($repetitions) {
                1 => $rating === SelfRating::Confident ? 3 : 1,
                2 => 6,
                default => (int) ceil(($card->interval_days ?: 6) * $ease),
            };
            $interval = min($interval, 180);
        }

        $card->fill([
            'ease_factor' => round($ease, 2),
            'interval_days' => $interval,
            'repetitions' => $repetitions,
            'lapses' => $lapses,
            'last_rating' => $rating->value,
            'due_on' => $now->copy()->addDays($interval)->toDateString(),
            'last_reviewed_at' => $now,
        ])->save();

        return $card;
    }

    /** Карточки, которые пора повторить. */
    public function due(Trainee $trainee, int $limit = 20, ?Carbon $on = null): Collection
    {
        return ReviewCard::query()
            ->where('trainee_id', $trainee->id)
            ->due($on ?? Carbon::now())
            ->with('question.topic')
            ->orderBy('due_on')
            ->limit($limit)
            ->get();
    }

    public function dueCount(Trainee $trainee, ?Carbon $on = null): int
    {
        return ReviewCard::query()
            ->where('trainee_id', $trainee->id)
            ->due($on ?? Carbon::now())
            ->count();
    }
}
