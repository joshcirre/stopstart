import { http } from '@inertiajs/svelte';
import {
    AudioBufferSource,
    BlobSource,
    BufferTarget,
    Conversion,
    getFirstEncodableAudioCodec,
    Input,
    MP4,
    Mp4OutputFormat,
    Output,
    Quality,
} from 'mediabunny';
import { decodeLayerChunks } from '@/lib/audio-mixer.svelte';
import type { LayerChunk } from '@/lib/audio-mixer.svelte';
import type { AudioLayer, DubExport } from '@/types/models';

export type ExportPhase =
    | 'idle'
    | 'fetching'
    | 'decoding'
    | 'mixing'
    | 'muxing'
    | 'uploading'
    | 'done'
    | 'error';

/**
 * Produces the final MP4 entirely in the browser: the master's video
 * track is copied without re-encoding (composable Conversion) while the
 * layers are mixed into a single audio track and encoded alongside it.
 */
export class DubExporter {
    phase = $state<ExportPhase>('idle');

    progress = $state(0);

    errorMessage = $state<string | null>(null);

    readonly busy = $derived(
        this.phase !== 'idle' &&
            this.phase !== 'done' &&
            this.phase !== 'error',
    );

    constructor(
        private readonly videoStreamUrl: string,
        private readonly uploadUrl: string,
    ) {}

    async export(
        layers: AudioLayer[],
        chunksByLayer: Map<number, LayerChunk[]>,
    ): Promise<DubExport | null> {
        if (this.busy) {
            return null;
        }

        this.errorMessage = null;

        try {
            this.phase = 'fetching';
            this.progress = 0;

            const response = await fetch(this.videoStreamUrl);

            if (!response.ok) {
                throw new Error('Downloading the video failed.');
            }

            const input = new Input({
                source: new BlobSource(await response.blob()),
                formats: [MP4],
            });
            const videoDuration = await input.computeDuration();

            this.phase = 'decoding';

            for (const [index, layer] of layers.entries()) {
                if (!chunksByLayer.has(layer.id)) {
                    chunksByLayer.set(
                        layer.id,
                        await decodeLayerChunks(layer.audioUrl),
                    );
                }

                this.progress = (index + 1) / Math.max(1, layers.length);
            }

            this.phase = 'mixing';

            const mixed = await this.mix(layers, chunksByLayer, videoDuration);

            this.phase = 'muxing';
            this.progress = 0;

            const file = await this.mux(input, mixed);

            this.phase = 'uploading';

            const exported = await this.upload(file);

            this.phase = 'done';
            this.progress = 1;

            return exported;
        } catch (error) {
            this.phase = 'error';
            this.errorMessage =
                error instanceof Error ? error.message : 'Export failed';

            return null;
        }
    }

    private async mix(
        layers: AudioLayer[],
        chunksByLayer: Map<number, LayerChunk[]>,
        videoDuration: number,
    ): Promise<AudioBuffer> {
        // The context's length hard-trims audio past the video's end, and
        // untouched regions stay silent — padding falls out for free.
        const context = new OfflineAudioContext(
            2,
            Math.ceil(videoDuration * 48000),
            48000,
        );

        for (const layer of layers) {
            const gain = context.createGain();
            gain.gain.value = layer.volume;
            gain.connect(context.destination);

            for (const chunk of chunksByLayer.get(layer.id) ?? []) {
                const startsAt = layer.offset + chunk.timestamp;

                if (startsAt >= videoDuration) {
                    continue;
                }

                const node = context.createBufferSource();
                node.buffer = chunk.buffer;
                node.connect(gain);
                node.start(startsAt);
            }
        }

        return context.startRendering();
    }

    private async mux(input: Input, mixed: AudioBuffer): Promise<Blob> {
        const output = new Output({
            format: new Mp4OutputFormat({ fastStart: 'in-memory' }),
            target: new BufferTarget(),
        });

        // No `video` options: any would silently force a re-encode
        // instead of the packet-passthrough fast path.
        const conversion = await Conversion.init({
            input,
            output,
            audio: { discard: true },
            composable: true,
        });

        const codec = await getFirstEncodableAudioCodec(
            output.format.getSupportedAudioCodecs(),
            { numberOfChannels: 2, sampleRate: 48000 },
        );

        if (!codec) {
            throw new Error('This browser cannot encode audio for MP4.');
        }

        const audioSource = new AudioBufferSource({
            codec,
            quality: new Quality('high'),
        });

        output.addAudioTrack(audioSource);

        conversion.onProgress = (progress) => {
            this.progress = progress;
        };

        await output.start();
        await Promise.all([
            conversion.execute(),
            audioSource.add(mixed).then(() => audioSource.close()),
        ]);
        await output.finalize();

        const buffer = output.target.buffer;

        if (!buffer) {
            throw new Error('Muxing produced no output.');
        }

        return new Blob([buffer], { type: 'video/mp4' });
    }

    private async upload(file: Blob): Promise<DubExport> {
        const formData = new FormData();
        formData.append('video', file, 'dub.mp4');

        const response = await http.getClient().request({
            method: 'post',
            url: this.uploadUrl,
            data: formData,
            headers: { Accept: 'application/json' },
        });

        return (JSON.parse(response.data) as { video: DubExport }).video;
    }
}
