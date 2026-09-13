<?php

namespace App\Services;

use App\Enums\SelfRating;
use App\Enums\SessionStatus;
use App\Models\InterviewSession;
use App\Models\SessionItem;
use App\Models\Trainee;

/**
 * Принимает ответ кандидата, оценивает его и закрывает сессию, когда вопросы кончились.
 */
class SessionGrader
{
    public function __construct(protected SpacedRepetition $repetition) {}

    /**
     * @param  array{answer?: ?string, option?: ?int, rating?: ?int, seconds?: int}  $payload
     */
    public function answer(SessionItem $item, array $payload, Trainee $trainee): SessionItem
    {
        $question = $item->question;
        $rating = null;
        $isCorrect = null;

        if ($question->isAutoGraded()) {
            $selected = $payload['option'] ?? null;
            $isCorrect = $selected !== null && (int) $selected === (int) $question->correct_option;
            $rating = $isCorrect ? SelfRating::Confident : SelfRating::Failed;
            $item->selected_option = $selected;
        } elseif (isset($payload['rating'])) {
            $rating = SelfRating::from((int) $payload['rating']);
        }

        $item->fill([
            'answer_text' => $payload['answer'] ?? null,
            'self_rating' => $rating?->value,
            'is_correct' => $isCorrect,
            'seconds_spent' => max(0, min((int) ($payload['seconds'] ?? 0), 3600)),
            'answered_at' => now(),
        ])->save();

        if ($rating !== null) {
            $this->repetition->record($trainee, $question, $rating);
        }

        return $item;
    }

    /** Закрывает сессию и считает итоговый балл. */
    public function complete(InterviewSession $session): InterviewSession
    {
        $items = $session->items()->whereNotNull('answered_at')->get();

        $score = $items->isEmpty()
            ? 0
            : (int) round($items->avg(fn (SessionItem $item) => ($item->self_rating ?? 0) / 3) * 100);

        $session->fill([
            'status' => SessionStatus::Completed,
            'score' => $score,
            'total_seconds' => min((int) $items->sum('seconds_spent'), 65535),
            'completed_at' => now(),
        ])->save();

        return $session;
    }

    /** Остались ли ещё неотвеченные вопросы. */
    public function hasNext(InterviewSession $session): bool
    {
        return $session->items()->whereNull('answered_at')->exists();
    }
}
