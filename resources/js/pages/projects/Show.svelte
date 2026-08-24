<script lang="ts" module>
    export { default as layout } from '@/layouts/AppLayout.svelte';
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import {
        ClientRenderer,
        supportsClientRendering,
    } from '@/lib/client-renderer.svelte';
    import { subscribeToProjectChannel } from '@/lib/echo.svelte';
    import {
        btnPrimary,
        displayHeading,
        emptyState,
        hairlineFill,
        hairlineTrack,
        headerRail,
        microLabel,
        monoMeta,
        railAccent,
        railCard,
    } from '@/lib/styles';
    import { toast } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
    import { capture } from '@/routes/projects';
    import { destroy as destroyFrame } from '@/routes/projects/frames';
    import {
        store as storeVideo,
        upload as uploadVideo,
    } from '@/routes/projects/videos';
    import type { Frame, ProjectShowProps } from '@/types';
    import { ORIENTATION_DIMENSIONS } from '@/types';

    let { project, frames, frameCount, video }: ProjectShowProps = $props();

    const aspectClass = $derived(
        project.orientation === 'landscape' ? 'aspect-video' : 'aspect-[9/16]',
    );

    const videoInFlight = $derived(
        video?.status === 'pending' || video?.status === 'processing',
    );

    // The project's identity never changes on this page; captured once.
    const dimensions = untrack(
        () => ORIENTATION_DIMENSIONS[project.orientation],
    );

    let clientRenderSupported = $state(false);

    const renderer = untrack(
        () =>
            new ClientRenderer(
                dimensions.width,
                dimensions.height,
                project.fps,
                uploadVideo.url(project.id),
            ),
    );

    $effect(() => {
        void supportsClientRendering(dimensions.width, dimensions.height).then(
            (supported) => (clientRenderSupported = supported),
        );
    });

    async function renderInBrowser(): Promise<void> {
        const rendered = await renderer.render(frames);

        if (rendered) {
            toast.success('Video rendered');
            router.reload({ only: ['video'] });
        } else if (renderer.errorMessage) {
            toast.error(renderer.errorMessage);
        }
    }

    const renderPhaseLabels = {
        fetching: 'DOWNLOADING FRAMES',
        encoding: 'ENCODING',
        uploading: 'UPLOADING',
    } as const;

    const renderPhaseLabel = $derived(
        renderPhaseLabels[renderer.phase as keyof typeof renderPhaseLabels] ??
            'RENDERING',
    );

    let reloadTimeout: ReturnType<typeof setTimeout> | null = null;
    let lastReloadAt = 0;

    function throttledFrameReload(): void {
        const run = () => {
            lastReloadAt = Date.now();
            router.reload({ only: ['frames', 'frameCount'] });
        };

        if (Date.now() - lastReloadAt > 2000) {
            run();
        } else if (!reloadTimeout) {
            reloadTimeout = setTimeout(() => {
                reloadTimeout = null;
                run();
            }, 2000);
        }
    }

    $effect(() =>
        subscribeToProjectChannel(project.remoteToken, {
            onFrameCaptured: throttledFrameReload,
            onVideoStatusUpdated: () => router.reload({ only: ['video'] }),
        }),
    );

    function deleteFrame(frame: Frame): void {
        if (confirm(`Delete frame ${frame.sequence}?`)) {
            router.delete(destroyFrame([project.id, frame.id]), {
                preserveScroll: true,
            });
        }
    }
</script>

<AppHead title={project.name} />

<div class="space-y-8 sm:space-y-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class={cn(headerRail, 'min-w-0')}>
            <p class={cn(microLabel, 'mb-2')}>PROJECT</p>
            <h1 class={cn(displayHeading, 'truncate')}>{project.name}</h1>
            <p class={cn(monoMeta, 'mt-2')}>
                {dimensions.width}×{dimensions.height} · {project.fps} FPS · {frameCount}
                {frameCount === 1 ? 'FRAME' : 'FRAMES'}
            </p>
        </div>

        <Link href={capture(project.id)} class={btnPrimary}>Open capture</Link>
    </div>

    <section class={cn(railCard, railAccent.amber)}>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class={microLabel}>VIDEO</h2>

            {#if clientRenderSupported}
                <div class="flex flex-col items-end gap-1.5">
                    <button
                        type="button"
                        disabled={renderer.busy || frameCount === 0}
                        onclick={() => void renderInBrowser()}
                        class={btnPrimary}
                    >
                        {renderer.busy
                            ? 'Rendering…'
                            : `Render video (${project.fps} fps)`}
                    </button>
                    <span class={microLabel}>RENDERS IN YOUR BROWSER</span>
                </div>
            {:else}
                <Form action={storeVideo(project.id)}>
                    {#snippet children({ errors, processing })}
                        <div class="flex flex-col items-end gap-1.5">
                            {#if errors.frames || errors.video}
                                <p
                                    class="font-mono text-xs text-red-600 dark:text-red-400"
                                >
                                    {errors.frames ?? errors.video}
                                </p>
                            {/if}
                            <button
                                type="submit"
                                disabled={processing ||
                                    videoInFlight ||
                                    frameCount === 0}
                                class={btnPrimary}
                            >
                                Render video ({project.fps} fps)
                            </button>
                            <span class={microLabel}>RENDERS ON THE SERVER</span
                            >
                        </div>
                    {/snippet}
                </Form>
            {/if}
        </div>

        {#if renderer.busy}
            <div class="mt-5">
                <div class={hairlineTrack}>
                    <div
                        class={cn(hairlineFill, 'bg-blue-500')}
                        style:width={`${Math.round(renderer.progress * 100)}%`}
                    ></div>
                </div>
                <p class={cn(microLabel, 'mt-1.5')}>{renderPhaseLabel}</p>
            </div>
        {/if}

        {#if video}
            <div class="mt-5 space-y-3">
                <div class="flex items-center gap-2">
                    <StatusBadge
                        label={video.status}
                        tone={video.status === 'completed'
                            ? 'success'
                            : video.status === 'failed'
                              ? 'danger'
                              : 'warning'}
                        pulse={videoInFlight}
                    />
                    {#if video.status === 'failed' && video.error}
                        <p
                            class="font-mono text-xs text-red-600 dark:text-red-400"
                        >
                            {video.error}
                        </p>
                    {/if}
                </div>

                {#if video.status === 'completed' && video.url}
                    <!-- svelte-ignore a11y_media_has_caption -->
                    <video
                        controls
                        src={video.url}
                        class={cn(
                            'max-h-96 border border-zinc-200 bg-black dark:border-zinc-800',
                            aspectClass,
                        )}
                    ></video>
                    {#if video.downloadUrl}
                        <a
                            href={video.downloadUrl}
                            class="inline-block font-mono text-xs underline underline-offset-4 hover:text-zinc-600 dark:hover:text-zinc-300"
                        >
                            DOWNLOAD MP4
                        </a>
                    {/if}
                {/if}
            </div>
        {/if}
    </section>

    <section>
        <h2 class={cn(microLabel, 'mb-3')}>
            FRAMES · <span class="tabular-nums">{frameCount}</span>
        </h2>

        {#if frames.length === 0}
            <p
                class="border-l-4 border-zinc-200 py-4 pl-4 dark:border-zinc-700"
            >
                <span class={emptyState}>
                    NO FRAMES YET — OPEN CAPTURE TO START SHOOTING
                </span>
            </p>
        {:else}
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {#each frames as frame (frame.id)}
                    <div
                        class={cn(
                            'group relative overflow-hidden bg-zinc-200 dark:bg-zinc-800',
                            aspectClass,
                        )}
                    >
                        <img
                            src={frame.thumbnailUrl}
                            alt={`Frame ${frame.sequence}`}
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                        <span
                            class="absolute bottom-1 left-1 bg-black/60 px-1.5 font-mono text-[10px] text-white tabular-nums"
                        >
                            {frame.sequence}
                        </span>
                        <button
                            type="button"
                            aria-label={`Delete frame ${frame.sequence}`}
                            class="absolute top-1 right-1 bg-black/60 px-1.5 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100 focus:opacity-100"
                            onclick={() => deleteFrame(frame)}
                        >
                            ✕
                        </button>
                    </div>
                {/each}
            </div>
        {/if}
    </section>
</div>
