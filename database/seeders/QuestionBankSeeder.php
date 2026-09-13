<?php

namespace Database\Seeders;

use App\Models\Question;
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
                Question::updateOrCreate(
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
                        'follow_ups' => $question['follow_ups'] ?? null,
                        'checklist' => $question['checklist'] ?? null,
                        'red_flags' => $question['red_flags'] ?? null,
                        'tags' => $question['tags'] ?? null,
                        'estimated_seconds' => $question['estimated_seconds'] ?? 120,
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
