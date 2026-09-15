<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Наполняет банк вопросов из JSON-файлов в database/data/questions.
 * Сидер идемпотентен: повторный запуск обновляет существующие записи.
 */
class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $models = [];

        foreach ($this->files() as $path) {
            $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            $topic = Topic::updateOrCreate(
                ['slug' => $payload['topic']['slug']],
                [
                    'name' => $payload['topic']['name'],
                    'area' => $payload['topic']['area'],
                    'description' => $payload['topic']['description'] ?? null,
                    'position' => $payload['topic']['position'] ?? 0,
                ],
            );

            foreach ($payload['questions'] as $question) {
                $models[$question['id']] = Question::updateOrCreate(
                    ['external_id' => $question['id']],
                    [
                        'topic_id' => $topic->id,
                        'type' => $question['type'],
                        'difficulty' => $question['difficulty'],
                        'prompt' => $question['prompt'],
                        'answer' => $question['answer'],
                        'explanation' => $question['explanation'] ?? null,
                        'options' => $question['options'] ?? null,
                        'correct_option' => $question['correct_option'] ?? null,
                        'accepted' => $question['accepted'] ?? null,
                        'follow_ups' => $question['follow_ups'] ?? null,
                        'checklist' => $question['checklist'] ?? null,
                        'red_flags' => $question['red_flags'] ?? null,
                        'tags' => $question['tags'] ?? null,
                        'estimated_seconds' => $question['estimated_seconds'] ?? 120,
                    ],
                );
            }
        }

        $this->seedTranslations($models);
    }

    /**
     * Переводы лежат отдельно от оригиналов: их можно дополнять по частям,
     * а незаполненные поля берутся из русской версии вопроса.
     *
     * @param  array<string, Question>  $questions
     */
    protected function seedTranslations(array $questions): void
    {
        foreach (glob(database_path('data/translations/*/*.json')) ?: [] as $path) {
            $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $locale = $payload['locale'];

            foreach ($payload['questions'] as $externalId => $translation) {
                $question = $questions[$externalId] ?? Question::firstWhere('external_id', $externalId);

                if (! $question) {
                    continue;
                }

                QuestionTranslation::updateOrCreate(
                    ['question_id' => $question->id, 'locale' => $locale],
                    [
                        'prompt' => $translation['prompt'] ?? $question->prompt,
                        'answer' => $translation['answer'] ?? $question->answer,
                        'explanation' => $translation['explanation'] ?? null,
                        'options' => $translation['options'] ?? null,
                        'follow_ups' => $translation['follow_ups'] ?? null,
                    ],
                );
            }
        }
    }

    /** @return list<string> */
    protected function files(): array
    {
        $files = glob(database_path('data/questions/*.json'));

        sort($files);

        return $files ?: [];
    }
}
