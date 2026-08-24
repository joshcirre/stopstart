import { http } from '@inertiajs/svelte';
import {
    BufferTarget,
    canEncodeVideo,
    CanvasSource,
    Mp4OutputFormat,
    Output,
    Quality,
} from 'mediabunny';
import type { Frame, Video } from '@/types/models';

export type RenderPhase =
    'idle' | 'fetching' | 'encoding' | 'uploading' | 'done' | 'error';

export async function supportsClientRendering(
    width: number,
    height: number,
): Promise<boolean> {
    if (typeof VideoEncoder === 'undefined') {
        return false;
    }

    try {
        return await canEncodeVideo('avc', { width, height });
    } catch {
        return false;
    }
}

/**
 * Renders the project's frames into an MP4 in the browser (WebCodecs via
 * mediabunny) and uploads the finished file. Skips the server-side ffmpeg
 * queue entirely; that path remains as the fallback for browsers without
 * WebCodecs H.264 support.
 */
export class ClientRenderer {
    phase = $state<RenderPhase>('idle');

    progress = $state(0);

    errorMessage = $state<string | null>(null);

    readonly busy = $derived(
        this.phase === 'fetching' ||
            this.phase === 'encoding' ||
            this.phase === 'uploading',
    );

    constructor(
        private readonly width: number,
        private readonly height: number,
        private readonly fps: number,
        private readonly uploadUrl: string,
    ) {}

    async render(frames: Frame[]): Promise<Video | null> {
        if (this.busy || frames.length === 0) {
            return null;
        }

        this.errorMessage = null;

        try {
            const blobs = await this.fetchFrames(frames);
            const file = await this.encode(blobs);
            const video = await this.upload(file);

            this.phase = 'done';
            this.progress = 1;

            return video;
        } catch (error) {
            this.phase = 'error';
            this.errorMessage =
                error instanceof Error ? error.message : 'Rendering failed';

            return null;
        }
    }

    private async fetchFrames(frames: Frame[]): Promise<Blob[]> {
        this.phase = 'fetching';
        this.progress = 0;

        const blobs: Blob[] = [];

        for (const [index, frame] of frames.entries()) {
            const response = await fetch(frame.imageUrl ?? frame.thumbnailUrl);

            if (!response.ok) {
                throw new Error(`Downloading frame ${frame.sequence} failed.`);
            }

            blobs.push(await response.blob());
            this.progress = (index + 1) / frames.length;
        }

        return blobs;
    }

    private async encode(blobs: Blob[]): Promise<Blob> {
        this.phase = 'encoding';
        this.progress = 0;

        const canvas = new OffscreenCanvas(this.width, this.height);
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Unable to create a canvas context.');
        }

        const source = new CanvasSource(canvas, {
            codec: 'avc',
            quality: new Quality('high'),
        });

        const output = new Output({
            format: new Mp4OutputFormat({ fastStart: 'in-memory' }),
            target: new BufferTarget(),
        });

        output.addVideoTrack(source, { frameRate: this.fps });

        await output.start();

        // Frames are decoded one at a time: holding a full project's worth
        // of 1080p bitmaps in memory would sink low-RAM phones.
        for (const [index, blob] of blobs.entries()) {
            const bitmap = await createImageBitmap(blob);

            context.drawImage(bitmap, 0, 0, this.width, this.height);
            bitmap.close();

            await source.add(index / this.fps, 1 / this.fps);
            this.progress = (index + 1) / blobs.length;
        }

        await output.finalize();

        const buffer = output.target.buffer;

        if (!buffer) {
            throw new Error('Encoding produced no output.');
        }

        return new Blob([buffer], { type: 'video/mp4' });
    }

    private async upload(file: Blob): Promise<Video> {
        this.phase = 'uploading';

        const formData = new FormData();
        formData.append('video', file, 'render.mp4');

        const response = await http.getClient().request({
            method: 'post',
            url: this.uploadUrl,
            data: formData,
            headers: { Accept: 'application/json' },
        });

        return (JSON.parse(response.data) as { video: Video }).video;
    }
}
