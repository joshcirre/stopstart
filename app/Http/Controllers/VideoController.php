<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Events\VideoStatusUpdated;
use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Http\Requests\StoreRenderedVideoRequest;
use App\Jobs\GenerateVideo;
use App\Models\Project;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    use AuthorizesOwner;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeOwner($request, $project);

        if ($project->frames()->doesntExist()) {
            throw ValidationException::withMessages([
                'frames' => 'Capture at least one frame before rendering a video.',
            ]);
        }

        $this->failStaleRenders($project);

        if ($project->videos()->whereIn('status', [VideoStatus::Pending, VideoStatus::Processing])->exists()) {
            throw ValidationException::withMessages([
                'video' => 'A render is already in progress for this project.',
            ]);
        }

        $video = $project->videos()->create(['fps' => $project->fps]);

        GenerateVideo::dispatch($video);

        return back();
    }

    /**
     * Stores a video rendered client-side (mediabunny/WebCodecs in the
     * browser). The server-side ffmpeg queue render remains available as
     * the fallback for browsers without WebCodecs support.
     */
    public function upload(StoreRenderedVideoRequest $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $path = $request->file('video')->storeAs(
            "{$project->storageDirectory()}/videos",
            Str::uuid7().'.mp4'
        );

        abort_if($path === false, 500, 'Unable to store the rendered video.');

        $video = $project->videos()->create([
            'status' => VideoStatus::Completed,
            'fps' => $project->fps,
            'path' => $path,
        ]);

        broadcast(new VideoStatusUpdated($video));

        return response()->json([
            'video' => [
                'id' => $video->id,
                'status' => $video->status->value,
                'url' => $video->url(),
                'downloadUrl' => route('videos.download', $video),
                'error' => null,
            ],
        ], 201);
    }

    /**
     * A render whose row hasn't been touched for far longer than the job
     * timeout was killed without running failed() (e.g. an OOM-killed
     * worker). Fail it here so it stops blocking new renders.
     */
    private function failStaleRenders(Project $project): void
    {
        $project->videos()
            ->whereIn('status', [VideoStatus::Pending, VideoStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes(15))
            ->get()
            ->each(function (Video $video) {
                $video->update([
                    'status' => VideoStatus::Failed,
                    'error' => 'The render was interrupted before it could finish — the worker likely ran out of memory. Try rendering again.',
                ]);

                broadcast(new VideoStatusUpdated($video));
            });
    }

    public function download(Request $request, Video $video): StreamedResponse
    {
        $this->authorizeOwner($request, $video->project);

        abort_unless($video->status === VideoStatus::Completed && $video->path !== null, 404);

        return Storage::download($video->path, Str::slug($video->project->name).'.mp4');
    }
}
