<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Models\Video;
use App\Support\OwnerToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use AuthorizesOwner;

    public function index(Request $request): Response
    {
        $projects = Project::ownedBy(OwnerToken::from($request))
            ->withCount('frames')
            ->with(['latestFrame', 'latestVideo'])
            ->latest()
            ->get();

        return Inertia::render('projects/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'orientation' => $project->orientation->value,
                'fps' => $project->fps,
                'frameCount' => $project->frames_count,
                'latestFrameThumbnailUrl' => $project->latestFrame?->thumbnailUrl(),
                'videoStatus' => $project->latestVideo?->status->value,
            ]),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'owner_token' => OwnerToken::from($request),
        ]);

        return redirect()->route('projects.capture', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorizeOwner($request, $project);

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'orientation' => $project->orientation->value,
                'fps' => $project->fps,
                'remoteToken' => $project->remote_token,
            ],
            'frames' => $project->frames()->orderBy('sequence')->get()
                ->map(fn ($frame): array => [
                    'id' => $frame->id,
                    'sequence' => $frame->sequence,
                    'thumbnailUrl' => $frame->thumbnailUrl(),
                    'imageUrl' => route('projects.frames.image', [$project, $frame]),
                ]),
            'frameCount' => $project->frames()->count(),
            'video' => $this->videoProps($project->latestVideo),
        ]);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeOwner($request, $project);

        // A project holds at most a few hundred objects; deleteDirectory
        // batches the removals, so inline cleanup is fine here.
        Storage::deleteDirectory($project->storageDirectory());

        $project->delete();

        return redirect()->route('projects.index');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function videoProps(?Video $video): ?array
    {
        if ($video === null) {
            return null;
        }

        return [
            'id' => $video->id,
            'status' => $video->status->value,
            'url' => $video->url(),
            'downloadUrl' => $video->url() !== null ? route('videos.download', $video) : null,
            'error' => $video->error,
        ];
    }
}
