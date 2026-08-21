<?php

use App\Events\RemoteCommandReceived;
use App\Models\Project;
use Illuminate\Support\Facades\Event;

it('loads the remote page by token without an owner cookie', function () {
    $project = Project::factory()->create();

    $this->get(route('remote.show', $project))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('remote/Show')
            ->where('projectName', $project->name)
            ->where('remoteToken', $project->remote_token)
        );
});

it('returns 404 for an unknown remote token', function () {
    $this->get('/remote/definitely-not-a-real-token')->assertNotFound();
});

it('broadcasts a capture command', function () {
    Event::fake([RemoteCommandReceived::class]);

    $project = Project::factory()->create();

    $this->postJson(route('remote.command', $project), ['command' => 'capture'])
        ->assertSuccessful()
        ->assertJson(['ok' => true]);

    Event::assertDispatched(RemoteCommandReceived::class, fn (RemoteCommandReceived $event): bool => $event->project->is($project)
        && $event->command === 'capture'
        && $event->intervalSeconds === null);
});

it('broadcasts an interval start command with the interval', function () {
    Event::fake([RemoteCommandReceived::class]);

    $project = Project::factory()->create();

    $this->postJson(route('remote.command', $project), [
        'command' => 'interval-start',
        'intervalSeconds' => 5,
    ])->assertSuccessful();

    Event::assertDispatched(RemoteCommandReceived::class, fn (RemoteCommandReceived $event): bool => $event->command === 'interval-start'
        && $event->intervalSeconds === 5);
});

it('requires an interval when starting interval mode', function () {
    Event::fake([RemoteCommandReceived::class]);

    $this->postJson(route('remote.command', Project::factory()->create()), [
        'command' => 'interval-start',
    ])->assertUnprocessable()->assertJsonValidationErrors('intervalSeconds');

    Event::assertNotDispatched(RemoteCommandReceived::class);
});

it('rejects unknown commands', function () {
    $this->postJson(route('remote.command', Project::factory()->create()), [
        'command' => 'self-destruct',
    ])->assertUnprocessable()->assertJsonValidationErrors('command');
});

it('throttles remote commands per project', function () {
    Event::fake([RemoteCommandReceived::class]);

    $project = Project::factory()->create();

    foreach (range(1, 120) as $attempt) {
        $this->postJson(route('remote.command', $project), ['command' => 'capture'])
            ->assertSuccessful();
    }

    $this->postJson(route('remote.command', $project), ['command' => 'capture'])
        ->assertTooManyRequests();
});
