<?php

namespace App\Models;

use Database\Factories\AudioLayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recorded voice layer positioned on the project's video timeline.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $path
 * @property string $mime_type
 * @property float $offset
 * @property float $volume
 * @property float $duration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['project_id', 'name', 'path', 'mime_type', 'offset', 'volume', 'duration'])]
class AudioLayer extends Model
{
    /** @use HasFactory<AudioLayerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offset' => 'float',
            'volume' => 'float',
            'duration' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
