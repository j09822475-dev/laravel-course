<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Topic;
use App\Models\Trainee;
use App\Models\User;
use App\Services\ProgressSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** API для мобильного клиента: токены на устройство и полный цикл тренировки. */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBank(): Topic
    {
        $topic = Topic::factory()->create();
        Question::factory()->count(12)->for($topic)->quiz(correct: 1)->create();

        return $topic;
    }

    public function test_registration_returns_a_device_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device' => 'Pixel 8',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['name', 'email']]);

        $user = User::sole();

        $this->assertNotNull($user->trainee, 'Профиль прогресса должен создаваться сразу');
        $this->assertSame('Pixel 8', $user->tokens()->sole()->name);
    }

    public function test_login_returns_a_token_and_wrong_password_does_not(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/progress')->assertUnauthorized();
        $this->postJson('/api/v1/sessions', ['mode' => 'quiz'])->assertUnauthorized();
    }

    public function test_profile_endpoint_returns_progress_summary(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Иван']));

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.name', 'Иван')
            ->assertJsonPath('readiness', 0)
            ->assertJsonStructure(['profile' => ['name', 'target_level', 'share_url'], 'summary', 'due_count']);
    }

    public function test_mobile_client_can_run_a_whole_session(): void
    {
        $this->seedBank();
        Sanctum::actingAs(User::factory()->create());

        $start = $this->postJson('/api/v1/sessions', ['mode' => 'quiz'])->assertCreated();

        $sessionId = $start->json('session.id');

        $this->assertSame(10, $start->json('session.total'));
        $this->assertNotNull($start->json('current.question.prompt'));
        $this->assertNull($start->json('current.question.answer'), 'Эталон не должен приходить до ответа');

        $answer = $this->postJson("/api/v1/sessions/{$sessionId}/answer", ['option' => 1, 'seconds' => 7])
            ->assertOk();

        $this->assertTrue($answer->json('graded.is_correct'));
        $this->assertNotNull($answer->json('graded.question.answer'), 'После ответа эталон приходит');
        $this->assertSame(1, $answer->json('session.answered'));

        for ($i = 0; $i < 9; $i++) {
            $this->postJson("/api/v1/sessions/{$sessionId}/answer", ['option' => 1, 'seconds' => 5]);
        }

        $report = $this->getJson("/api/v1/sessions/{$sessionId}/report")->assertOk();

        $this->assertSame('completed', $report->json('session.status'));
        $this->assertSame(100, $report->json('session.score'));
        $this->assertCount(10, $report->json('items'));
    }

    public function test_open_question_requires_a_self_rating(): void
    {
        $topic = Topic::factory()->create();
        Question::factory()->count(12)->for($topic)->create();

        Sanctum::actingAs(User::factory()->create());

        $sessionId = $this->postJson('/api/v1/sessions', ['mode' => 'drill', 'size' => 3])->json('session.id');

        $this->postJson("/api/v1/sessions/{$sessionId}/answer", ['answer' => 'мой ответ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_shadowing_passage_is_visible_before_answering(): void
    {
        $topic = Topic::factory()->create(['area' => 'language']);
        Question::factory()->count(3)->for($topic)->create([
            'type' => QuestionType::Shadowing,
            'answer' => 'I am a full stack developer with four years of experience.',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trainee = (new ProgressSync)->attach($user);
        $session = $trainee->sessions()->create([
            'mode' => 'english_interview',
            'title' => 'Интервью на английском',
            'status' => 'in_progress',
            'config' => ['language' => 'en'],
            'started_at' => now(),
        ]);
        $session->items()->create([
            'question_id' => Question::where('type', 'shadowing')->first()->id,
            'position' => 1,
            'phase' => 'warm_up',
        ]);

        $state = $this->getJson("/api/v1/sessions/{$session->id}")->assertOk();

        // Для шэдоуинга образец и есть задание: без него упражнение бессмысленно.
        $this->assertSame(
            'I am a full stack developer with four years of experience.',
            $state->json('current.question.answer'),
        );

        // У обычного вопроса эталон по-прежнему скрыт до ответа.
        $theory = Question::factory()->create(['answer' => 'Скрытый эталон']);
        $session->items()->create(['question_id' => $theory->id, 'position' => 2, 'phase' => 'tech']);
        $this->postJson("/api/v1/sessions/{$session->id}/answer", ['rating' => 2]);

        $next = $this->getJson("/api/v1/sessions/{$session->id}")->assertOk();
        $this->assertNull($next->json('current.question.answer'));
    }

    public function test_session_of_another_account_is_forbidden(): void
    {
        $this->seedBank();

        $stranger = User::factory()->create();
        $strangerTrainee = Trainee::factory()->create(['user_id' => $stranger->id]);
        $session = $strangerTrainee->sessions()->create([
            'mode' => 'quiz',
            'title' => 'Чужая сессия',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/sessions/{$session->id}")->assertForbidden();
        $this->postJson("/api/v1/sessions/{$session->id}/answer", ['rating' => 3])->assertForbidden();
    }

    public function test_share_link_can_be_managed_from_the_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $url = $this->postJson('/api/v1/share')->assertOk()->json('share_url');

        $this->assertNotNull($url);
        $this->get($url)->assertOk();

        $this->deleteJson('/api/v1/share')->assertOk()->assertJsonPath('share_url', null);
        $this->get($url)->assertNotFound();
    }

    public function test_logout_revokes_only_the_current_device(): void
    {
        $user = User::factory()->create();
        $other = $user->createToken('tablet')->plainTextToken;
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        // В тестах приложение живёт между запросами и guard кэширует пользователя,
        // в продакшене каждый запрос поднимает его заново.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/me')->assertOk();
    }

    public function test_progress_is_shared_between_web_and_api(): void
    {
        $this->seedBank();
        $user = User::factory()->create(['password' => 'secret-password']);

        // Тренировка в вебе.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/sessions', ['mode' => 'quiz']);
        $session = $user->refresh()->trainee->sessions()->sole();
        $this->post(route('sessions.answer', $session), ['option' => 1, 'seconds' => 5]);
        $this->post('/logout');

        // Та же сессия видна мобильному клиенту.
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $session->id);

        $this->assertGreaterThan(0, $this->getJson('/api/v1/progress')->json('readiness'));
    }
}
