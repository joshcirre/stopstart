<?php

use App\Events\AudioLayerUpdated;
use App\Models\AudioLayer;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Event::fake([AudioLayerUpdated::class]);
});

it('stores a voice layer by remote token without an owner cookie', function () {
    $project = Project::factory()->create();

    $response = $this->postJson(route('remote.layers.store', $project), [
        'audio' => UploadedFile::fake()->create('layer.webm', 100, 'audio/webm'),
        'name' => 'Take 1',
        'offset' => 1.5,
        'duration' => 3.2,
    ]);

    $response->assertCreated()
        ->assertJsonPath('layer.name', 'Take 1')
        ->assertJsonPath('layer.offset', 1.5)
        ->assertJsonPath('layerCount', 1);

    $layer = AudioLayer::sole();

    expect($layer->project_id)->toBe($project->id)
        ->and($layer->volume)->toBe(1.0)
        ->and($layer->duration)->toBe(3.2);

    Storage::assertExists($layer->path);
    Event::assertDispatched(AudioLayerUpdated::class);
});

it('rejects invalid layer uploads', function (array $payload) {
    $project = Project::factory()->create();

    $overrides = array_map(
        fn ($value) => $value instanceof Closure ? $value() : $value,
        $payload,
    );

    $this->postJson(route('remote.layers.store', $project), [
        'audio' => UploadedFile::fake()->create('layer.webm', 100, 'audio/webm'),
        'name' => 'Take 1',
        'offset' => 0,
        'duration' => 2,
        ...$overrides,
    ])->assertUnprocessable();

    Event::assertNotDispatched(AudioLayerUpdated::class);
})->with([
    'text file' => [['audio' => fn () => UploadedFile::fake()->create('layer.txt', 10, 'text/plain')]],
    'oversize file' => [['audio' => fn () => UploadedFile::fake()->create('layer.webm', 26 * 1024, 'audio/webm')]],
    'negative offset' => [['offset' => -1]],
    'missing duration' => [['duration' => null]],
]);

it('updates layer name, offset, and volume by token', function () {
    $layer = AudioLayer::factory()->create();

    $this->patchJson(
        route('remote.layers.update', [$layer->project, $layer]),
        ['name' => 'Villain voice', 'offset' => 4.25, 'volume' => 0.8],
    )->assertRedirect();

    $layer->refresh();

    expect($layer->name)->toBe('Villain voice')
        ->and($layer->offset)->toBe(4.25)
        ->and($layer->volume)->toBe(0.8);

    Event::assertDispatched(AudioLayerUpdated::class);
});

it('rejects volume above 1.5', function () {
    $layer = AudioLayer::factory()->create();

    $this->patchJson(
        route('remote.layers.update', [$layer->project, $layer]),
        ['volume' => 2.0],
    )->assertUnprocessable();
});

it('deletes a layer and its stored file', function () {
    $layer = AudioLayer::factory()->create();
    Storage::put($layer->path, 'audio');

    $this->deleteJson(route('remote.layers.destroy', [$layer->project, $layer]))
        ->assertRedirect();

    Storage::assertMissing($layer->path);
    $this->assertDatabaseEmpty('audio_layers');
    Event::assertDispatched(AudioLayerUpdated::class);
});

it('streams layer audio by token', function () {
    $layer = AudioLayer::factory()->create();
    Storage::put($layer->path, 'audio-bytes');

    $this->get(route('remote.layers.audio', [$layer->project, $layer]))
        ->assertSuccessful();
});

it('blocks access to a layer through another project\'s token', function (string $routeName, string $method) {
    $layer = AudioLayer::factory()->create();
    $otherProject = Project::factory()->create();

    $this->{$method}(route($routeName, [$otherProject, $layer]))
        ->assertNotFound();
})->with([
    'update' => ['remote.layers.update', 'patchJson'],
    'destroy' => ['remote.layers.destroy', 'deleteJson'],
    'audio' => ['remote.layers.audio', 'getJson'],
]);

it('throttles layer uploads per project', function () {
    $project = Project::factory()->create();

    foreach (range(1, 30) as $attempt) {
        $this->postJson(route('remote.layers.store', $project), [
            'audio' => UploadedFile::fake()->create('layer.webm', 10, 'audio/webm'),
            'name' => "Take {$attempt}",
            'offset' => 0,
            'duration' => 1,
        ])->assertCreated();
    }

    $this->postJson(route('remote.layers.store', $project), [
        'audio' => UploadedFile::fake()->create('layer.webm', 10, 'audio/webm'),
        'name' => 'One too many',
        'offset' => 0,
        'duration' => 1,
    ])->assertTooManyRequests();
});
