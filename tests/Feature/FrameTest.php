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

it('streams a frame image to its owner', function () {
    $project = Project::factory()->create();
    $path = "projects/{$project->id}/frames/a.jpg";
    Storage::put($path, 'jpg-bytes');
    $frame = Frame::factory()->for($project)->create(['path' => $path]);

    $this->withCookie('owner_token', $project->owner_token)
        ->get(route('projects.frames.image', [$project, $frame]))
        ->assertSuccessful();

    $this->withCookie('owner_token', ownerToken())
        ->get(route('projects.frames.image', [$project, $frame]))
        ->assertNotFound();
});

it('reorders a frame by swapping with its neighbor', function () {
    $project = Project::factory()->create();
    $frames = collect([1, 2, 3])->map(fn (int $sequence) => Frame::factory()
        ->for($project)
        ->create(['sequence' => $sequence]));

    $this->withCookie('owner_token', $project->owner_token)
        ->patch(route('projects.frames.move', [$project, $frames[1]]), [
            'direction' => 'earlier',
        ])
        ->assertRedirect();

    expect($frames[1]->refresh()->sequence)->toBe(1)
        ->and($frames[0]->refresh()->sequence)->toBe(2)
        ->and($frames[2]->refresh()->sequence)->toBe(3);
});

it('swaps across sequence gaps when moving later', function () {
    $project = Project::factory()->create();
    $first = Frame::factory()->for($project)->create(['sequence' => 1]);
    $gapped = Frame::factory()->for($project)->create(['sequence' => 5]);

    $this->withCookie('owner_token', $project->owner_token)
        ->patch(route('projects.frames.move', [$project, $first]), [
            'direction' => 'later',
        ])
        ->assertRedirect();

    expect($first->refresh()->sequence)->toBe(5)
        ->and($gapped->refresh()->sequence)->toBe(1);
});

it('leaves edge frames in place when moved outward', function () {
    $project = Project::factory()->create();
    $only = Frame::factory()->for($project)->create(['sequence' => 1]);

    $this->withCookie('owner_token', $project->owner_token)
        ->patch(route('projects.frames.move', [$project, $only]), [
            'direction' => 'earlier',
        ])
        ->assertRedirect();

    expect($only->refresh()->sequence)->toBe(1);
});

it('guards frame moves by owner and direction', function () {
    $frame = Frame::factory()->create();

    $this->withCookie('owner_token', ownerToken())
        ->patch(route('projects.frames.move', [$frame->project, $frame]), [
            'direction' => 'earlier',
        ])
        ->assertNotFound();

    $this->withCookie('owner_token', $frame->project->owner_token)
        ->patch(route('projects.frames.move', [$frame->project, $frame]), [
            'direction' => 'sideways',
        ])
        ->assertSessionHasErrors('direction');
});

it('cannot delete a frame through a different project', function () {
    $frame = Frame::factory()->create();
    $otherProject = Project::factory()->create(['owner_token' => $frame->project->owner_token]);

    $this->withCookie('owner_token', $frame->project->owner_token)
        ->delete(route('projects.frames.destroy', [$otherProject, $frame]))
        ->assertNotFound();
});
