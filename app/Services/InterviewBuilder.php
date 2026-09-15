<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\QuestionType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\InterviewSession;
use App\Models\Question;
use App\Models\Trainee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Собирает сессию тренировки: сценарий интервью, блиц-тест или карточки.
 *
 * Сценарий скрининга повторяет реальный процесс найма: сначала рекрутер,
 * потом техническая часть, в конце — практика и вопросы кандидата.
 */
class InterviewBuilder
{
    public function __construct(
        protected QuestionBank $bank,
        protected SpacedRepetition $repetition,
    ) {}

    /**
     * Состав фаз для каждого режима: фаза => [тип вопроса, количество].
     *
     * @return array<string, array{types: list<QuestionType>, count: int}>
     */
    public function blueprint(SessionMode $mode): array
    {
        return match ($mode) {
            SessionMode::Screening => [
                'intro' => ['types' => [QuestionType::Behavioral], 'count' => 2],
                'screening' => ['types' => [QuestionType::Theory, QuestionType::Quiz], 'count' => 6],
                'wrap_up' => ['types' => [QuestionType::Behavioral], 'count' => 1],
            ],
            SessionMode::TechInterview => [
                'intro' => ['types' => [QuestionType::Behavioral], 'count' => 1],
                'tech' => ['types' => [QuestionType::Theory], 'count' => 8],
                'live_coding' => ['types' => [QuestionType::Coding, QuestionType::SystemDesign], 'count' => 3],
                'wrap_up' => ['types' => [QuestionType::Behavioral], 'count' => 1],
            ],
            SessionMode::Quiz => [
                'tech' => ['types' => [QuestionType::Quiz], 'count' => 10],
            ],
            // Английский скрининг: разогрев речи, самопрезентация, техническое
            // объяснение на английском и отработка речевых блоков в конце.
            SessionMode::EnglishInterview => [
                'warm_up' => ['types' => [QuestionType::Shadowing], 'count' => 1, 'areas' => ['language']],
                'intro' => ['types' => [QuestionType::Behavioral], 'count' => 2, 'areas' => ['language']],
                'tech' => ['types' => [QuestionType::Theory], 'count' => 3, 'except_areas' => ['language', 'soft']],
                'drill' => ['types' => [QuestionType::Cloze], 'count' => 3, 'areas' => ['language']],
                'wrap_up' => ['types' => [QuestionType::Behavioral, QuestionType::Quiz], 'count' => 1, 'areas' => ['language']],
            ],
            SessionMode::Drill => [
                'tech' => ['types' => [QuestionType::Theory, QuestionType::Coding], 'count' => 10],
            ],
        };
    }

    /**
     * @param  array{topics?: list<int>, level?: string, size?: int}  $options
     */
    public function create(Trainee $trainee, SessionMode $mode, array $options = []): InterviewSession
    {
        $level = isset($options['level'])
            ? Difficulty::from($options['level'])
            : $trainee->target_level;

        $questions = $mode === SessionMode::Drill
            ? $this->drillQuestions($trainee, $options, $level)
            : $this->scenarioQuestions($mode, $options, $level);

        if ($questions->isEmpty()) {
            throw new \RuntimeException('Не удалось подобрать вопросы под выбранные фильтры.');
        }

        $language = $this->language($mode, $options);

        return DB::transaction(function () use ($trainee, $mode, $options, $level, $language, $questions) {
            $session = $trainee->sessions()->create([
                'mode' => $mode,
                'title' => $this->title($mode, $level, $language),
                'status' => SessionStatus::InProgress,
                'config' => [
                    'level' => $level->value,
                    'topics' => $options['topics'] ?? [],
                    'language' => $language->value,
                ],
                'started_at' => now(),
            ]);

            $position = 0;

            foreach ($questions as $entry) {
                $session->items()->create([
                    'question_id' => $entry['question']->id,
                    'position' => ++$position,
                    'phase' => $entry['phase'],
                ]);
            }

            return $session->load('items.question.topic');
        });
    }

    /**
     * Вопросы по сценарию интервью: каждая фаза набирается отдельно и без повторов.
     *
     * @return Collection<int, array{question: Question, phase: string}>
     */
    protected function scenarioQuestions(SessionMode $mode, array $options, Difficulty $level): Collection
    {
        $used = [];
        $result = collect();

        $language = $this->language($mode, $options);

        foreach ($this->blueprint($mode) as $phase => $spec) {
            $filters = [
                'types' => $spec['types'],
                'difficulties' => $level->scope(),
                'areas' => $spec['areas'] ?? null,
                // Языковую тему не подмешиваем в технические фазы и наоборот.
                'except_areas' => $spec['except_areas'] ?? (isset($spec['areas']) ? null : ['language']),
                'exclude' => $used,
            ];

            if ($language->isEnglish()) {
                $filters['language'] = $language;
            }

            $picked = $this->bank->pick($spec['count'], $filters + ['topics' => $options['topics'] ?? null]);

            // Поведенческие и языковые вопросы не зависят от выбранных технических тем.
            if ($picked->isEmpty() && ! empty($options['topics'])) {
                $picked = $this->bank->pick($spec['count'], $filters);
            }

            foreach ($picked as $question) {
                $used[] = $question->id;
                $result->push(['question' => $question, 'phase' => $phase]);
            }
        }

        return $result;
    }

    /**
     * Режим карточек: сначала то, что пора повторить, затем слабые темы.
     *
     * @return Collection<int, array{question: Question, phase: string}>
     */
    protected function drillQuestions(Trainee $trainee, array $options, Difficulty $level): Collection
    {
        $size = (int) ($options['size'] ?? 10);
        $topics = $options['topics'] ?? null;

        $due = $this->repetition->due($trainee, $size)
            ->pluck('question')
            ->filter()
            ->when($topics, fn (Collection $questions) => $questions->whereIn('topic_id', $topics))
            ->values();

        $language = $this->language(SessionMode::Drill, $options);

        $fresh = $this->bank->pickWeakest($trainee, max($size - $due->count(), 0), [
            'types' => [QuestionType::Theory, QuestionType::Coding, QuestionType::SystemDesign],
            'difficulties' => $level->scope(),
            'topics' => $topics,
            'except_areas' => $topics ? null : ['language'],
            'language' => $language->isEnglish() ? $language : null,
            'exclude' => $due->pluck('id')->all(),
        ]);

        $questions = $due->concat($fresh)->take($size);

        return $this->bank
            ->interleave($questions)
            ->map(fn (Question $question) => ['question' => $question, 'phase' => 'tech']);
    }

    /**
     * Язык сессии: режим английского интервью всегда на английском,
     * остальные режимы можно переключить вручную.
     */
    public function language(SessionMode $mode, array $options = []): Language
    {
        if ($mode === SessionMode::EnglishInterview) {
            return Language::En;
        }

        return isset($options['language'])
            ? Language::from($options['language'])
            : Language::Ru;
    }

    protected function title(SessionMode $mode, Difficulty $level, Language $language): string
    {
        return sprintf(
            '%s · %s%s',
            $mode->label(),
            $level->label(),
            $language->isEnglish() && $mode !== SessionMode::EnglishInterview ? ' · EN' : '',
        );
    }
}
