<?php

use App\Events\FrameCaptured;
use App\Models\Frame;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Event::fake([FrameCaptured::class]);
});

it('stores uploaded frames with incrementing sequences', function () {
    $project = Project::factory()->create();

    $first = $this->withCookie('owner_token', $project->owner_token)
        ->post(route('projects.frames.store', $project), [
            'image' => UploadedFile::fake()->image('frame.jpg', 1920, 1080),
        ]);

    $first->assertCreated()
        ->assertJsonPath('frame.sequence', 1)
        ->assertJsonPath('frameCount', 1);

    $second = $this->withCookie('owner_token', $project->owner_token)
        ->post(route('projects.frames.store', $project), [
            'image' => UploadedFile::fake()->image('frame.jpg', 1920, 1080),
        ]);

    $second->assertCreated()->assertJsonPath('frame.sequence', 2);

    Storage::assertExists(Frame::firstOrFail()->path);
    Event::assertDispatchedTimes(FrameCaptured::class, 2);
});

it('rejects invalid frame uploads', function (callable $file, ?string $orientationState = null) {
    $project = $orientationState === 'portrait'
        ? Project::factory()->portrait()->create()
        : Project::factory()->create();

    $this->withCookie('owner_token', $project->owner_token)
        ->postJson(route('projects.frames.store', $project), ['image' => $file()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    Event::assertNotDispatched(FrameCaptured::class);
})->with([
    'png file' => [fn () => UploadedFile::fake()->image('frame.png', 1920, 1080)],
    'oversize file' => [fn () => UploadedFile::fake()->create('frame.jpg', 11 * 1024, 'image/jpeg')],
    'wrong dimensions for landscape' => [fn () => UploadedFile::fake()->image('frame.jpg', 1080, 1920)],
    'wrong dimensions for portrait' => [fn () => UploadedFile::fake()->image('frame.jpg', 1920, 1080), 'portrait'],
]);

it('blocks uploads to another owner\'s project', function () {
    $project = Project::factory()->create();

    $this->withCookie('owner_token', ownerToken())
        ->post(route('projects.frames.store', $project), [
            'image' => UploadedFile::fake()->image('frame.jpg', 1920, 1080),
        ])
        ->assertNotFound();
});

it('deletes a frame and leaves sequence gaps intact', function () {
    $project = Project::factory()->create();

    [$first, $second, $third] = collect([1, 2, 3])
        ->map(function (int $sequence) use ($project) {
            $path = "projects/{$project->id}/frames/{$sequence}.jpg";
            Storage::put($path, 'jpg');

            return Frame::factory()->for($project)->create(['sequence' => $sequence, 'path' => $path]);
        })
        ->all();

    $this->withCookie('owner_token', $project->owner_token)
        ->delete(route('projects.frames.destroy', [$project, $second]))
        ->assertRedirect();

    Storage::assertMissing($second->path);

    expect($project->frames()->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 3]);
});

it('cannot delete a frame through a different project', function () {
    $frame = Frame::factory()->create();
    $otherProject = Project::factory()->create(['owner_token' => $frame->project->owner_token]);

    $this->withCookie('owner_token', $frame->project->owner_token)
        ->delete(route('projects.frames.destroy', [$otherProject, $frame]))
        ->assertNotFound();
});
