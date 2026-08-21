<?php

namespace App\Http\Controllers;

use App\Events\FrameCaptured;
use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Http\Requests\StoreFrameRequest;
use App\Models\Frame;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
