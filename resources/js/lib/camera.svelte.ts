export type CameraStatus =
    'idle' | 'starting' | 'active' | 'denied' | 'unavailable' | 'error';

/**
 * Manages the getUserMedia stream lifecycle and captures frames at the
 * project's exact output resolution using a center cover-crop, so what
 * the preview shows (object-cover) is exactly what gets stored.
 */
export class Camera {
    status = $state<CameraStatus>('idle');

    devices = $state<MediaDeviceInfo[]>([]);

    activeDeviceId = $state<string | null>(null);

    mirrored = $state(false);

    errorMessage = $state<string | null>(null);

    /** Optical/digital zoom bounds when the camera supports zooming. */
    zoomRange = $state<{ min: number; max: number; step: number } | null>(null);

    zoom = $state(1);

    stream: MediaStream | null = null;

    #canvas: HTMLCanvasElement | null = null;

    constructor(
        private readonly targetWidth: number,
        private readonly targetHeight: number,
    ) {}

    async start(deviceId?: string): Promise<void> {
        if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
            this.status = 'unavailable';
            this.errorMessage =
                'Camera access needs a secure context: open the app over HTTPS or on localhost.';

            return;
        }

        this.status = 'starting';
        this.errorMessage = null;
        this.stop(false);

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    ...(deviceId
                        ? { deviceId: { exact: deviceId } }
                        : { facingMode: { ideal: 'environment' } }),
                    width: { ideal: 3840 },
                    height: { ideal: 2160 },
                },
                audio: false,
            });
        } catch (error) {
            this.#handleStartError(error, deviceId);

            return;
        }

        const track = this.stream.getVideoTracks()[0];

        this.activeDeviceId = track.getSettings().deviceId ?? deviceId ?? null;
        this.mirrored = track.getSettings().facingMode === 'user';

        track.addEventListener('ended', () => {
            this.status = 'error';
            this.errorMessage = 'The camera stopped unexpectedly.';
        });

        this.#readZoomCapability(track);

        this.devices = (await navigator.mediaDevices.enumerateDevices()).filter(
            (device) => device.kind === 'videoinput',
        );

        this.status = 'active';
    }

    async switchTo(deviceId: string): Promise<void> {
        await this.start(deviceId);
    }

    async setZoom(value: number): Promise<void> {
        const track = this.stream?.getVideoTracks()[0];

        if (!track || !this.zoomRange) {
            return;
        }

        this.zoom = value;

        try {
            // Zoom constraints are not in the standard TS lib yet.
            await track.applyConstraints({
                advanced: [{ zoom: value } as MediaTrackConstraintSet],
            });
        } catch {
            // Some devices reject mid-stream zoom; the slider stays cosmetic.
        }
    }

    #readZoomCapability(track: MediaStreamTrack): void {
        const capabilities = track.getCapabilities?.() as
            | (MediaTrackCapabilities & {
                  zoom?: { min: number; max: number; step: number };
              })
            | undefined;

        if (
            capabilities?.zoom &&
            capabilities.zoom.max > capabilities.zoom.min
        ) {
            this.zoomRange = capabilities.zoom;
            this.zoom =
                (track.getSettings() as MediaTrackSettings & { zoom?: number })
                    .zoom ?? capabilities.zoom.min;
        } else {
            this.zoomRange = null;
        }
    }

    stop(resetStatus = true): void {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        if (resetStatus) {
            this.status = 'idle';
        }
    }

    async captureBlob(video: HTMLVideoElement): Promise<Blob> {
        if (video.readyState < 2) {
            throw new Error('The camera is not ready yet.');
        }

        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;

        const scale = Math.max(
            this.targetWidth / videoWidth,
            this.targetHeight / videoHeight,
        );
        const sourceWidth = this.targetWidth / scale;
        const sourceHeight = this.targetHeight / scale;
        const sourceX = (videoWidth - sourceWidth) / 2;
        const sourceY = (videoHeight - sourceHeight) / 2;

        this.#canvas ??= document.createElement('canvas');
        this.#canvas.width = this.targetWidth;
        this.#canvas.height = this.targetHeight;

        const context = this.#canvas.getContext('2d');

        if (!context) {
            throw new Error('Unable to create a canvas context.');
        }

        context.drawImage(
            video,
            sourceX,
            sourceY,
            sourceWidth,
            sourceHeight,
            0,
            0,
            this.targetWidth,
            this.targetHeight,
        );

        return new Promise<Blob>((resolve, reject) => {
            this.#canvas!.toBlob(
                (blob) =>
                    blob
                        ? resolve(blob)
                        : reject(new Error('Capturing the frame failed.')),
                'image/jpeg',
                0.92,
            );
        });
    }

    #handleStartError(error: unknown, deviceId?: string): void {
        if (error instanceof DOMException) {
            if (error.name === 'NotAllowedError') {
                this.status = 'denied';

                return;
            }

            if (error.name === 'NotFoundError') {
                this.status = 'unavailable';
                this.errorMessage = 'No camera was found on this device.';

                return;
            }

            if (error.name === 'OverconstrainedError' && deviceId) {
                void this.start();

                return;
            }
        }

        this.status = 'error';
        this.errorMessage =
            error instanceof Error
                ? error.message
                : 'The camera failed to start.';
    }
}
