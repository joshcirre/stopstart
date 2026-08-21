<?php

namespace Database\Factories;

use App\Enums\Orientation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'orientation' => Orientation::Landscape,
            'fps' => 12,
            'owner_token' => (string) Str::uuid7(),
            'remote_token' => Str::random(40),
        ];
    }

    public function portrait(): static
    {
        return $this->state(fn (array $attributes) => [
            'orientation' => Orientation::Portrait,
        ]);
    }
}
