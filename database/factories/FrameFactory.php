<?php

namespace Database\Factories;

use App\Models\Frame;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Frame>
 */
class FrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sequence' => 1,
            'path' => 'projects/1/frames/'.fake()->uuid().'.jpg',
        ];
    }
}
