<?php

namespace App\Http\Controllers;

use App\Events\AudioLayerUpdated;
use App\Http\Requests\StoreAudioLayerRequest;
use App\Http\Requests\UpdateAudioLayerRequest;
use App\Models\AudioLayer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Voice layers for the dub workspace. Like the remote routes, these are
 * authorized by knowledge of the project's remote token alone so a
 * paired phone can record without the owner cookie.
 */
class AudioLayerController extends Controller
{
    public function store(StoreAudioLayerRequest $request, Project $project): JsonResponse
    {
        $file = $request->file('audio');

        $path = $file->storeAs(
            "{$project->storageDirectory()}/audio-layers",
            Str::uuid7().'.'.$file->getClientOriginalExtension()
        );

        abort_if($path === false, 500, 'Unable to store the recording.');

        $layer = $project->audioLayers()->create([
            'name' => $request->string('name')->value(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'audio/webm',
            'offset' => $request->float('offset'),
            'volume' => 1,
            'duration' => $request->float('duration'),
        ]);

        broadcast(new AudioLayerUpdated($project));

        return response()->json([
            'layer' => $this->layerProps($project, $layer),
            'layerCount' => $project->audioLayers()->count(),
        ], 201);
    }

    public function update(UpdateAudioLayerRequest $request, Project $project, AudioLayer $audioLayer): RedirectResponse
    {
        abort_unless($audioLayer->project_id === $project->id, 404);

        $audioLayer->update($request->validated());

        broadcast(new AudioLayerUpdated($project));

        return back();
    }

    public function destroy(Project $project, AudioLayer $audioLayer): RedirectResponse
    {
        abort_unless($audioLayer->project_id === $project->id, 404);

        Storage::delete($audioLayer->path);

        $audioLayer->delete();

        broadcast(new AudioLayerUpdated($project));

        return back();
    }

    /**
     * Streams the recording through the app: the dub page's decoder must
     * fetch() layer audio, and the bucket's signed URLs send no CORS
     * headers (same constraint as FrameController::image).
     */
    public function audio(Project $project, AudioLayer $audioLayer): StreamedResponse
    {
        abort_unless($audioLayer->project_id === $project->id, 404);

        return Storage::response($audioLayer->path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function layerProps(Project $project, AudioLayer $layer): array
    {
        return [
            'id' => $layer->id,
            'name' => $layer->name,
            'offset' => $layer->offset,
            'volume' => $layer->volume,
            'duration' => $layer->duration,
            'audioUrl' => route('remote.layers.audio', [$project, $layer]),
        ];
    }
}
