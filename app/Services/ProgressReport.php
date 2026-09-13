<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\InterviewSession;
use App\Models\SessionItem;
use App\Models\Topic;
use App\Models\Trainee;
use App\Support\Plural;
use Illuminate\Support\Collection;

/**
 * Сводит ответы кандидата в статистику: владение темами, готовность к собеседованию,
 * слабые места и рекомендации.
 */
class ProgressReport
{
    /**
     * Статистика по каждой теме.
     *
     * @return Collection<int, array{topic: Topic, answered: int, mastery: float, failed: int}>
     */
    public function topicStats(Trainee $trainee): Collection
    {
        $rows = SessionItem::query()
            ->join('questions', 'questions.id', '=', 'session_items.question_id')
            ->join('interview_sessions', 'interview_sessions.id', '=', 'session_items.interview_session_id')
            ->where('interview_sessions.trainee_id', $trainee->id)
            ->whereNotNull('session_items.answered_at')
            ->selectRaw('questions.topic_id as topic_id')
            ->selectRaw('count(*) as answered')
            ->selectRaw('avg(coalesce(session_items.self_rating, 0)) as avg_rating')
            ->selectRaw('sum(case when coalesce(session_items.self_rating, 0) <= 1 then 1 else 0 end) as failed')
            ->groupBy('questions.topic_id')
            ->get()
            ->keyBy('topic_id');

        return Topic::query()
            ->withCount('questions')
            ->orderBy('position')
            ->get()
            ->map(function (Topic $topic) use ($rows) {
                $row = $rows->get($topic->id);
                $answered = (int) ($row->answered ?? 0);

                return [
                    'topic' => $topic,
                    'answered' => $answered,
                    'failed' => (int) ($row->failed ?? 0),
                    'mastery' => $answered > 0 ? round(((float) $row->avg_rating) / 3, 3) : 0.0,
                    'coverage' => $topic->questions_count > 0
                        ? round(min($answered / $topic->questions_count, 1.0), 3)
                        : 0.0,
                ];
            });
    }

    /**
     * Готовность к собеседованию в процентах.
     * Учитываем и качество ответов, и охват банка вопросов: знать половину тем идеально — мало.
     */
    public function readiness(Trainee $trainee): int
    {
        $stats = $this->topicStats($trainee);
        $touched = $stats->where('answered', '>', 0);

        if ($touched->isEmpty()) {
            return 0;
        }

        $quality = $touched->avg('mastery');
        $coverage = $stats->avg('coverage');

        return (int) round((0.7 * $quality + 0.3 * $coverage) * 100);
    }

    /**
     * Темы, которые стоит подтянуть в первую очередь.
     *
     * @return Collection<int, array{topic: Topic, answered: int, mastery: float, failed: int}>
     */
    public function weakSpots(Trainee $trainee, int $limit = 3): Collection
    {
        return $this->topicStats($trainee)
            ->filter(fn (array $row) => $row['answered'] > 0 && $row['mastery'] < 0.7)
            ->sortBy('mastery')
            ->take($limit)
            ->values();
    }

    /** Короткие рекомендации по итогам сессии. */
    public function advice(InterviewSession $session): array
    {
        $items = $session->items()->with('question.topic')->get()->whereNotNull('answered_at');

        if ($items->isEmpty()) {
            return [];
        }

        $advice = [];

        $weak = $items->filter(fn (SessionItem $item) => ($item->self_rating ?? 0) <= 1)
            ->groupBy(fn (SessionItem $item) => $item->question->topic->name)
            ->sortByDesc(fn (Collection $group) => $group->count());

        foreach ($weak->take(3) as $topicName => $group) {
            $advice[] = sprintf(
                'Тема «%s»: %s — вернитесь к ней в режиме карточек.',
                $topicName,
                Plural::count($group->count(), 'проваленный вопрос', 'проваленных вопроса', 'проваленных вопросов'),
            );
        }

        $slow = $items->filter(fn (SessionItem $item) => $item->seconds_spent > $item->question->estimated_seconds * 1.5);

        if ($slow->isNotEmpty()) {
            $advice[] = sprintf(
                'На %s вы потратили заметно больше ориентировочного времени — тренируйте краткий ответ на 1–2 минуты.',
                Plural::count($slow->count(), 'вопросе', 'вопросах', 'вопросах'),
            );
        }

        $confident = $items->filter(fn (SessionItem $item) => ($item->self_rating ?? 0) === 3)->count();

        if ($confident / max($items->count(), 1) >= 0.8) {
            $advice[] = 'Уровень уверенный: попробуйте следующий грейд или полное техническое интервью на 60 минут.';
        }

        return $advice;
    }

    /** Последние завершённые сессии. */
    public function recentSessions(Trainee $trainee, int $limit = 10): Collection
    {
        return $trainee->sessions()
            ->where('status', SessionStatus::Completed->value)
            ->latest('completed_at')
            ->limit($limit)
            ->get();
    }

    /** Сводка по всем сессиям кандидата. */
    public function summary(Trainee $trainee): array
    {
        $sessions = $trainee->sessions()->completed()->get();

        return [
            'sessions' => $sessions->count(),
            'answered' => SessionItem::query()
                ->whereIn('interview_session_id', $trainee->sessions()->pluck('id'))
                ->whereNotNull('answered_at')
                ->count(),
            'average_score' => $sessions->isEmpty() ? null : (int) round($sessions->avg('score')),
            'minutes' => (int) round($sessions->sum('total_seconds') / 60),
        ];
    }
}
