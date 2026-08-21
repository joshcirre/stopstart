<?php

namespace App\Http\Controllers;

use App\Events\RemoteCommandReceived;
use App\Http\Requests\RemoteCommandRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The remote routes are authorized by knowledge of the project's
 * remote token alone, so a paired phone never needs the owner cookie.
 */
class RemoteController extends Controller
{
    public function show(Project $project): Response
    {
        return Inertia::render('remote/Show', [
            'projectName' => $project->name,
            'remoteToken' => $project->remote_token,
            'orientation' => $project->orientation->value,
            'fps' => $project->fps,
            'frameCount' => $project->frames()->count(),
            'lastFrameThumbnailUrl' => $project->latestFrame?->thumbnailUrl(),
        ]);
    }

    public function command(RemoteCommandRequest $request, Project $project): JsonResponse
    {
        broadcast(new RemoteCommandReceived(
            $project,
            $request->string('command')->value(),
            $request->filled('intervalSeconds') ? $request->integer('intervalSeconds') : null,
        ));

        return response()->json(['ok' => true]);
    }
}
