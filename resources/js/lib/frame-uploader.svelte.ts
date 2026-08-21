import { http } from '@inertiajs/svelte';
import type { FrameStoreResponse } from '@/types/models';

interface QueuedUpload {
    key: string;
    blob: Blob;
}

/**
 * Uploads captured frames sequentially in the background so rapid or
 * interval captures never block the camera, and frame order is kept.
 */
export class FrameUploader {
    pendingCount = $state(0);

    readonly inFlight = $derived(this.pendingCount > 0);

    #queue: QueuedUpload[] = [];

    #draining = false;

    constructor(
        private readonly uploadUrl: string,
        private readonly onUploaded: (
            key: string,
            response: FrameStoreResponse,
        ) => void,
        private readonly onFailed: (key: string, message: string) => void,
    ) {}

    enqueue(key: string, blob: Blob): void {
        this.#queue.push({ key, blob });
        this.pendingCount = this.#queue.length + (this.#draining ? 1 : 0);

        void this.#drain();
    }

    async #drain(): Promise<void> {
        if (this.#draining) {
            return;
        }

        this.#draining = true;

        let upload = this.#queue.shift();

        while (upload) {
            this.pendingCount = this.#queue.length + 1;

            await this.#upload(upload);

            upload = this.#queue.shift();
        }

        this.#draining = false;
        this.pendingCount = 0;
    }

    async #upload(upload: QueuedUpload, attempt = 1): Promise<void> {
        try {
            const formData = new FormData();
            formData.append('image', upload.blob, 'frame.jpg');

            const response = await http.getClient().request({
                method: 'post',
                url: this.uploadUrl,
                data: formData,
                headers: { Accept: 'application/json' },
            });

            const parsed = JSON.parse(
                response.data as string,
            ) as FrameStoreResponse;

            this.onUploaded(upload.key, parsed);
        } catch (error) {
            if (attempt === 1) {
                await new Promise((resolve) => setTimeout(resolve, 2000));

                return this.#upload(upload, 2);
            }

            this.onFailed(
                upload.key,
                error instanceof Error ? error.message : 'Upload failed',
            );
        }
    }
}
