<?php

namespace App\Console\Commands;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Enums\SelfRating;
use App\Models\Question;
use App\Models\Topic;
use App\Models\Trainee;
use App\Services\QuestionBank;
use App\Services\SpacedRepetition;
use Illuminate\Console\Command;

/**
 * Тренировка в терминале: вопрос — ваш ответ вслух — эталон — самооценка.
 * Результат попадает в ту же очередь повторений, что и веб-интерфейс.
 */
class DrillCommand extends Command
{
    protected $signature = 'interview:drill
        {--topic= : Слаг темы, например laravel-core}
        {--level=middle : Уровень: junior, middle или senior}
        {--count=5 : Сколько вопросов задать}
        {--trainee= : UUID существующего профиля кандидата}';

    protected $description = 'Тренировка ответов на вопросы собеседования в терминале';

    public function handle(QuestionBank $bank, SpacedRepetition $repetition): int
    {
        $level = Difficulty::tryFrom((string) $this->option('level'));

        if (! $level) {
            $this->error('Уровень должен быть junior, middle или senior.');

            return self::FAILURE;
        }

        $topic = null;

        if ($slug = $this->option('topic')) {
            $topic = Topic::firstWhere('slug', $slug);

            if (! $topic) {
                $this->error("Тема «{$slug}» не найдена. Доступные: ".Topic::pluck('slug')->implode(', '));

                return self::FAILURE;
            }
        }

        $trainee = $this->resolveTrainee();

        $questions = $bank->pick((int) $this->option('count'), [
            'types' => [QuestionType::Theory, QuestionType::Coding, QuestionType::Behavioral],
            'difficulties' => $level->scope(),
            'topics' => $topic ? [$topic->id] : null,
        ]);

        if ($questions->isEmpty()) {
            $this->error('Под эти фильтры вопросов не нашлось. Проверьте, что банк загружен: php artisan db:seed');

            return self::FAILURE;
        }

        $this->line('');
        $this->info("Тренировка: {$questions->count()} вопросов, уровень {$level->label()}");
        $this->comment('Отвечайте вслух, как на собеседовании, и только потом смотрите эталон.');

        $ratings = [];

        foreach ($questions as $index => $question) {
            $ratings[] = $this->askQuestion($question, $index + 1, $questions->count(), $trainee, $repetition);
        }

        $this->printSummary($ratings);

        return self::SUCCESS;
    }

    protected function askQuestion(
        Question $question,
        int $number,
        int $total,
        Trainee $trainee,
        SpacedRepetition $repetition,
    ): SelfRating {
        $this->line('');
        $this->line(str_repeat('─', 60));
        $this->line("<fg=gray>[{$number}/{$total}] {$question->topic->name} · {$question->difficulty->label()} · ориентир ".
            ceil($question->estimated_seconds / 60).' мин</>');
        $this->line('');
        $this->line("<options=bold>{$question->prompt}</>");
        $this->line('');

        $this->ask('Ответьте вслух и нажмите Enter, чтобы увидеть эталон', ' ');

        $this->line('');
        $this->line('<fg=green>Эталонный ответ:</>');
        $this->line($question->answer);

        if ($question->checklist) {
            $this->line('');
            $this->line('<fg=green>Что оценивает интервьюер:</>');

            foreach ($question->checklist as $point) {
                $this->line("  ✓ {$point}");
            }
        }

        if ($question->follow_ups) {
            $this->line('');
            $this->line('<fg=yellow>Уточняющие вопросы:</>');

            foreach ($question->follow_ups as $followUp) {
                $this->line("  — {$followUp}");
            }
        }

        $this->line('');

        $labels = collect(SelfRating::cases())->mapWithKeys(
            fn (SelfRating $rating) => [$rating->value => $rating->label()],
        )->all();

        $choice = $this->choice('Как вы ответили?', $labels, SelfRating::Good->value);
        $rating = collect(SelfRating::cases())->firstWhere(fn (SelfRating $case) => $case->label() === $choice)
            ?? SelfRating::Good;

        $repetition->record($trainee, $question, $rating);

        return $rating;
    }

    /** @param  list<SelfRating>  $ratings */
    protected function printSummary(array $ratings): void
    {
        $score = (int) round(collect($ratings)->avg(fn (SelfRating $rating) => $rating->share()) * 100);

        $this->line('');
        $this->line(str_repeat('═', 60));
        $this->info("Результат тренировки: {$score}%");

        $failed = collect($ratings)->filter(fn (SelfRating $rating) => $rating->value <= 1)->count();

        if ($failed > 0) {
            $this->comment("Вопросов с провалом: {$failed} — они вернутся в ближайшие дни.");
        } else {
            $this->comment('Все вопросы закрыты уверенно — попробуйте уровень выше.');
        }
    }

    protected function resolveTrainee(): Trainee
    {
        if ($uuid = $this->option('trainee')) {
            return Trainee::firstWhere('uuid', $uuid) ?? Trainee::create(['name' => 'CLI']);
        }

        return Trainee::firstWhere('name', 'CLI') ?? Trainee::create(['name' => 'CLI']);
    }
}
