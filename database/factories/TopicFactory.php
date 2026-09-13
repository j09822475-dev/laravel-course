<?php

namespace Database\Factories;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Topic> */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'name' => Str::ucfirst($name),
            'area' => fake()->randomElement(['backend', 'frontend', 'database', 'architecture', 'devops', 'soft']),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(1, 20),
        ];
    }
}
