<?php

namespace App\Events;

use App\Models\Frame;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FrameCaptured implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Frame $frame) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->frame->project->channelName())];
    }

    public function broadcastAs(): string
    {
        return 'frame.captured';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'frameId' => $this->frame->id,
            'sequence' => $this->frame->sequence,
            'frameCount' => $this->frame->project->frames()->count(),
            'thumbnailUrl' => $this->frame->thumbnailUrl(),
        ];
    }
}
