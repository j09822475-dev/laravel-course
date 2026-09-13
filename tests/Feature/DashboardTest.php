<?php

namespace Tests\Feature;

use App\Models\Trainee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_creates_trainee_profile_for_new_visitor(): void
    {
        $this->assertSame(0, Trainee::count());

        $this->get('/')
            ->assertOk()
            ->assertSee('Выберите формат тренировки');

        $this->assertSame(1, Trainee::count());
    }

    public function test_profile_can_be_updated(): void
    {
        $this->get('/');

        $this->put('/profile', ['name' => 'Иван', 'target_level' => 'senior'])
            ->assertRedirect(route('dashboard'));

        $trainee = Trainee::sole();

        $this->assertSame('Иван', $trainee->name);
        $this->assertSame('senior', $trainee->target_level->value);
    }

    public function test_profile_rejects_unknown_level(): void
    {
        $this->get('/');

        $this->from('/')
            ->put('/profile', ['name' => 'Иван', 'target_level' => 'god'])
            ->assertSessionHasErrors('target_level');
    }
}
