<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Question;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_list_is_filtered_by_topic(): void
    {
        $laravel = Topic::factory()->create(['name' => 'Ядро Laravel']);
        $sql = Topic::factory()->create(['name' => 'SQL']);

        Question::factory()->for($laravel)->create(['prompt' => 'Что такое сервис-контейнер?']);
        Question::factory()->for($sql)->create(['prompt' => 'Что такое индекс?']);

        $this->get('/questions?topic='.$laravel->id)
            ->assertOk()
            ->assertSee('сервис-контейнер')
            ->assertDontSee('Что такое индекс?');
    }

    public function test_question_list_is_searchable(): void
    {
        $topic = Topic::factory()->create();

        Question::factory()->for($topic)->create(['prompt' => 'Расскажите про очереди в Laravel']);
        Question::factory()->for($topic)->create(['prompt' => 'Что такое CSRF?']);

        $this->get('/questions?search=очереди')
            ->assertOk()
            ->assertSee('очереди в Laravel')
            ->assertDontSee('Что такое CSRF?');
    }

    public function test_question_list_is_filtered_by_difficulty(): void
    {
        $topic = Topic::factory()->create();

        Question::factory()->for($topic)->difficulty(Difficulty::Senior)->create(['prompt' => 'Сложный вопрос про шардинг']);
        Question::factory()->for($topic)->difficulty(Difficulty::Junior)->create(['prompt' => 'Простой вопрос про массивы']);

        $this->get('/questions?difficulty=senior')
            ->assertOk()
            ->assertSee('шардинг')
            ->assertDontSee('массивы');
    }

    public function test_question_page_shows_reference_answer_and_follow_ups(): void
    {
        $question = Question::factory()->create([
            'prompt' => 'Что такое N+1?',
            'answer' => 'Лишние запросы к базе на каждую связь.',
            'follow_ups' => ['Как обнаружить N+1?'],
            'checklist' => ['Называет eager loading'],
        ]);

        $this->get(route('questions.show', $question))
            ->assertOk()
            ->assertSee('Лишние запросы к базе')
            ->assertSee('Как обнаружить N+1?')
            ->assertSee('Называет eager loading');
    }

    public function test_progress_page_is_available(): void
    {
        Question::factory()->create();

        $this->get('/progress')
            ->assertOk()
            ->assertSee('Владение темами');
    }
}
