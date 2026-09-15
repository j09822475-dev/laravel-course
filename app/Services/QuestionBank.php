<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Подбор вопросов для тренировки: с учётом уровня, темы и слабых мест кандидата.
 */
class QuestionBank
{
    public function __construct(protected ProgressReport $progress) {}

    /**
     * Случайная выборка вопросов с фильтрами.
     *
     * @param  array{types?: list<QuestionType|string>, topics?: list<int>, difficulties?: list<Difficulty|string>, exclude?: list<int>}  $filters
     * @return Collection<int, Question>
     */
    public function pick(int $limit, array $filters = []): Collection
    {
        return $this->query($filters)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Вопросы, приоритизированные по слабым темам кандидата:
     * сначала то, что проваливали или ещё не проходили.
     *
     * @return Collection<int, Question>
     */
    public function pickWeakest(Trainee $trainee, int $limit, array $filters = []): Collection
    {
        $weights = $this->topicWeights($trainee);

        $candidates = $this->query($filters)
            ->inRandomOrder()
            ->limit(max($limit * 4, 40))
            ->get();

        return $candidates
            ->sortByDesc(fn (Question $question) => $weights[$question->topic_id] ?? 1.0)
            ->take($limit)
            ->values();
    }

    /**
     * Перемешивает выборку так, чтобы соседние вопросы были из разных тем.
     *
     * Приём: чередование (interleaving). Вперемешку материал усваивается хуже
     * в моменте, но удерживается заметно дольше, чем блоками по одной теме.
     *
     * @param  SupportCollection<int, Question>  $questions
     * @return SupportCollection<int, Question>
     */
    public function interleave(SupportCollection $questions): SupportCollection
    {
        $byTopic = $questions->groupBy('topic_id')->map->values()->values();
        $result = collect();

        while ($byTopic->isNotEmpty()) {
            $byTopic = $byTopic->map(function (SupportCollection $group) use ($result) {
                if ($group->isNotEmpty()) {
                    $result->push($group->shift());
                }

                return $group;
            })->filter(fn (SupportCollection $group) => $group->isNotEmpty())->values();
        }

        return $result;
    }

    /**
     * Вес темы: чем хуже средняя самооценка, тем выше приоритет.
     * Темы без ответов получают повышенный вес — их нужно проверить.
     *
     * @return array<int, float>
     */
    public function topicWeights(Trainee $trainee): array
    {
        $stats = $this->progress->topicStats($trainee);

        return $stats->mapWithKeys(fn (array $row) => [
            $row['topic']->id => $row['answered'] === 0 ? 1.5 : 2.0 - $row['mastery'],
        ])->all();
    }

    /** @return Builder<Question> */
    public function query(array $filters = []): Builder
    {
        $query = Question::query()->with(['topic', 'translations']);

        if ($types = $filters['types'] ?? null) {
            $query->whereIn('type', array_map(
                fn (QuestionType|string $type) => $type instanceof QuestionType ? $type->value : $type,
                $types,
            ));
        }

        if ($topics = $filters['topics'] ?? null) {
            $query->whereIn('topic_id', $topics);
        }

        // Область тем: language — упражнения на английский, остальные — технические.
        if ($areas = $filters['areas'] ?? null) {
            $query->whereHas('topic', fn (Builder $q) => $q->whereIn('area', (array) $areas));
        }

        if ($exceptAreas = $filters['except_areas'] ?? null) {
            $query->whereHas('topic', fn (Builder $q) => $q->whereNotIn('area', (array) $exceptAreas));
        }

        // В сессии на английском показываем только то, что переведено.
        if ($language = $filters['language'] ?? null) {
            $query->translatedInto($language instanceof Language ? $language : Language::from($language));
        }

        if ($difficulties = $filters['difficulties'] ?? null) {
            $query->ofDifficulty($difficulties);
        }

        if ($exclude = $filters['exclude'] ?? null) {
            $query->whereNotIn('id', $exclude);
        }

        if ($term = $filters['search'] ?? null) {
            $query->search($term);
        }

        return $query;
    }
}
