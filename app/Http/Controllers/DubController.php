<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Events\VideoStatusUpdated;
use App\Http\Requests\StoreRenderedVideoRequest;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The dub workspace: record voice layers over the rendered master and
 * export the mix. Token-authorized like the remote routes so it works
 * on a paired phone without the owner cookie.
 */
class DubController extends Controller
{
    public function show(Project $project): Response
    {
        $master = $project->latestMasterVideo;
        $export = $project->latestExport;

        $masterReady = $master !== null
            && $master->status === VideoStatus::Completed
            && $master->path !== null;

        return Inertia::render('remote/Dub', [
            'projectName' => $project->name,
            'remoteToken' => $project->remote_token,
            'orientation' => $project->orientation->value,
            'fps' => $project->fps,
            'video' => $masterReady ? [
                'id' => $master->id,
                'url' => $master->url(),
                'streamUrl' => route('remote.videos.stream', [$project, $master]),
            ] : null,
            'layers' => $project->audioLayers()->orderBy('offset')->get()
                ->map(fn ($layer): array => AudioLayerController::layerProps($project, $layer)),
            'export' => $export !== null ? [
                'id' => $export->id,
                'url' => $export->url(),
            ] : null,
        ]);
    }

    /**
     * Same-origin bytes of the master video for the exporter's fetch()
     * (bucket URLs lack CORS headers; <video> playback uses url()).
     */
    public function streamVideo(Project $project, Video $video): StreamedResponse
    {
        abort_unless(
            $video->project_id === $project->id
                && $video->status === VideoStatus::Completed
                && $video->path !== null,
            404,
        );

        return Storage::response($video->path);
    }

    public function export(StoreRenderedVideoRequest $request, Project $project): JsonResponse
    {
        $path = $request->file('video')->storeAs(
            "{$project->storageDirectory()}/videos",
            Str::uuid7().'.mp4'
        );

        abort_if($path === false, 500, 'Unable to store the exported video.');

        $video = $project->videos()->create([
            'status' => VideoStatus::Completed,
            'fps' => $project->fps,
            'path' => $path,
            'has_audio' => true,
        ]);

        broadcast(new VideoStatusUpdated($video));

        return response()->json([
            'video' => [
                'id' => $video->id,
                'status' => $video->status->value,
                'url' => $video->url(),
                'error' => null,
            ],
        ], 201);
    }
}
