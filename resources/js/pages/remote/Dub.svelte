<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { http } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { SvelteMap } from 'svelte/reactivity';
    import AppHead from '@/components/AppHead.svelte';
    import Toaster from '@/components/Toaster.svelte';
    import { decodeLayerChunks, MixPreview } from '@/lib/audio-mixer.svelte';
    import type { LayerChunk } from '@/lib/audio-mixer.svelte';
    import { DubExporter } from '@/lib/dub-exporter.svelte';
    import { subscribeToProjectChannel } from '@/lib/echo.svelte';
    import {
        btnGhostMono,
        emptyState,
        hairlineFill,
        hairlineTrack,
        microLabel,
    } from '@/lib/styles';
    import { toast } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
    import { VoiceRecorder } from '@/lib/voice-recorder.svelte';
    import type { RecorderStatus } from '@/lib/voice-recorder.svelte';
    import { createWakeLock } from '@/lib/wake-lock';
    import { exportMethod as exportRoute } from '@/routes/remote';
    import {
        destroy as destroyLayer,
        store as storeLayer,
        update as updateLayer,
    } from '@/routes/remote/layers';
    import type {
        AudioLayer,
        AudioLayerStoreResponse,
        RemoteDubProps,
    } from '@/types';

    let {
        projectName,
        remoteToken,
        orientation,
        video,
        layers,
        export: dubExport,
    }: RemoteDubProps = $props();

    const aspectClass = $derived(
        orientation === 'landscape' ? 'aspect-video' : 'aspect-[9/16]',
    );

    const recorder = new VoiceRecorder();
    const preview = new MixPreview();
    const exporter = untrack(
        () =>
            new DubExporter(
                video?.streamUrl ?? '',
                exportRoute.url(remoteToken),
            ),
    );
    const wakeLock = createWakeLock();

    let videoElement = $state<HTMLVideoElement | null>(null);
    let videoDuration = $state(0);
    let currentTime = $state(0);
    let videoPlaying = $state(false);
    let uploadingLayer = $state(false);

    const chunkCache = new SvelteMap<number, LayerChunk[]>();

    const recording = $derived(recorder.status === 'recording');
    const busy = $derived(recording || uploadingLayer || exporter.busy);

    const exportPhaseLabels = {
        fetching: 'DOWNLOADING VIDEO',
        decoding: 'DECODING LAYERS',
        mixing: 'MIXING',
        muxing: 'WRITING MP4',
        uploading: 'UPLOADING',
    } as const;

    const exportPhaseLabel = $derived(
        exportPhaseLabels[exporter.phase as keyof typeof exportPhaseLabels] ??
            'EXPORTING',
    );

    function stopPreview(): void {
        preview.stop();
    }

    async function toggleRecording(): Promise<void> {
        if (!videoElement || !video) {
            return;
        }

        if (recording) {
            videoElement.pause();
            await saveRecording();

            return;
        }

        stopPreview();

        if (recorder.status !== 'ready') {
            await recorder.start();
        }

        if ((recorder.status as RecorderStatus) !== 'ready') {
            toast.error(recorder.errorMessage ?? 'Microphone access failed');

            return;
        }

        recorder.beginRecording(videoElement.currentTime);
        void wakeLock.acquire();
        void videoElement.play();
    }

    async function saveRecording(): Promise<void> {
        wakeLock.release();

        const result = await recorder.finishRecording();

        if (!result) {
            return;
        }

        uploadingLayer = true;

        try {
            const formData = new FormData();
            formData.append('audio', result.blob, `layer.${result.extension}`);
            formData.append('name', `Layer ${layers.length + 1}`);
            formData.append('offset', result.offset.toFixed(3));
            formData.append('duration', result.duration.toFixed(3));

            const response = await http.getClient().request({
                method: 'post',
                url: storeLayer.url(remoteToken),
                data: formData,
                headers: { Accept: 'application/json' },
            });

            const parsed = JSON.parse(response.data) as AudioLayerStoreResponse;

            toast.success(`${parsed.layer.name} saved`);
            router.reload({ only: ['layers'] });
        } catch {
            toast.error('Saving the recording failed');
        } finally {
            uploadingLayer = false;
        }
    }

    async function togglePreview(): Promise<void> {
        if (!videoElement || recording) {
            return;
        }

        if (preview.playing || videoPlaying) {
            videoElement.pause();
            stopPreview();

            return;
        }

        try {
            for (const layer of layers) {
                if (!chunkCache.has(layer.id)) {
                    chunkCache.set(
                        layer.id,
                        await decodeLayerChunks(layer.audioUrl),
                    );
                }
            }
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Preview failed',
            );

            return;
        }

        preview.start(videoElement, layers, chunkCache);
        void videoElement.play();
    }

    function nudgeOffset(layer: AudioLayer, delta: number): void {
        const offset = Math.max(0, layer.offset + delta);

        router.patch(
            updateLayer([remoteToken, layer.id]),
            { offset },
            { preserveScroll: true },
        );
    }

    const volumeTimeouts = new SvelteMap<
        number,
        ReturnType<typeof setTimeout>
    >();

    function changeVolume(layer: AudioLayer, volume: number): void {
        preview.setVolume(layer.id, volume);

        clearTimeout(volumeTimeouts.get(layer.id));
        volumeTimeouts.set(
            layer.id,
            setTimeout(() => {
                router.patch(
                    updateLayer([remoteToken, layer.id]),
                    { volume },
                    { preserveScroll: true },
                );
            }, 400),
        );
    }

    function renameLayer(layer: AudioLayer, name: string): void {
        if (name.trim() !== '' && name !== layer.name) {
            router.patch(
                updateLayer([remoteToken, layer.id]),
                { name: name.trim() },
                { preserveScroll: true },
            );
        }
    }

    function deleteLayer(layer: AudioLayer): void {
        if (confirm(`Delete "${layer.name}"?`)) {
            chunkCache.delete(layer.id);
            router.delete(destroyLayer([remoteToken, layer.id]), {
                preserveScroll: true,
            });
        }
    }

    async function runExport(): Promise<void> {
        if (layers.length === 0) {
            toast.error('Record at least one layer first');

            return;
        }

        stopPreview();
        videoElement?.pause();

        const exported = await exporter.export(layers, chunkCache);

        if (exported) {
            toast.success('Export complete');
            router.reload({ only: ['export'] });
        } else if (exporter.errorMessage) {
            toast.error(exporter.errorMessage);
        }
    }

    function seekTo(event: Event): void {
        const target = event.currentTarget as HTMLInputElement;

        if (videoElement) {
            videoElement.currentTime = Number(target.value);
            stopPreview();
        }
    }

    function togglePlayback(): void {
        if (!videoElement || recording) {
            return;
        }

        if (videoPlaying) {
            videoElement.pause();
        } else {
            stopPreview();
            void videoElement.play();
        }
    }

    async function onVideoEnded(): Promise<void> {
        stopPreview();

        if (recording) {
            await saveRecording();
        }
    }

    let layerReloadTimeout: ReturnType<typeof setTimeout> | null = null;

    $effect(() =>
        subscribeToProjectChannel(remoteToken, {
            onLayerUpdated: () => {
                layerReloadTimeout ??= setTimeout(() => {
                    layerReloadTimeout = null;
                    router.reload({ only: ['layers'] });
                }, 500);
            },
            onVideoStatusUpdated: () =>
                router.reload({ only: ['video', 'export'] }),
        }),
    );

    $effect(() => {
        return () => {
            recorder.stop();
            preview.stop();
            wakeLock.release();
        };
    });
</script>

<AppHead title={`Dub – ${projectName}`} />

<div
    class="dark flex min-h-dvh flex-col bg-zinc-950 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] text-zinc-100"
>
    <header class="flex items-center justify-between px-4 py-4 sm:px-6">
        <div class="min-w-0">
            <p class={microLabel}>DUB WORKSPACE</p>
            <h1 class="truncate font-display text-lg leading-snug font-bold">
                {projectName}
            </h1>
        </div>
        <p class={microLabel}>
            {layers.length}
            {layers.length === 1 ? 'LAYER' : 'LAYERS'}
        </p>
    </header>

    {#if !video}
        <main class="grid flex-1 place-items-center px-6">
            <div class="border-l-4 border-zinc-700 pl-4">
                <p class={emptyState}>NO RENDERED VIDEO YET</p>
                <p class="mt-2 text-sm text-zinc-400">
                    Render the project's video first, then come back to add
                    voices.
                </p>
            </div>
        </main>
    {:else}
        <main
            class="mx-auto w-full max-w-3xl flex-1 space-y-4 px-4 pb-6 sm:px-6"
        >
            <!-- svelte-ignore a11y_media_has_caption -->
            <video
                bind:this={videoElement}
                src={video.url}
                playsinline
                class={cn(
                    'max-h-[45dvh] w-full border border-zinc-800 bg-black',
                    aspectClass,
                )}
                onloadedmetadata={() =>
                    (videoDuration = videoElement?.duration ?? 0)}
                ontimeupdate={() =>
                    (currentTime = videoElement?.currentTime ?? 0)}
                onplay={() => (videoPlaying = true)}
                onpause={() => (videoPlaying = false)}
                onended={() => void onVideoEnded()}
            ></video>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class={btnGhostMono}
                    disabled={recording}
                    onclick={togglePlayback}
                >
                    {videoPlaying ? 'PAUSE' : 'PLAY'}
                </button>
                <input
                    type="range"
                    min="0"
                    max={videoDuration}
                    step="0.05"
                    value={currentTime}
                    disabled={recording}
                    oninput={seekTo}
                    class="min-h-11 flex-1 accent-white"
                />
                <span class={cn(microLabel, 'whitespace-nowrap')}>
                    {currentTime.toFixed(1)}S / {videoDuration.toFixed(1)}S
                </span>
            </div>

            <!-- Timeline: layer bars positioned over the video duration. -->
            {#if videoDuration > 0 && layers.length > 0}
                <div class="space-y-1">
                    {#each layers as layer (layer.id)}
                        <div class="relative h-3 bg-zinc-900">
                            <div
                                class="absolute top-0 h-full bg-blue-500/70"
                                style:left={`${Math.min(100, (layer.offset / videoDuration) * 100)}%`}
                                style:width={`${Math.min(100 - (layer.offset / videoDuration) * 100, (layer.duration / videoDuration) * 100)}%`}
                            ></div>
                        </div>
                    {/each}
                    <div class="relative h-0.5 bg-zinc-800">
                        <div
                            class="absolute top-0 h-full w-px bg-amber-400"
                            style:left={`${videoDuration > 0 ? (currentTime / videoDuration) * 100 : 0}%`}
                        ></div>
                    </div>
                </div>
            {/if}

            <div class="flex items-center justify-center gap-4 py-2">
                <button
                    type="button"
                    aria-label={recording
                        ? 'Stop recording'
                        : 'Record a voice layer'}
                    disabled={uploadingLayer || exporter.busy}
                    onclick={() => void toggleRecording()}
                    class={cn(
                        'grid h-20 w-20 touch-manipulation place-items-center rounded-full border-4 transition-colors duration-200 select-none disabled:opacity-40',
                        recording
                            ? 'animate-pulse border-red-500 bg-red-500/20'
                            : 'border-zinc-200 hover:border-red-400',
                    )}
                >
                    <span
                        class={cn(
                            'bg-red-500 transition-all duration-200',
                            recording ? 'h-7 w-7' : 'h-14 w-14 rounded-full',
                        )}
                    ></span>
                </button>
            </div>
            <p class="text-center">
                <span class={microLabel}>
                    {recording
                        ? 'RECORDING — TAP TO STOP'
                        : uploadingLayer
                          ? 'SAVING LAYER'
                          : 'TAP TO RECORD FROM THE PLAYHEAD'}
                </span>
            </p>

            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class={microLabel}>LAYERS</h2>
                    <button
                        type="button"
                        class={btnGhostMono}
                        disabled={busy || layers.length === 0}
                        onclick={() => void togglePreview()}
                    >
                        {preview.playing ? 'STOP PREVIEW' : 'PREVIEW MIX'}
                    </button>
                </div>

                {#if layers.length === 0}
                    <p class="border-l-2 border-zinc-800 py-2 pl-3">
                        <span class={emptyState}>
                            NO LAYERS YET — SEEK, THEN TAP RECORD
                        </span>
                    </p>
                {:else}
                    {#each layers as layer (layer.id)}
                        <div class="border-l-4 border-blue-500 bg-zinc-900 p-3">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <input
                                    type="text"
                                    value={layer.name}
                                    disabled={busy}
                                    onchange={(event) =>
                                        renameLayer(
                                            layer,
                                            event.currentTarget.value,
                                        )}
                                    class="min-w-0 flex-1 border-none bg-transparent text-sm font-medium focus:outline-none"
                                />
                                <button
                                    type="button"
                                    class="font-mono text-xs text-red-400 transition-colors duration-200 hover:text-red-300"
                                    disabled={busy}
                                    onclick={() => deleteLayer(layer)}
                                >
                                    DELETE
                                </button>
                            </div>

                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2"
                            >
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class={btnGhostMono}
                                        disabled={busy}
                                        onclick={() => nudgeOffset(layer, -0.1)}
                                    >
                                        −0.1S
                                    </button>
                                    <span
                                        class="font-mono text-xs text-zinc-400 tabular-nums"
                                    >
                                        AT {layer.offset.toFixed(1)}S ·
                                        {layer.duration.toFixed(1)}S
                                    </span>
                                    <button
                                        type="button"
                                        class={btnGhostMono}
                                        disabled={busy}
                                        onclick={() => nudgeOffset(layer, 0.1)}
                                    >
                                        +0.1S
                                    </button>
                                </div>

                                <label
                                    class="flex min-w-32 flex-1 items-center gap-2"
                                >
                                    <span class={microLabel}>VOL</span>
                                    <input
                                        type="range"
                                        min="0"
                                        max="1.5"
                                        step="0.05"
                                        value={layer.volume}
                                        disabled={recording || exporter.busy}
                                        oninput={(event) =>
                                            changeVolume(
                                                layer,
                                                Number(
                                                    event.currentTarget.value,
                                                ),
                                            )}
                                        class="min-h-8 flex-1 accent-white"
                                    />
                                </label>
                            </div>
                        </div>
                    {/each}
                {/if}
            </section>

            <section
                class="space-y-3 border-l-4 border-emerald-500 bg-zinc-900 p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class={microLabel}>EXPORT</h2>
                    <button
                        type="button"
                        class={btnGhostMono}
                        disabled={busy || layers.length === 0}
                        onclick={() => void runExport()}
                    >
                        {exporter.busy ? 'EXPORTING' : 'EXPORT WITH AUDIO'}
                    </button>
                </div>

                {#if exporter.busy}
                    <div>
                        <div class={hairlineTrack}>
                            <div
                                class={cn(hairlineFill, 'bg-emerald-500')}
                                style:width={`${Math.round(exporter.progress * 100)}%`}
                            ></div>
                        </div>
                        <p class={cn(microLabel, 'mt-1.5')}>
                            {exportPhaseLabel}
                        </p>
                    </div>
                {/if}

                {#if dubExport?.url}
                    <!-- svelte-ignore a11y_media_has_caption -->
                    <video
                        controls
                        playsinline
                        src={dubExport.url}
                        class={cn(
                            'max-h-64 w-full border border-zinc-800 bg-black',
                            aspectClass,
                        )}
                    ></video>
                    <a
                        href={dubExport.url}
                        class="inline-block font-mono text-xs underline underline-offset-4 hover:text-zinc-300"
                    >
                        SAVE MP4
                    </a>
                {/if}
            </section>
        </main>
    {/if}

    <Toaster />
</div>
