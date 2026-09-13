<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Topic;
use Database\Seeders\QuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Банк вопросов — основная ценность приложения, поэтому его содержимое проверяется тестами:
 * файлы должны быть валидны, а повторный импорт не должен плодить дубли.
 */
class QuestionBankSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_question_bank(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $this->assertGreaterThanOrEqual(10, Topic::count());
        $this->assertGreaterThanOrEqual(100, Question::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(QuestionBankSeeder::class);
        $count = Question::count();

        $this->seed(QuestionBankSeeder::class);

        $this->assertSame($count, Question::count());
    }

    public function test_every_bank_file_is_valid(): void
    {
        $files = glob(database_path('data/questions/*.json'));

        $this->assertNotEmpty($files, 'Банк вопросов не должен быть пустым.');

        $ids = [];

        foreach ($files as $file) {
            $payload = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

            $this->assertArrayHasKey('topic', $payload, basename($file));
            $this->assertArrayHasKey('questions', $payload, basename($file));

            foreach ($payload['questions'] as $question) {
                $label = basename($file).':'.($question['id'] ?? '?');

                foreach (['id', 'type', 'difficulty', 'prompt', 'answer'] as $field) {
                    $this->assertArrayHasKey($field, $question, $label);
                    $this->assertNotEmpty($question[$field], "$label: пустое поле $field");
                }

                $this->assertNotContains($question['id'], $ids, "Дубль идентификатора: {$question['id']}");
                $ids[] = $question['id'];

                $this->assertNotNull(QuestionType::tryFrom($question['type']), "$label: неизвестный тип");
                $this->assertContains($question['difficulty'], ['junior', 'middle', 'senior'], $label);
            }
        }
    }

    public function test_quiz_questions_have_valid_options(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $quizzes = Question::where('type', QuestionType::Quiz->value)->get();

        $this->assertGreaterThanOrEqual(10, $quizzes->count());

        foreach ($quizzes as $quiz) {
            $this->assertIsArray($quiz->options, $quiz->external_id);
            $this->assertGreaterThanOrEqual(2, count($quiz->options), $quiz->external_id);
            $this->assertNotNull($quiz->correct_option, $quiz->external_id);
            $this->assertArrayHasKey($quiz->correct_option, $quiz->options, $quiz->external_id);
            $this->assertTrue($quiz->isAutoGraded(), $quiz->external_id);
        }
    }

    public function test_bank_covers_every_scenario_phase(): void
    {
        $this->seed(QuestionBankSeeder::class);

        foreach ([QuestionType::Theory, QuestionType::Quiz, QuestionType::Coding, QuestionType::Behavioral] as $type) {
            $this->assertGreaterThan(
                0,
                Question::ofType($type)->count(),
                "В банке нет вопросов типа {$type->value} — сценарий интервью не соберётся.",
            );
        }
    }
}
