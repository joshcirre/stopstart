<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaptureController extends Controller
{
    use AuthorizesOwner;

    public function show(Request $request, Project $project): Response
    {
        $this->authorizeOwner($request, $project);

        $latestVideo = $project->latestVideo;

        return Inertia::render('projects/Capture', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'orientation' => $project->orientation->value,
                'width' => $project->orientation->width(),
                'height' => $project->orientation->height(),
                'fps' => $project->fps,
                'remoteToken' => $project->remote_token,
            ],
            'frames' => $project->frames()->orderByDesc('sequence')->limit(20)->get()
                ->reverse()
                ->values()
                ->map(fn ($frame): array => [
                    'id' => $frame->id,
                    'sequence' => $frame->sequence,
                    'thumbnailUrl' => $frame->thumbnailUrl(),
                ]),
            'frameCount' => $project->frames()->count(),
            'remoteUrl' => route('remote.show', $project),
            'videoInFlight' => $latestVideo !== null && $latestVideo->status->isInFlight(),
        ]);
    }
}
