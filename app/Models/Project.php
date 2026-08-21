<?php

namespace App\Models;

use App\Enums\Orientation;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property Orientation $orientation
 * @property int $fps
 * @property string $owner_token
 * @property string $remote_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Frame> $frames
 * @property-read Collection<int, Video> $videos
 * @property-read Frame|null $latestFrame
 * @property-read Video|null $latestVideo
 * @property-read int|null $frames_count
 */
#[Fillable(['name', 'orientation', 'fps', 'owner_token', 'remote_token'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $attributes = [
        'fps' => 12,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => Orientation::class,
            'fps' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->remote_token ??= Str::random(40);
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, string $ownerToken): void
    {
        $query->where('owner_token', $ownerToken);
    }

    /**
     * @return HasMany<Frame, $this>
     */
    public function frames(): HasMany
    {
        return $this->hasMany(Frame::class);
    }

    /**
     * @return HasMany<Video, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * @return HasOne<Frame, $this>
     */
    public function latestFrame(): HasOne
    {
        return $this->hasOne(Frame::class)->latestOfMany('sequence');
    }

    /**
     * @return HasOne<Video, $this>
     */
    public function latestVideo(): HasOne
    {
        return $this->hasOne(Video::class)->latestOfMany();
    }

    public function channelName(): string
    {
        return 'project.'.$this->remote_token;
    }

    public function storageDirectory(): string
    {
        return "projects/{$this->id}";
    }
}
