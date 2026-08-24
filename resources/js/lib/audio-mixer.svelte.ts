import {
    AudioBufferSink,
    BlobSource,
    Input,
    MP4,
    QTFF,
    WEBM,
} from 'mediabunny';
import { SvelteMap } from 'svelte/reactivity';
import type { AudioLayer } from '@/types/models';

export interface LayerChunk {
    buffer: AudioBuffer;
    /** Seconds from the start of the recording. */
    timestamp: number;
}

/**
 * Decodes a recorded layer into ordered AudioBuffer chunks via
 * mediabunny (WebCodecs) — more reliable than decodeAudioData for
 * MediaRecorder webm/opus, and it handles Safari's mp4 recordings too.
 * The URL must be same-origin (bucket URLs have no CORS headers).
 */
export async function decodeLayerChunks(
    audioUrl: string,
): Promise<LayerChunk[]> {
    const response = await fetch(audioUrl);

    if (!response.ok) {
        throw new Error('Downloading the recording failed.');
    }

    const input = new Input({
        source: new BlobSource(await response.blob()),
        formats: [WEBM, MP4, QTFF],
    });

    const track = await input.getPrimaryAudioTrack();

    if (!track) {
        throw new Error('The recording has no audio track.');
    }

    const chunks: LayerChunk[] = [];
    const sink = new AudioBufferSink(track);

    for await (const { buffer, timestamp } of sink.buffers()) {
        chunks.push({ buffer, timestamp });
    }

    return chunks;
}

interface ScheduledNode {
    node: AudioBufferSourceNode;
    gain: GainNode;
    layerId: number;
}

/**
 * Live preview: schedules every layer's chunks on a shared AudioContext
 * in sync with the playing video. Create/resume only inside a user
 * gesture (autoplay policy).
 */
export class MixPreview {
    playing = $state(false);

    #context: AudioContext | null = null;

    #nodes: ScheduledNode[] = [];

    #gainsByLayer = new SvelteMap<number, GainNode[]>();

    start(
        video: HTMLVideoElement,
        layers: AudioLayer[],
        chunksByLayer: Map<number, LayerChunk[]>,
    ): void {
        this.stop();

        this.#context ??= new AudioContext();

        if (this.#context.state === 'suspended') {
            void this.#context.resume();
        }

        const context = this.#context;
        const videoTime = video.currentTime;

        for (const layer of layers) {
            const chunks = chunksByLayer.get(layer.id) ?? [];
            const gain = context.createGain();
            gain.gain.value = layer.volume;
            gain.connect(context.destination);

            this.#gainsByLayer.set(layer.id, [
                ...(this.#gainsByLayer.get(layer.id) ?? []),
                gain,
            ]);

            for (const chunk of chunks) {
                const startsAt = layer.offset + chunk.timestamp - videoTime;
                const elapsed = startsAt < 0 ? -startsAt : 0;

                if (elapsed >= chunk.buffer.duration) {
                    continue;
                }

                const node = context.createBufferSource();
                node.buffer = chunk.buffer;
                node.connect(gain);
                node.start(
                    context.currentTime + Math.max(0, startsAt),
                    elapsed,
                );

                this.#nodes.push({ node, gain, layerId: layer.id });
            }
        }

        this.playing = true;
    }

    setVolume(layerId: number, volume: number): void {
        for (const gain of this.#gainsByLayer.get(layerId) ?? []) {
            gain.gain.value = volume;
        }
    }

    stop(): void {
        for (const { node } of this.#nodes) {
            try {
                node.stop();
            } catch {
                // Already ended.
            }

            node.disconnect();
        }

        this.#nodes = [];
        this.#gainsByLayer.clear();
        this.playing = false;
    }
}
