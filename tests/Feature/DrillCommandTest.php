<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\ReviewCard;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_drill_asks_questions_and_records_ratings(): void
    {
        $topic = Topic::factory()->create(['slug' => 'laravel-core']);
        Question::factory()->for($topic)->create(['prompt' => 'Что такое middleware?']);

        $this->artisan('interview:drill', ['--count' => 1, '--topic' => 'laravel-core'])
            ->expectsOutputToContain('Что такое middleware?')
            ->expectsQuestion('Ответьте вслух и нажмите Enter, чтобы увидеть эталон', ' ')
            ->expectsChoice('Как вы ответили?', 'Ответил', [0 => 'Не знал', 1 => 'Плавал', 2 => 'Ответил', 3 => 'Уверенно'])
            ->expectsOutputToContain('Результат тренировки: 67%')
            ->assertSuccessful();

        $this->assertDatabaseCount(ReviewCard::class, 1);
    }

    public function test_drill_fails_on_unknown_topic(): void
    {
        Topic::factory()->create(['slug' => 'php-core']);

        $this->artisan('interview:drill', ['--topic' => 'cobol'])
            ->expectsOutputToContain('не найдена')
            ->assertFailed();
    }

    public function test_drill_fails_on_unknown_level(): void
    {
        $this->artisan('interview:drill', ['--level' => 'god'])
            ->expectsOutputToContain('junior, middle или senior')
            ->assertFailed();
    }

    public function test_drill_reports_empty_bank(): void
    {
        $this->artisan('interview:drill', ['--count' => 3])
            ->expectsOutputToContain('вопросов не нашлось')
            ->assertFailed();
    }
}
