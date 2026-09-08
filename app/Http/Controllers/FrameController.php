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

        $afterFrameId = $request->integer('after_frame_id') ?: null;
        $shifted = false;

        // The lock serializes sequence assignment under rapid captures; the
        // unique [project_id, sequence] index is the backstop.
        $frame = Cache::lock("project:{$project->id}:frame-sequence", 10)
            ->block(5, function () use ($project, $path, $afterFrameId, &$shifted): Frame {
                if ($afterFrameId === null) {
                    return $project->frames()->create([
                        'sequence' => ((int) $project->frames()->max('sequence')) + 1,
                        'path' => $path,
                    ]);
                }

                return DB::transaction(function () use ($project, $path, $afterFrameId, &$shifted): Frame {
                    $anchor = $project->frames()->whereKey($afterFrameId)->firstOrFail();
                    $sequence = $anchor->sequence + 1;

                    // A deleted frame may already have left this slot open,
                    // in which case nothing below it has to move.
                    $shifted = $project->frames()->where('sequence', $sequence)->exists();

                    if ($shifted) {
                        $this->shiftFramesDown($project, $sequence);
                    }

                    return $project->frames()->create([
                        'sequence' => $sequence,
                        'path' => $path,
                    ]);
                });
            });

        broadcast(new FrameCaptured($frame));

        return response()->json([
            'frame' => [
                'id' => $frame->id,
                'sequence' => $frame->sequence,
                'thumbnailUrl' => $frame->thumbnailUrl(),
            ],
            'frameCount' => $project->frames()->count(),
            'shifted' => $shifted,
        ], 201);
    }

    /**
     * Opens a slot at $sequence by pushing that frame and everything after
     * it one step later. The two-pass negation keeps every intermediate row
     * out of the positive range, so the unique [project_id, sequence] index
     * can never collide mid-shift. Callers must already hold the project's
     * frame-sequence lock.
     */
    private function shiftFramesDown(Project $project, int $sequence): void
    {
        $project->frames()
            ->where('sequence', '>=', $sequence)
            ->update(['sequence' => DB::raw('-(sequence + 1)')]);

        $project->frames()
            ->where('sequence', '<', 0)
            ->update(['sequence' => DB::raw('-sequence')]);
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
