<?php

namespace App\Http\Controllers;

use App\Events\FrameCaptured;
use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Http\Requests\MoveFrameRequest;
use App\Http\Requests\StoreFrameRequest;
use App\Models\Frame;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrameController extends Controller
{
    use AuthorizesOwner;

    public function store(StoreFrameRequest $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $path = $request->file('image')->storeAs(
            "{$project->storageDirectory()}/frames",
            Str::uuid7().'.jpg'
        );

        abort_if($path === false, 500, 'Unable to store the captured frame.');

        // The lock serializes sequence assignment under rapid captures; the
        // unique [project_id, sequence] index is the backstop.
        $frame = Cache::lock("project:{$project->id}:frame-sequence", 10)
            ->block(5, fn (): Frame => $project->frames()->create([
                'sequence' => ((int) $project->frames()->max('sequence')) + 1,
                'path' => $path,
            ]));

        broadcast(new FrameCaptured($frame));

        return response()->json([
            'frame' => [
                'id' => $frame->id,
                'sequence' => $frame->sequence,
                'thumbnailUrl' => $frame->thumbnailUrl(),
            ],
            'frameCount' => $project->frames()->count(),
        ], 201);
    }

    /**
     * Streams the frame through the app so the browser-side renderer can
     * fetch() it: the bucket's signed URLs live on another origin and send
     * no CORS headers, which blocks fetch (but not <img> display).
     */
    public function image(Request $request, Project $project, Frame $frame): StreamedResponse
    {
        $this->authorizeOwner($request, $project);

        abort_unless($frame->project_id === $project->id, 404);

        return Storage::response($frame->path);
    }

    /**
     * Swaps the frame's sequence with its neighbor. The same lock that
     * serializes capture sequencing prevents races with in-flight
     * uploads; the pass through sequence 0 (never assigned to frames)
     * sidesteps the unique [project_id, sequence] index mid-swap.
     */
    public function move(MoveFrameRequest $request, Project $project, Frame $frame): RedirectResponse
    {
        $this->authorizeOwner($request, $project);

        abort_unless($frame->project_id === $project->id, 404);

        $earlier = $request->string('direction')->value() === 'earlier';

        Cache::lock("project:{$project->id}:frame-sequence", 10)
            ->block(5, function () use ($project, $frame, $earlier): void {
                $neighbor = $project->frames()
                    ->when(
                        $earlier,
                        fn ($query) => $query->where('sequence', '<', $frame->sequence)->orderByDesc('sequence'),
                        fn ($query) => $query->where('sequence', '>', $frame->sequence)->orderBy('sequence'),
                    )
                    ->first();

                if ($neighbor === null) {
                    return;
                }

                DB::transaction(function () use ($frame, $neighbor): void {
                    $frameTarget = $neighbor->sequence;
                    $neighborTarget = $frame->sequence;

                    $frame->update(['sequence' => 0]);
                    $neighbor->update(['sequence' => $neighborTarget]);
                    $frame->update(['sequence' => $frameTarget]);
                });
            });

        return back();
    }

    public function destroy(Request $request, Project $project, Frame $frame): RedirectResponse
    {
        $this->authorizeOwner($request, $project);

        abort_unless($frame->project_id === $project->id, 404);

        Storage::delete($frame->path);

        // Remaining sequences keep their gaps; the render job re-numbers
        // frames into a gapless set when building the video.
        $frame->delete();

        return back();
    }
}
