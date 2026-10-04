<?php

namespace Tests\Feature;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\Question;
use App\Models\Topic;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Публичная ссылка на прогресс: включается осознанно, не раскрывает личные
 * данные и мгновенно перестаёт работать после отзыва.
 */
class SharingTest extends TestCase
{
    use RefreshDatabase;

    protected function traineeWithProgress(User $user): Trainee
    {
        $trainee = Trainee::factory()->create(['user_id' => $user->id, 'name' => 'Иван']);
        $topic = Topic::factory()->create(['name' => 'Ядро Laravel']);

        $session = $trainee->sessions()->create([
            'mode' => SessionMode::Screening,
            'title' => 'Скрининг (30 минут) · Middle',
            'status' => SessionStatus::Completed,
            'score' => 72,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $session->items()->create([
            'question_id' => Question::factory()->for($topic)->create([
                'prompt' => 'Секретный текст вопроса',
                'answer' => 'Секретный эталонный ответ',
            ])->id,
            'position' => 1,
            'phase' => 'tech',
            'answer_text' => 'Мой личный черновик ответа',
            'self_rating' => 2,
            'seconds_spent' => 60,
            'answered_at' => now(),
        ]);

        return $trainee;
    }

    public function test_owner_can_create_and_open_a_share_link(): void
    {
        $user = User::factory()->create();
        $trainee = $this->traineeWithProgress($user);

        $this->actingAs($user)->post('/share')->assertRedirect(route('progress'));

        $token = $trainee->refresh()->share_token;

        $this->assertNotNull($token);
        $this->assertSame(32, mb_strlen($token), 'Токен должен быть длинным и непредсказуемым');

        $this->get("/p/{$token}")
            ->assertOk()
            ->assertSee('Иван')
            ->assertSee('Ядро Laravel')
            ->assertSee('72%');
    }

    public function test_public_page_hides_personal_data_and_answers(): void
    {
        $user = User::factory()->create(['email' => 'private@example.com']);
        $trainee = $this->traineeWithProgress($user);
        $token = $trainee->startSharing();

        $response = $this->get("/p/{$token}");

        $response->assertOk()
            ->assertDontSee('private@example.com')
            ->assertDontSee('Мой личный черновик ответа')
            ->assertDontSee('Секретный эталонный ответ')
            ->assertDontSee('Секретный текст вопроса');
    }

    public function test_progress_is_private_until_sharing_is_enabled(): void
    {
        $user = User::factory()->create();
        $this->traineeWithProgress($user);

        $this->get('/p/no-such-token')->assertNotFound();
    }

    public function test_revoking_the_link_breaks_the_old_address(): void
    {
        $user = User::factory()->create();
        $trainee = $this->traineeWithProgress($user);
        $token = $trainee->startSharing();

        $this->actingAs($user)->delete('/share')->assertRedirect(route('progress'));

        $this->get("/p/{$token}")->assertNotFound();
        $this->assertNull($trainee->refresh()->share_token);
    }

    public function test_regenerating_the_link_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $trainee = $this->traineeWithProgress($user);
        $old = $trainee->startSharing();

        $this->actingAs($user)->put('/share')->assertRedirect(route('progress'));

        $new = $trainee->refresh()->share_token;

        $this->assertNotSame($old, $new);
        $this->get("/p/{$old}")->assertNotFound();
        $this->get("/p/{$new}")->assertOk();
    }

    public function test_guest_cannot_create_a_share_link(): void
    {
        $this->get('/');

        $this->post('/share')->assertRedirect(route('login'));
        $this->assertNull(Trainee::sole()->share_token);
    }

    public function test_share_link_is_visible_on_the_progress_page(): void
    {
        $user = User::factory()->create();
        $trainee = $this->traineeWithProgress($user);
        $token = $trainee->startSharing();

        $this->actingAs($user)->get('/progress')
            ->assertOk()
            ->assertSee(url("/p/{$token}"));
    }
}
