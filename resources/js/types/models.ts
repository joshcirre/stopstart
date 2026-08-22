export type Orientation = 'landscape' | 'portrait';

export type VideoStatus = 'pending' | 'processing' | 'completed' | 'failed';

export const ORIENTATION_DIMENSIONS = {
    landscape: { width: 1920, height: 1080 },
    portrait: { width: 1080, height: 1920 },
} as const satisfies Record<Orientation, { width: number; height: number }>;

export const INTERVAL_CHOICES = [2, 3, 5, 10, 30] as const;

export const FPS_CHOICES = [6, 12, 24] as const;

export interface Project {
    id: number;
    name: string;
    orientation: Orientation;
    fps: number;
    remoteToken: string;
}

export interface CaptureProject extends Project {
    width: number;
    height: number;
}

export interface Frame {
    id: number;
    sequence: number;
    thumbnailUrl: string;
    /** Same-origin stream of the frame, fetchable without bucket CORS. */
    imageUrl?: string;
}

export interface Video {
    id: number;
    status: VideoStatus;
    url: string | null;
    downloadUrl: string | null;
    error: string | null;
}

export interface FrameStoreResponse {
    frame: Frame;
    frameCount: number;
}

export interface StripFrame {
    key: string;
    src: string;
    sequence: number;
    pending?: boolean;
    failed?: boolean;
}
