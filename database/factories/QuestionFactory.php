<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'external_id' => 'q-'.fake()->unique()->numerify('######'),
            'type' => QuestionType::Theory,
            'difficulty' => Difficulty::Middle,
            'prompt' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'explanation' => null,
            'options' => null,
            'correct_option' => null,
            'follow_ups' => [fake()->sentence()],
            'checklist' => null,
            'red_flags' => null,
            'tags' => ['test'],
            'estimated_seconds' => 120,
        ];
    }

    /** Вопрос с вариантами ответов — проверяется автоматически. */
    public function quiz(int $correct = 1): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::Quiz,
            'options' => ['Вариант А', 'Вариант Б', 'Вариант В'],
            'correct_option' => $correct,
        ]);
    }

    public function behavioral(): static
    {
        return $this->state(fn () => ['type' => QuestionType::Behavioral]);
    }

    public function coding(): static
    {
        return $this->state(fn () => ['type' => QuestionType::Coding]);
    }

    public function difficulty(Difficulty $difficulty): static
    {
        return $this->state(fn () => ['difficulty' => $difficulty]);
    }
}
