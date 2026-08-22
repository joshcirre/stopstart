<?php

namespace App\Events;

use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoStatusUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Video $video) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->video->project->channelName())];
    }

    public function broadcastAs(): string
    {
        return 'video.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'videoId' => $this->video->id,
            'status' => $this->video->status->value,
            'url' => $this->video->url(),
            'downloadUrl' => $this->video->status === VideoStatus::Completed
                ? route('videos.download', $this->video)
                : null,
            'error' => $this->video->error,
        ];
    }
}
