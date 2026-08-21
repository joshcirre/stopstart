<?php

use App\Enums\VideoStatus;
use App\Events\VideoStatusUpdated;
use App\Jobs\GenerateVideo;
use App\Models\Frame;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('queues a render with the project\'s fps', function () {
    Bus::fake([GenerateVideo::class]);

    $project = Project::factory()->create(['fps' => 24]);
    Frame::factory()->for($project)->create();

    $this->withCookie('owner_token', $project->owner_token)
        ->post(route('projects.videos.store', $project))
        ->assertRedirect();

    $video = Video::sole();

    expect($video->status)->toBe(VideoStatus::Pending)
        ->and($video->fps)->toBe(24);

    Bus::assertDispatched(GenerateVideo::class, fn (GenerateVideo $job): bool => $job->video->is($video));
});

it('rejects a render without frames', function () {
    Bus::fake([GenerateVideo::class]);

    $project = Project::factory()->create();

    $this->withCookie('owner_token', $project->owner_token)
        ->post(route('projects.videos.store', $project))
        ->assertSessionHasErrors('frames');

    Bus::assertNotDispatched(GenerateVideo::class);
});

it('rejects a render while another is in flight', function () {
    Bus::fake([GenerateVideo::class]);

    $project = Project::factory()->create();
    Frame::factory()->for($project)->create();
    Video::factory()->for($project)->processing()->create();

    $this->withCookie('owner_token', $project->owner_token)
        ->post(route('projects.videos.store', $project))
        ->assertSessionHasErrors('video');

    Bus::assertNotDispatched(GenerateVideo::class);
});

it('renders frames into a stored video', function (callable $projectFactory, string $expectedScale) {
    Storage::fake();
    Event::fake([VideoStatusUpdated::class]);
    Process::fake(function (PendingProcess $process) {
        touch(end($process->command));

        return Process::result();
    });

    $project = $projectFactory();

    foreach ([1, 3, 7] as $sequence) {
        $path = "projects/{$project->id}/frames/{$sequence}.jpg";
        Storage::put($path, 'jpg-data');
        Frame::factory()->for($project)->create(['sequence' => $sequence, 'path' => $path]);
    }

    $video = Video::factory()->for($project)->create(['fps' => $project->fps]);

    (new GenerateVideo($video))->handle();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'ffmpeg'
        && in_array($expectedScale, $process->command, true)
        && in_array('-framerate', $process->command, true));

    $video->refresh();

    expect($video->status)->toBe(VideoStatus::Completed)
        ->and($video->path)->toBe("projects/{$project->id}/videos/video-{$video->id}.mp4");

    Storage::assertExists($video->path);
    Event::assertDispatchedTimes(VideoStatusUpdated::class, 2);
})->with([
    'landscape' => [fn () => Project::factory()->create(), 'scale=1920:1080'],
    'portrait' => [fn () => Project::factory()->portrait()->create(), 'scale=1080:1920'],
]);

it('marks the video failed when ffmpeg errors', function () {
    Storage::fake();
    Event::fake([VideoStatusUpdated::class]);
    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'encoder exploded')]);

    $project = Project::factory()->create();
    $path = "projects/{$project->id}/frames/1.jpg";
    Storage::put($path, 'jpg-data');
    Frame::factory()->for($project)->create(['path' => $path]);

    $video = Video::factory()->for($project)->create();

    expect(fn () => GenerateVideo::dispatch($video))->toThrow(RuntimeException::class);

    $video->refresh();

    expect($video->status)->toBe(VideoStatus::Failed)
        ->and($video->error)->toContain('encoder exploded');
});

it('downloads a completed video for its owner', function () {
    Storage::fake();

    $video = Video::factory()->completed()->create();
    Storage::put($video->path, 'mp4-data');

    $this->withCookie('owner_token', $video->project->owner_token)
        ->get(route('videos.download', $video))
        ->assertSuccessful()
        ->assertDownload();
});

it('blocks video downloads for other owners and unfinished renders', function () {
    Storage::fake();

    $completed = Video::factory()->completed()->create();
    Storage::put($completed->path, 'mp4-data');

    $this->withCookie('owner_token', ownerToken())
        ->get(route('videos.download', $completed))
        ->assertNotFound();

    $pending = Video::factory()->create();

    $this->withCookie('owner_token', $pending->project->owner_token)
        ->get(route('videos.download', $pending))
        ->assertNotFound();
});
