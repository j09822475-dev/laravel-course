<?php

namespace Tests\Feature;

use App\Models\Trainee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_gets_a_profile(): void
    {
        $this->post('/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $user = User::sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Иван', $user->name);
        $this->assertNotNull($user->trainee);
        $this->assertNotSame('secret-password', $user->password, 'Пароль обязан храниться хешированным');
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_registration_requires_confirmation_and_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Иван',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'other-password',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('dashboard'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_without_revealing_the_account(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'Неверный адрес или пароль.',
            session('errors')->first('email'),
            'Сообщение не должно отличаться для существующего и несуществующего адреса',
        );
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login:victim@example.com|127.0.0.1');

        User::factory()->create(['email' => 'victim@example.com', 'password' => 'secret-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['email' => 'victim@example.com', 'password' => 'guess'.$i]);
        }

        $this->from('/login')
            ->post('/login', ['email' => 'victim@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        // Перебор блокируется даже тогда, когда пароль наконец угадан верно.
        $this->assertGuest();
    }

    public function test_guest_pages_redirect_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect();
        $this->get('/register')->assertRedirect();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_guest_keeps_working_without_an_account(): void
    {
        $this->get('/')->assertOk();

        $trainee = Trainee::sole();

        $this->assertTrue($trainee->isGuest());
        $this->assertFalse($trainee->isShared());
    }
}
