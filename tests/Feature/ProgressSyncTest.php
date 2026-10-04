<?php

namespace Tests\Feature;

use App\Enums\SelfRating;
use App\Models\Question;
use App\Models\ReviewCard;
use App\Models\Topic;
use App\Models\Trainee;
use App\Models\User;
use App\Services\ProgressSync;
use App\Services\SpacedRepetition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Синхронизация прогресса: результаты живут в аккаунте, а не в браузере,
 * и гостевая тренировка не пропадает при регистрации или входе.
 */
class ProgressSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBank(): Topic
    {
        $topic = Topic::factory()->create();
        Question::factory()->count(12)->for($topic)->quiz()->create();

        return $topic;
    }

    public function test_guest_progress_moves_into_the_new_account(): void
    {
        $this->seedBank();

        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz']);

        $guest = Trainee::sole();
        $this->post(route('sessions.answer', $guest->sessions()->sole()), ['option' => 0, 'seconds' => 5]);

        $this->post('/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $trainee = User::sole()->trainee;

        $this->assertSame($guest->id, $trainee->id, 'Гостевой профиль должен стать профилем аккаунта');
        $this->assertSame(1, $trainee->sessions()->count());
        $this->assertSame(1, ReviewCard::where('trainee_id', $trainee->id)->count());
    }

    public function test_adopted_guest_profile_takes_the_account_name(): void
    {
        $this->get('/');

        $this->post('/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertSame('Иван', User::sole()->trainee->name);
    }

    public function test_custom_profile_name_survives_registration(): void
    {
        $this->get('/');
        $this->put('/profile', ['name' => 'Ваня-джун', 'target_level' => 'junior']);

        $this->post('/register', [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertSame('Ваня-джун', User::sole()->trainee->name, 'Имя, заданное вручную, перезаписывать нельзя');
    }

    public function test_second_device_sees_the_same_progress(): void
    {
        $this->seedBank();

        $user = User::factory()->create(['password' => 'secret-password']);

        // Первое устройство: вход и одна тренировка.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/sessions', ['mode' => 'quiz']);
        $session = $user->refresh()->trainee->sessions()->sole();
        $this->post(route('sessions.answer', $session), ['option' => 0, 'seconds' => 5]);
        $this->post('/logout');

        // Второе устройство — чистая сессия браузера.
        $this->flushSession();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->get('/progress')
            ->assertOk()
            ->assertSee('Владение темами');

        $this->assertSame(
            1,
            $user->refresh()->trainee->sessions()->count(),
            'Прогресс аккаунта должен быть виден с другого устройства',
        );
    }

    public function test_guest_progress_merges_into_an_existing_account(): void
    {
        $topic = $this->seedBank();
        $user = User::factory()->create(['password' => 'secret-password']);

        // Аккаунт уже имеет профиль с одной сессией.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/sessions', ['mode' => 'quiz']);
        $accountTrainee = $user->refresh()->trainee;
        $this->post('/logout');

        // Гость на этом же устройстве успел потренироваться до входа.
        $this->flushSession();
        $this->get('/');
        $this->post('/sessions', ['mode' => 'quiz']);
        $guest = Trainee::whereNull('user_id')->sole();
        $this->post(route('sessions.answer', $guest->sessions()->sole()), ['option' => 0, 'seconds' => 5]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->assertSame(2, $accountTrainee->refresh()->sessions()->count(), 'Обе сессии должны оказаться в аккаунте');
        $this->assertDatabaseMissing('trainees', ['id' => $guest->id]);
        $this->assertSame(1, Trainee::count());
    }

    public function test_merge_keeps_the_later_review_schedule(): void
    {
        $question = Question::factory()->create();
        $scheduler = new SpacedRepetition;
        $sync = new ProgressSync;

        $user = User::factory()->create();
        $account = Trainee::factory()->create(['user_id' => $user->id]);
        $guest = Trainee::factory()->create(['user_id' => null]);

        // В аккаунте вопрос провален давно, на устройстве — отвечен уверенно сегодня.
        $scheduler->record($account, $question, SelfRating::Failed, Carbon::parse('2026-01-01'));
        $scheduler->record($guest, $question, SelfRating::Confident, Carbon::parse('2026-03-01'));

        $sync->merge($guest, $account);

        $card = ReviewCard::sole();

        $this->assertSame($account->id, $card->trainee_id);
        $this->assertSame(SelfRating::Confident->value, $card->last_rating);
        $this->assertSame('2026-03-01', $card->last_reviewed_at->toDateString());
    }

    public function test_logging_out_does_not_hand_the_account_profile_to_a_guest(): void
    {
        $this->seedBank();
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $accountTrainee = $user->refresh()->trainee;

        $this->post('/logout');
        $this->get('/')->assertOk();

        $guest = Trainee::whereNull('user_id')->sole();

        $this->assertNotSame($accountTrainee->id, $guest->id);
    }

    public function test_account_profile_is_never_reassigned_to_another_user(): void
    {
        $sync = new ProgressSync;

        $owner = User::factory()->create();
        $ownerTrainee = Trainee::factory()->create(['user_id' => $owner->id]);

        $stranger = User::factory()->create();
        $strangerTrainee = $sync->attach($stranger, $ownerTrainee);

        $this->assertNotSame($ownerTrainee->id, $strangerTrainee->id);
        $this->assertSame($owner->id, $ownerTrainee->refresh()->user_id);
    }
}
