<?php

namespace Tests\Feature;

use App\Enums\Language;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionTranslation;
use Database\Seeders\QuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Переводы — такая же часть контента, как и сами вопросы, поэтому их целостность
 * проверяется тестами: опечатка в идентификаторе или расхождение вариантов
 * ответа сделали бы английский режим неработоспособным незаметно.
 */
class TranslationBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_question_has_an_english_translation(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $untranslated = Question::query()
            ->whereDoesntHave('translations', fn ($q) => $q->where('locale', 'en'))
            ->pluck('external_id');

        $this->assertEmpty(
            $untranslated->all(),
            'Без перевода остались вопросы: '.$untranslated->implode(', '),
        );
    }

    public function test_translation_files_reference_existing_questions(): void
    {
        $ids = [];

        foreach (glob(database_path('data/questions/*.json')) as $file) {
            $payload = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

            foreach ($payload['questions'] as $question) {
                $ids[] = $question['id'];
            }
        }

        foreach (glob(database_path('data/translations/*/*.json')) as $file) {
            $payload = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

            $this->assertNotNull(
                Language::tryFrom($payload['locale']),
                basename($file).': неизвестный язык '.$payload['locale'],
            );

            foreach ($payload['questions'] as $externalId => $translation) {
                $this->assertContains($externalId, $ids, basename($file).": перевод без вопроса {$externalId}");
                $this->assertNotEmpty($translation, basename($file).": пустой перевод {$externalId}");
            }
        }
    }

    public function test_translated_quiz_keeps_the_same_number_of_options(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $quizzes = Question::query()
            ->where('type', QuestionType::Quiz->value)
            ->with('translations')
            ->get();

        foreach ($quizzes as $quiz) {
            $translated = $quiz->in(Language::En);

            $this->assertCount(
                count($quiz->options),
                $translated->options,
                "{$quiz->external_id}: число вариантов в переводе не совпадает — правильный ответ съедет",
            );
        }
    }

    public function test_cloze_questions_have_accepted_answers(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $cloze = Question::where('type', QuestionType::Cloze->value)->get();

        $this->assertGreaterThanOrEqual(5, $cloze->count());

        foreach ($cloze as $question) {
            $this->assertNotEmpty($question->accepted, "{$question->external_id}: нет допустимых ответов");
            $this->assertContains(
                $question->answer,
                $question->accepted,
                "{$question->external_id}: эталонный ответ отсутствует в списке допустимых",
            );
            $this->assertTrue($question->isAutoGraded());
        }
    }

    public function test_language_topic_covers_every_drill_format(): void
    {
        $this->seed(QuestionBankSeeder::class);

        foreach ([QuestionType::Behavioral, QuestionType::Cloze, QuestionType::Shadowing] as $type) {
            $count = Question::query()
                ->where('type', $type->value)
                ->whereHas('topic', fn ($q) => $q->where('area', 'language'))
                ->count();

            $this->assertGreaterThan(0, $count, "В языковой теме нет заданий типа {$type->value}");
        }
    }

    public function test_seeding_twice_does_not_duplicate_translations(): void
    {
        $this->seed(QuestionBankSeeder::class);
        $count = QuestionTranslation::count();

        $this->seed(QuestionBankSeeder::class);

        $this->assertSame($count, QuestionTranslation::count());
    }
}
