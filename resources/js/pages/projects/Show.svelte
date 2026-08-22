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
        fetching: 'Downloading frames…',
        encoding: 'Encoding…',
        uploading: 'Uploading…',
    } as const;

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

<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{project.name}</h1>
            <div class="mt-1 flex items-center gap-1.5">
                <StatusBadge
                    label={project.orientation === 'landscape'
                        ? 'Landscape 1920×1080'
                        : 'Portrait 1080×1920'}
                />
                <StatusBadge label={`${project.fps} fps`} />
                <span class="text-sm text-neutral-500 tabular-nums">
                    {frameCount}
                    {frameCount === 1 ? 'frame' : 'frames'}
                </span>
            </div>
        </div>

        <Link
            href={capture(project.id)}
            class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white"
        >
            Open capture
        </Link>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Video</h2>

            {#if clientRenderSupported}
                <div class="flex flex-col items-end gap-1">
                    <button
                        type="button"
                        disabled={renderer.busy || frameCount === 0}
                        onclick={() => void renderInBrowser()}
                        class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {renderer.busy
                            ? (renderPhaseLabels[
                                  renderer.phase as keyof typeof renderPhaseLabels
                              ] ?? 'Rendering…')
                            : `Render video (${project.fps} fps)`}
                    </button>
                    <span class="text-xs text-neutral-400">
                        renders in your browser
                    </span>
                </div>
            {:else}
                <Form action={storeVideo(project.id)}>
                    {#snippet children({ errors, processing })}
                        <div class="flex flex-col items-end gap-1">
                            {#if errors.frames || errors.video}
                                <p class="text-sm text-red-600">
                                    {errors.frames ?? errors.video}
                                </p>
                            {/if}
                            <button
                                type="submit"
                                disabled={processing ||
                                    videoInFlight ||
                                    frameCount === 0}
                                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                Render video ({project.fps} fps)
                            </button>
                            <span class="text-xs text-neutral-400">
                                renders on the server
                            </span>
                        </div>
                    {/snippet}
                </Form>
            {/if}
        </div>

        {#if renderer.busy}
            <div class="mt-4">
                <div class="h-2 overflow-hidden rounded-full bg-neutral-200">
                    <div
                        class="h-full rounded-full bg-neutral-900 transition-[width] duration-150"
                        style:width={`${Math.round(renderer.progress * 100)}%`}
                    ></div>
                </div>
                <p class="mt-1 text-xs text-neutral-500">
                    {renderPhaseLabels[
                        renderer.phase as keyof typeof renderPhaseLabels
                    ] ?? 'Rendering…'}
                </p>
            </div>
        {/if}

        {#if video}
            <div class="mt-4 space-y-3">
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
                        <p class="text-sm text-red-600">{video.error}</p>
                    {/if}
                </div>

                {#if video.status === 'completed' && video.url}
                    <!-- svelte-ignore a11y_media_has_caption -->
                    <video
                        controls
                        src={video.url}
                        class={cn('max-h-96 rounded-lg bg-black', aspectClass)}
                    ></video>
                    {#if video.downloadUrl}
                        <a
                            href={video.downloadUrl}
                            class="inline-block text-sm font-medium text-neutral-700 underline"
                        >
                            Download MP4
                        </a>
                    {/if}
                {/if}
            </div>
        {/if}
    </section>

    <section>
        <h2 class="mb-3 text-base font-semibold">Frames</h2>

        {#if frames.length === 0}
            <p
                class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-sm text-neutral-500"
            >
                No frames yet — open capture to start shooting.
            </p>
        {:else}
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {#each frames as frame (frame.id)}
                    <div
                        class={cn(
                            'group relative overflow-hidden rounded-lg bg-neutral-200',
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
                            class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 text-xs text-white tabular-nums"
                        >
                            {frame.sequence}
                        </span>
                        <button
                            type="button"
                            aria-label={`Delete frame ${frame.sequence}`}
                            class="absolute top-1 right-1 rounded bg-black/60 px-1.5 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100 focus:opacity-100"
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
