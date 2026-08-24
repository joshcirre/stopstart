<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
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
            'status' => VideoStatus::Pending,
            'fps' => 12,
            'path' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Processing,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Completed,
            'path' => 'projects/1/videos/'.fake()->uuid().'.mp4',
        ]);
    }

    public function export(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Completed,
            'has_audio' => true,
            'path' => 'projects/1/videos/'.fake()->uuid().'.mp4',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Failed,
            'error' => 'ffmpeg failed',
        ]);
    }
}
