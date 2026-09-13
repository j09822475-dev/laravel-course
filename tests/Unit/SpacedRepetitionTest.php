<?php

namespace Tests\Unit;

use App\Enums\SelfRating;
use App\Models\Question;
use App\Models\Trainee;
use App\Services\SpacedRepetition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpacedRepetitionTest extends TestCase
{
    use RefreshDatabase;

    protected SpacedRepetition $scheduler;

    protected Trainee $trainee;

    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new SpacedRepetition;
        $this->trainee = Trainee::factory()->create();
        $this->question = Question::factory()->create();
    }

    public function test_failed_answer_returns_question_today(): void
    {
        $card = $this->scheduler->record($this->trainee, $this->question, SelfRating::Failed, Carbon::parse('2026-01-10'));

        $this->assertSame(0, $card->interval_days);
        $this->assertSame('2026-01-10', $card->due_on->toDateString());
        $this->assertSame(1, $card->lapses);
        $this->assertSame(0, $card->repetitions);
    }

    public function test_intervals_grow_with_successful_repetitions(): void
    {
        $first = $this->scheduler->record($this->trainee, $this->question, SelfRating::Good, Carbon::parse('2026-01-01'));
        $this->assertSame(1, $first->interval_days);

        $second = $this->scheduler->record($this->trainee, $this->question, SelfRating::Good, Carbon::parse('2026-01-02'));
        $this->assertSame(6, $second->interval_days);

        $third = $this->scheduler->record($this->trainee, $this->question, SelfRating::Good, Carbon::parse('2026-01-08'));
        $this->assertGreaterThan(6, $third->interval_days);
    }

    public function test_lapse_lowers_ease_factor_and_resets_progress(): void
    {
        $this->scheduler->record($this->trainee, $this->question, SelfRating::Confident, Carbon::parse('2026-01-01'));
        $this->scheduler->record($this->trainee, $this->question, SelfRating::Confident, Carbon::parse('2026-01-04'));

        $card = $this->scheduler->record($this->trainee, $this->question, SelfRating::Failed, Carbon::parse('2026-01-10'));

        $this->assertSame(0, $card->repetitions);
        $this->assertLessThan(2.7, $card->ease_factor);
        $this->assertGreaterThanOrEqual(1.3, $card->ease_factor);
    }

    public function test_interval_never_exceeds_half_a_year(): void
    {
        $card = null;
        $date = Carbon::parse('2026-01-01');

        for ($i = 0; $i < 12; $i++) {
            $card = $this->scheduler->record($this->trainee, $this->question, SelfRating::Confident, $date);
            $date = $date->copy()->addDays(max($card->interval_days, 1));
        }

        $this->assertLessThanOrEqual(180, $card->interval_days);
    }

    public function test_only_due_cards_are_returned(): void
    {
        $later = Question::factory()->create();

        $this->scheduler->record($this->trainee, $this->question, SelfRating::Failed, Carbon::parse('2026-01-10'));
        $this->scheduler->record($this->trainee, $later, SelfRating::Confident, Carbon::parse('2026-01-10'));

        $due = $this->scheduler->due($this->trainee, 10, Carbon::parse('2026-01-10'));

        $this->assertCount(1, $due);
        $this->assertSame($this->question->id, $due->first()->question_id);
        $this->assertSame(1, $this->scheduler->dueCount($this->trainee, Carbon::parse('2026-01-10')));
    }

    public function test_one_card_per_question_and_trainee(): void
    {
        $this->scheduler->record($this->trainee, $this->question, SelfRating::Good);
        $this->scheduler->record($this->trainee, $this->question, SelfRating::Good);

        $this->assertDatabaseCount('review_cards', 1);
    }
}
