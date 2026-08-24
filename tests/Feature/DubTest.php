<?php

use App\Events\VideoStatusUpdated;
use App\Models\AudioLayer;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('loads the dub workspace by token with the master video and layers', function () {
    $project = Project::factory()->create();
    $master = Video::factory()->for($project)->completed()->create();
    AudioLayer::factory()->for($project)->create(['offset' => 2]);
    AudioLayer::factory()->for($project)->create(['offset' => 0.5, 'name' => 'Layer 2']);

    $this->get(route('remote.dub', $project))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('remote/Dub')
            ->where('video.id', $master->id)
            ->where('video.streamUrl', route('remote.videos.stream', [$project, $master]))
            ->has('layers', 2)
            ->where('layers.0.offset', 0.5)
            ->where('export', null)
        );
});

it('offers no video when only an export exists', function () {
    $project = Project::factory()->create();
    Video::factory()->for($project)->export()->create();

    $this->get(route('remote.dub', $project))
        ->assertInertia(fn ($page) => $page
            ->where('video', null)
            ->whereNot('export', null)
        );
});

it('streams a completed master video by token', function () {
    $video = Video::factory()->completed()->create();
    Storage::put($video->path, 'mp4-bytes');

    $this->get(route('remote.videos.stream', [$video->project, $video]))
        ->assertSuccessful();
});

it('refuses to stream unfinished or foreign videos', function () {
    $pending = Video::factory()->create();

    $this->get(route('remote.videos.stream', [$pending->project, $pending]))
        ->assertNotFound();

    $completed = Video::factory()->completed()->create();
    $otherProject = Project::factory()->create();

    $this->get(route('remote.videos.stream', [$otherProject, $completed]))
        ->assertNotFound();
});

it('stores a dub export as a completed video with audio', function () {
    Event::fake([VideoStatusUpdated::class]);

    $project = Project::factory()->create(['fps' => 6]);
    Video::factory()->for($project)->completed()->create();

    $response = $this->postJson(route('remote.export', $project), [
        'video' => UploadedFile::fake()->create('dub.mp4', 512, 'video/mp4'),
    ]);

    $response->assertCreated()->assertJsonPath('video.status', 'completed');

    $export = $project->latestExport;

    expect($export)->not->toBeNull()
        ->and($export->has_audio)->toBeTrue()
        ->and($export->fps)->toBe(6);

    Storage::assertExists($export->path);
    Event::assertDispatched(VideoStatusUpdated::class);

    expect($project->latestMasterVideo->has_audio)->toBeFalse();
});

it('rejects invalid export uploads', function () {
    $project = Project::factory()->create();

    $this->postJson(route('remote.export', $project), [
        'video' => UploadedFile::fake()->create('dub.txt', 10, 'text/plain'),
    ])->assertUnprocessable();
});
