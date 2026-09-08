<?php

use App\Models\AudioLayer;
use App\Models\Frame;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

it('creates a project owned by the cookie holder', function () {
    $token = ownerToken();

    $response = $this->withCookie('owner_token', $token)->post(route('projects.store'), [
        'name' => 'Lego Walk',
        'orientation' => 'portrait',
        'fps' => 24,
    ]);

    $project = Project::sole();

    $response->assertRedirect(route('projects.capture', $project));

    expect($project->owner_token)->toBe($token)
        ->and($project->remote_token)->toHaveLength(40)
        ->and($project->fps)->toBe(24);
});

it('defaults fps to 12 when omitted', function () {
    $this->withCookie('owner_token', ownerToken())->post(route('projects.store'), [
        'name' => 'Clay Jump',
        'orientation' => 'landscape',
    ]);

    expect(Project::sole()->fps)->toBe(12);
});

it('validates the project payload', function (array $payload, string $errorField) {
    $this->withCookie('owner_token', ownerToken())
        ->post(route('projects.store'), $payload)
        ->assertSessionHasErrors($errorField);
})->with([
    'missing name' => [['orientation' => 'landscape'], 'name'],
    'bad orientation' => [['name' => 'X', 'orientation' => 'diagonal'], 'orientation'],
    'fps out of range' => [['name' => 'X', 'orientation' => 'landscape', 'fps' => 61], 'fps'],
]);

it('lists only the cookie owner\'s projects', function () {
    $mine = ownerToken();

    Project::factory()->count(2)->create(['owner_token' => $mine]);
    Project::factory()->create();

    $this->withCookie('owner_token', $mine)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->has('projects', 2)
        );
});

it('blocks access to another owner\'s project', function (string $routeName, string $method) {
    $project = Project::factory()->create();

    $this->withCookie('owner_token', ownerToken())
        ->{$method}(route($routeName, $project))
        ->assertNotFound();
})->with([
    'show' => ['projects.show', 'get'],
    'capture' => ['projects.capture', 'get'],
    'destroy' => ['projects.destroy', 'delete'],
]);

it('updates the project fps for later renders', function () {
    $project = Project::factory()->create(['fps' => 12]);

    $this->withCookie('owner_token', $project->owner_token)
        ->patch(route('projects.update', $project), ['fps' => 6])
        ->assertRedirect();

    expect($project->refresh()->fps)->toBe(6);
});

it('validates and guards fps updates', function () {
    $project = Project::factory()->create(['fps' => 12]);

    $this->withCookie('owner_token', $project->owner_token)
        ->patch(route('projects.update', $project), ['fps' => 61])
        ->assertSessionHasErrors('fps');

    $this->withCookie('owner_token', ownerToken())
        ->patch(route('projects.update', $project), ['fps' => 6])
        ->assertNotFound();

    expect($project->refresh()->fps)->toBe(12);
});

it('deletes a project along with its stored files', function () {
    Storage::fake();

    $project = Project::factory()->create();
    Frame::factory()->for($project)->create(['path' => "projects/{$project->id}/frames/a.jpg"]);
    Video::factory()->for($project)->completed()->create(['path' => "projects/{$project->id}/videos/v.mp4"]);
    AudioLayer::factory()->for($project)->create(['path' => "projects/{$project->id}/audio-layers/l.webm"]);

    Storage::put("projects/{$project->id}/frames/a.jpg", 'jpg');
    Storage::put("projects/{$project->id}/videos/v.mp4", 'mp4');
    Storage::put("projects/{$project->id}/audio-layers/l.webm", 'audio');

    $this->withCookie('owner_token', $project->owner_token)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    Storage::assertMissing("projects/{$project->id}/frames/a.jpg");
    Storage::assertMissing("projects/{$project->id}/videos/v.mp4");
    Storage::assertMissing("projects/{$project->id}/audio-layers/l.webm");

    $this->assertDatabaseEmpty('projects');
    $this->assertDatabaseEmpty('frames');
    $this->assertDatabaseEmpty('videos');
    $this->assertDatabaseEmpty('audio_layers');
});

it('shares the remote pairing url and channel data on the capture page', function () {
    $project = Project::factory()->create();

    $this->withCookie('owner_token', $project->owner_token)
        ->get(route('projects.capture', $project))
        ->assertInertia(fn ($page) => $page
            ->component('projects/Capture')
            ->where('remoteUrl', route('remote.show', $project))
            ->where('project.remoteToken', $project->remote_token)
            ->where('project.width', 1920)
            ->where('project.height', 1080)
        );
});

it('keeps every master render in the show payload, tagged with its own fps', function () {
    $project = Project::factory()->create(['fps' => 6]);

    $twelve = Video::factory()->for($project)->completed()->create(['fps' => 12]);
    $six = Video::factory()->for($project)->completed()->create(['fps' => 6]);
    $export = Video::factory()->for($project)->export()->create(['fps' => 6]);

    $this->withCookie('owner_token', $project->owner_token)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            // Newest first, and the export is not a master render.
            ->has('renders', 2)
            ->where('renders.0.id', $six->id)
            ->where('renders.0.fps', 6)
            ->where('renders.1.id', $twelve->id)
            ->where('renders.1.fps', 12)
            ->where('video.id', $six->id)
            ->where('video.fps', 6)
            ->where('export.id', $export->id)
            ->etc()
        );
});

it('sends every frame to the capture page so any of them can anchor a resume', function () {
    $project = Project::factory()->create();

    foreach (range(1, 25) as $sequence) {
        Frame::factory()->for($project)->create(['sequence' => $sequence]);
    }

    $this->withCookie('owner_token', $project->owner_token)
        ->get(route('projects.capture', $project))
        ->assertInertia(fn ($page) => $page
            ->component('projects/Capture')
            ->has('frames', 25)
            ->where('frames.0.sequence', 1)
            ->where('frames.24.sequence', 25)
            ->etc()
        );
});
