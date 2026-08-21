<?php

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

it('deletes a project along with its stored files', function () {
    Storage::fake();

    $project = Project::factory()->create();
    Frame::factory()->for($project)->create(['path' => "projects/{$project->id}/frames/a.jpg"]);
    Video::factory()->for($project)->completed()->create(['path' => "projects/{$project->id}/videos/v.mp4"]);

    Storage::put("projects/{$project->id}/frames/a.jpg", 'jpg');
    Storage::put("projects/{$project->id}/videos/v.mp4", 'mp4');

    $this->withCookie('owner_token', $project->owner_token)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    Storage::assertMissing("projects/{$project->id}/frames/a.jpg");
    Storage::assertMissing("projects/{$project->id}/videos/v.mp4");

    $this->assertDatabaseEmpty('projects');
    $this->assertDatabaseEmpty('frames');
    $this->assertDatabaseEmpty('videos');
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
