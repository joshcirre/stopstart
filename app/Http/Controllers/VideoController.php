<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Http\Controllers\Concerns\AuthorizesOwner;
use App\Jobs\GenerateVideo;
use App\Models\Project;
use App\Models\Video;
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

        if ($project->videos()->whereIn('status', [VideoStatus::Pending, VideoStatus::Processing])->exists()) {
            throw ValidationException::withMessages([
                'video' => 'A render is already in progress for this project.',
            ]);
        }

        $video = $project->videos()->create(['fps' => $project->fps]);

        GenerateVideo::dispatch($video);

        return back();
    }

    public function download(Request $request, Video $video): StreamedResponse
    {
        $this->authorizeOwner($request, $video->project);

        abort_unless($video->status === VideoStatus::Completed && $video->path !== null, 404);

        return Storage::download($video->path, Str::slug($video->project->name).'.mp4');
    }
}
