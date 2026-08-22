<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Events\VideoStatusUpdated;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File as HttpFile;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public Video $video) {}

    public function handle(): void
    {
        if ($this->video->status !== VideoStatus::Pending) {
            return;
        }

        $this->video->update(['status' => VideoStatus::Processing]);
        broadcast(new VideoStatusUpdated($this->video));

        $temporaryDirectory = sys_get_temp_dir().'/stopstart-render-'.$this->video->id;
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $this->copyFramesTo($temporaryDirectory);
            $outputPath = $this->renderVideo($temporaryDirectory);
            $storedPath = $this->storeVideo($outputPath);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }

        $this->video->update(['status' => VideoStatus::Completed, 'path' => $storedPath]);
        broadcast(new VideoStatusUpdated($this->video));
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'Unknown error';

        // "Attempted too many times" means a previous attempt was killed
        // without running this handler (usually the worker being OOM-killed
        // mid-encode) and the queue redelivered the job.
        if ($exception instanceof MaxAttemptsExceededException) {
            $message = 'The render was interrupted before it could finish — '
                .'the worker likely ran out of memory. Try rendering again.';
        }

        $this->video->update([
            'status' => VideoStatus::Failed,
            'error' => Str::limit($message, 2000),
        ]);

        broadcast(new VideoStatusUpdated($this->video));
    }

    /**
     * Copy the project's frames into gapless, sequentially named files.
     * Frames are fetched through Storage because the default disk may be
     * a remote bucket with no local paths.
     */
    private function copyFramesTo(string $directory): void
    {
        $frames = $this->video->project->frames()->orderBy('sequence')->get();

        if ($frames->isEmpty()) {
            throw new RuntimeException('The project has no frames to render.');
        }

        foreach ($frames->values() as $index => $frame) {
            $contents = Storage::get($frame->path)
                ?? throw new RuntimeException("Missing frame file: {$frame->path}");

            File::put($directory.'/'.sprintf('frame_%06d.jpg', $index), $contents);
        }
    }

    private function renderVideo(string $directory): string
    {
        $orientation = $this->video->project->orientation;
        $outputPath = $directory.'/output.mp4';

        // The thread and lookahead caps keep x264's memory flat regardless of
        // frame count: with defaults, the encoder buffers up to ~40 input
        // frames (~3MB each at 1080p), which OOM-kills small queue workers.
        $result = Process::path($directory)->timeout(300)->run([
            config()->string('stopstart.ffmpeg_path'),
            '-y',
            '-framerate', (string) $this->video->fps,
            '-i', 'frame_%06d.jpg',
            '-vf', sprintf('scale=%d:%d', $orientation->width(), $orientation->height()),
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-threads', '2',
            '-x264-params', 'ref=1:rc-lookahead=8',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-r', (string) $this->video->fps,
            $outputPath,
        ]);

        if (! $result->successful() || ! File::exists($outputPath)) {
            throw new RuntimeException('ffmpeg failed: '.Str::limit($result->errorOutput(), 1000));
        }

        return $outputPath;
    }

    private function storeVideo(string $outputPath): string
    {
        $storedPath = Storage::putFileAs(
            "{$this->video->project->storageDirectory()}/videos",
            new HttpFile($outputPath),
            "video-{$this->video->id}.mp4"
        );

        if ($storedPath === false) {
            throw new RuntimeException('Unable to store the rendered video.');
        }

        return $storedPath;
    }
}
