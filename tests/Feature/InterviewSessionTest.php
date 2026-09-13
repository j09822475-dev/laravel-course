<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Enums\SessionStatus;
use App\Models\InterviewSession;
use App\Models\Question;
use App\Models\ReviewCard;
use App\Models\Topic;
use App\Models\Trainee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function seedQuestionBank(): void
    {
        $topic = Topic::factory()->create();

        Question::factory()->count(12)->for($topic)->create();
        Question::factory()->count(12)->for($topic)->quiz()->create();
        Question::factory()->count(6)->for($topic)->behavioral()->create();
        Question::factory()->count(4)->for($topic)->coding()->create();
    }

    public function test_screening_session_is_built_from_scenario_phases(): void
    {
        $this->seedQuestionBank();
        $this->get('/');

        $this->post('/sessions', ['mode' => 'screening', 'level' => 'middle'])
            ->assertRedirect();

        $session = InterviewSession::sole();

        $this->assertSame('screening', $session->mode->value);
        $this->assertSame(9, $session->items()->count());
        $this->assertSame(
            ['intro', 'screening', 'wrap_up'],
            $session->items->pluck('phase')->unique()->values()->all(),
        );
    }

    public function test_open_question_requires_self_rating(): void
    {
        $this->seedQuestionBank();
        $this->get('/');
        $this->post('/sessions', ['mode' => 'drill', 'size' => 3]);

        $session = InterviewSession::sole();

        $this->from(route('sessions.show', $session))
            ->post(route('sessions.answer', $session), ['answer' => 'Мой ответ'])
            ->assertSessionHasErrors('rating');

        $this->assertNull($session->items()->first()->answered_at);
    }

    public function test_self_rated_answer_creates_review_card(): void
    {
        $this->seedQuestionBank();
        $this->get('/');
        $this->post('/sessions', ['mode' => 'drill', 'size' => 3]);

        $session = InterviewSession::sole();

        $this->post(route('sessions.answer', $session), [
            'answer' => 'Ответ кандидата',
            'rating' => 2,
            'seconds' => 42,
        ])->assertRedirect();

        $item = $session->items()->first();

        $this->assertSame(2, $item->self_rating);
        $this->assertSame(42, $item->seconds_spent);
        $this->assertNotNull($item->answered_at);
        $this->assertDatabaseCount(ReviewCard::class, 1);
    }

    public function test_quiz_answer_is_graded_automatically(): void
    {
        $topic = Topic::factory()->create();
        Question::factory()->count(10)->for($topic)->quiz(correct: 1)->create();

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz']);

        $session = InterviewSession::sole();

        $this->post(route('sessions.answer', $session), ['option' => 1, 'seconds' => 10]);
        $this->post(route('sessions.answer', $session), ['option' => 0, 'seconds' => 10]);

        $items = $session->items()->whereNotNull('answered_at')->get();

        $this->assertTrue($items[0]->is_correct);
        $this->assertFalse($items[1]->is_correct);
        $this->assertSame(3, $items[0]->self_rating);
        $this->assertSame(0, $items[1]->self_rating);
    }

    public function test_session_completes_with_score_after_last_answer(): void
    {
        $topic = Topic::factory()->create();
        Question::factory()->count(10)->for($topic)->quiz(correct: 0)->create();

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz']);

        $session = InterviewSession::sole();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('sessions.answer', $session), ['option' => 0, 'seconds' => 5]);
        }

        $session->refresh();

        $this->assertSame(SessionStatus::Completed, $session->status);
        $this->assertSame(100, $session->score);
        $this->assertNotNull($session->completed_at);

        $this->get(route('sessions.report', $session))
            ->assertOk()
            ->assertSee('Готовы к собеседованию');
    }

    public function test_trainee_cannot_open_foreign_session(): void
    {
        $this->seedQuestionBank();
        $this->get('/');
        $this->post('/sessions', ['mode' => 'drill', 'size' => 3]);

        $foreign = InterviewSession::sole();
        $foreign->update(['trainee_id' => Trainee::factory()->create()->id]);

        $this->get(route('sessions.show', $foreign))->assertForbidden();
        $this->post(route('sessions.answer', $foreign), ['rating' => 1])->assertForbidden();
    }

    public function test_session_can_be_abandoned(): void
    {
        $this->seedQuestionBank();
        $this->get('/');
        $this->post('/sessions', ['mode' => 'drill', 'size' => 3]);

        $session = InterviewSession::sole();

        $this->delete(route('sessions.destroy', $session))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(SessionStatus::Abandoned, $session->refresh()->status);
    }

    public function test_session_respects_selected_level_and_topics(): void
    {
        $wanted = Topic::factory()->create();
        $other = Topic::factory()->create();

        Question::factory()->count(10)->for($wanted)->difficulty(Difficulty::Junior)->quiz()->create();
        Question::factory()->count(10)->for($other)->difficulty(Difficulty::Senior)->quiz()->create();

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz', 'level' => 'junior', 'topics' => [$wanted->id]]);

        $session = InterviewSession::sole();
        $topics = $session->items->map(fn ($item) => $item->question->topic_id)->unique();

        $this->assertSame([$wanted->id], $topics->values()->all());
    }

    public function test_session_creation_requires_known_mode(): void
    {
        $this->get('/');

        $this->from('/')
            ->post('/sessions', ['mode' => 'coffee_break'])
            ->assertSessionHasErrors('mode');
    }
}
