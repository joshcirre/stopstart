import type { VideoStatus } from '@/types/models';

/**
 * Broadcast event names as defined by each event's broadcastAs(). The
 * leading dot tells Echo the name is absolute rather than namespaced.
 */
export const PROJECT_EVENTS = {
    remoteCommand: '.remote.command',
    frameCaptured: '.frame.captured',
    videoStatusUpdated: '.video.status',
    layerUpdated: '.layer.updated',
} as const;

export type RemoteCommand = 'capture' | 'interval-start' | 'interval-stop';

export interface RemoteCommandReceivedEvent {
    command: RemoteCommand;
    intervalSeconds: number | null;
}

export interface FrameCapturedEvent {
    frameId: number;
    sequence: number;
    frameCount: number;
    thumbnailUrl: string;
}

export interface VideoStatusUpdatedEvent {
    videoId: number;
    status: VideoStatus;
    url: string | null;
    downloadUrl: string | null;
    error: string | null;
}

export interface LayerUpdatedEvent {
    layerCount: number;
}
