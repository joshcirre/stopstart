<?php

namespace Database\Factories;

use App\Models\AudioLayer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioLayer>
 */
class AudioLayerFactory extends Factory
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
            'name' => 'Layer 1',
            'path' => 'projects/1/audio-layers/'.fake()->uuid().'.webm',
            'mime_type' => 'audio/webm',
            'offset' => 0,
            'volume' => 1,
            'duration' => 2.5,
        ];
    }
}
