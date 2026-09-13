<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Trainee> */
class TraineeFactory extends Factory
{
    protected $model = Trainee::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'target_level' => Difficulty::Middle,
        ];
    }
}
