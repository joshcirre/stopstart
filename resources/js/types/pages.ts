import type {
    CaptureProject,
    Frame,
    Orientation,
    Project,
    Video,
    VideoStatus,
} from '@/types/models';

export interface ProjectSummary {
    id: number;
    name: string;
    orientation: Orientation;
    fps: number;
    frameCount: number;
    latestFrameThumbnailUrl: string | null;
    videoStatus: VideoStatus | null;
}

export interface ProjectsIndexProps {
    projects: ProjectSummary[];
}

export interface ProjectShowProps {
    project: Project;
    frames: Frame[];
    frameCount: number;
    video: Video | null;
}

export interface ProjectCaptureProps {
    project: CaptureProject;
    frames: Frame[];
    frameCount: number;
    remoteUrl: string;
    videoInFlight: boolean;
}

export interface RemoteShowProps {
    projectName: string;
    remoteToken: string;
    orientation: Orientation;
    fps: number;
    frameCount: number;
    lastFrameThumbnailUrl: string | null;
}
