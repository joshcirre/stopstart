export type RecorderStatus =
    | 'idle'
    | 'starting'
    | 'ready'
    | 'recording'
    | 'denied'
    | 'unavailable'
    | 'error';

export interface Recording {
    blob: Blob;
    extension: string;
    offset: number;
    duration: number;
}

/**
 * Manages the microphone stream and MediaRecorder lifecycle for voice
 * layers. Offsets come from the video's currentTime when recording
 * starts; duration is wall-clock (exact timing at export uses the
 * decoded chunk timestamps, so small drift here is cosmetic).
 */
export class VoiceRecorder {
    status = $state<RecorderStatus>('idle');

    errorMessage = $state<string | null>(null);

    stream: MediaStream | null = null;

    #recorder: MediaRecorder | null = null;

    #chunks: Blob[] = [];

    #offset = 0;

    #startedAt = 0;

    /** webm/opus preferred; iOS Safari only records audio/mp4 (AAC). */
    static pickMimeType(): { mimeType: string; extension: string } | null {
        if (typeof MediaRecorder === 'undefined') {
            return null;
        }

        if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
            return { mimeType: 'audio/webm;codecs=opus', extension: 'webm' };
        }

        if (MediaRecorder.isTypeSupported('audio/webm')) {
            return { mimeType: 'audio/webm', extension: 'webm' };
        }

        if (MediaRecorder.isTypeSupported('audio/mp4')) {
            return { mimeType: 'audio/mp4', extension: 'm4a' };
        }

        return null;
    }

    async start(): Promise<void> {
        if (
            !navigator.mediaDevices?.getUserMedia ||
            !window.isSecureContext ||
            VoiceRecorder.pickMimeType() === null
        ) {
            this.status = 'unavailable';
            this.errorMessage =
                'Recording needs a secure context: open the app over HTTPS or on localhost.';

            return;
        }

        this.status = 'starting';
        this.errorMessage = null;

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        } catch (error) {
            if (
                error instanceof DOMException &&
                error.name === 'NotAllowedError'
            ) {
                this.status = 'denied';
            } else if (
                error instanceof DOMException &&
                error.name === 'NotFoundError'
            ) {
                this.status = 'unavailable';
                this.errorMessage = 'No microphone was found on this device.';
            } else {
                this.status = 'error';
                this.errorMessage =
                    error instanceof Error
                        ? error.message
                        : 'The microphone failed to start.';
            }

            return;
        }

        this.status = 'ready';
    }

    beginRecording(offset: number): void {
        if (!this.stream || this.status !== 'ready') {
            return;
        }

        const picked = VoiceRecorder.pickMimeType();

        if (!picked) {
            return;
        }

        this.#chunks = [];
        this.#offset = offset;
        this.#startedAt = performance.now();

        this.#recorder = new MediaRecorder(this.stream, {
            mimeType: picked.mimeType,
        });
        this.#recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.#chunks.push(event.data);
            }
        };
        this.#recorder.start();

        this.status = 'recording';
    }

    async finishRecording(): Promise<Recording | null> {
        const recorder = this.#recorder;

        if (!recorder || this.status !== 'recording') {
            return null;
        }

        const duration = (performance.now() - this.#startedAt) / 1000;

        await new Promise<void>((resolve) => {
            recorder.onstop = () => resolve();
            recorder.stop();
        });

        this.#recorder = null;
        this.status = 'ready';

        const picked = VoiceRecorder.pickMimeType();

        if (this.#chunks.length === 0 || !picked) {
            return null;
        }

        return {
            blob: new Blob(this.#chunks, { type: picked.mimeType }),
            extension: picked.extension,
            offset: this.#offset,
            duration,
        };
    }

    stop(): void {
        this.#recorder?.stop();
        this.#recorder = null;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        this.status = 'idle';
    }
}
