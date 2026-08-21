<?php

use App\Events\FrameCaptured;
use App\Events\RemoteCommandReceived;
use App\Events\VideoStatusUpdated;
use App\Models\Frame;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

it('broadcasts remote commands on the project token channel', function () {
    $project = Project::factory()->create();

    $event = new RemoteCommandReceived($project, 'interval-start', 5);

    expect($event->broadcastOn()[0]->name)->toBe('project.'.$project->remote_token)
        ->and($event->broadcastAs())->toBe('remote.command')
        ->and($event->broadcastWith())->toBe([
            'command' => 'interval-start',
            'intervalSeconds' => 5,
        ]);
});

it('broadcasts captured frames with count and thumbnail', function () {
    Storage::fake();

    $frame = Frame::factory()->create();

    $event = new FrameCaptured($frame);
    $payload = $event->broadcastWith();

    expect($event->broadcastOn()[0]->name)->toBe($frame->project->channelName())
        ->and($event->broadcastAs())->toBe('frame.captured')
        ->and($payload['frameId'])->toBe($frame->id)
        ->and($payload['sequence'])->toBe($frame->sequence)
        ->and($payload['frameCount'])->toBe(1)
        ->and($payload['thumbnailUrl'])->toBeString();
});

it('broadcasts video status with urls only when completed', function () {
    Storage::fake();

    $completed = Video::factory()->completed()->create();
    Storage::put($completed->path, 'mp4');

    $payload = (new VideoStatusUpdated($completed))->broadcastWith();

    expect($payload['status'])->toBe('completed')
        ->and($payload['url'])->toBeString()
        ->and($payload['downloadUrl'])->toBe(route('videos.download', $completed));

    $failed = Video::factory()->failed()->create();

    $payload = (new VideoStatusUpdated($failed))->broadcastWith();

    expect($payload['status'])->toBe('failed')
        ->and($payload['url'])->toBeNull()
        ->and($payload['downloadUrl'])->toBeNull()
        ->and($payload['error'])->toBe('ffmpeg failed');
});
