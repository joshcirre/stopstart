<script lang="ts" module>
    export { default as layout } from '@/layouts/AppLayout.svelte';
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import QrCode from '@/components/QrCode.svelte';
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
        select,
    } from '@/lib/styles';
    import { btnGhostMono } from '@/lib/styles';
    import { toast } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
    import { capture, update as updateProject } from '@/routes/projects';
    import {
        destroy as destroyFrame,
        move as moveFrame,
    } from '@/routes/projects/frames';
    import {
        store as storeVideo,
        upload as uploadVideo,
    } from '@/routes/projects/videos';
    import type { Frame, ProjectShowProps, Video } from '@/types';
    import { FPS_CHOICES, ORIENTATION_DIMENSIONS } from '@/types';

    let {
        project,
        frames,
        frameCount,
        video,
        renders,
        export: dubExport,
        layerCount,
        dubUrl,
    }: ProjectShowProps = $props();

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
                uploadVideo.url(project.id),
            ),
    );

    $effect(() => {
        void supportsClientRendering(dimensions.width, dimensions.height).then(
            (supported) => (clientRenderSupported = supported),
        );
    });

    async function renderInBrowser(): Promise<void> {
        const rendered = await renderer.render(frames, project.fps);

        if (rendered) {
            toast.success(`Video rendered at ${project.fps} fps`);
            selectedRenderId = null;
            router.reload({ only: ['video', 'renders'] });
        } else if (renderer.errorMessage) {
            toast.error(renderer.errorMessage);
        }
    }

    // Null tracks "whatever is newest" so a fresh render surfaces on its
    // own; picking an older render pins the player to that one.
    let selectedRenderId = $state<number | null>(null);

    const selectedRender = $derived(
        renders.find((render) => render.id === selectedRenderId) ??
            renders[0] ??
            video,
    );

    function renderTime(render: Video): string {
        return render.createdAt
            ? new Date(render.createdAt).toLocaleString(undefined, {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
              })
            : '';
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
            onVideoStatusUpdated: () =>
                router.reload({ only: ['video', 'renders', 'export'] }),
            onLayerUpdated: () => router.reload({ only: ['layerCount'] }),
        }),
    );

    function changeFps(event: Event): void {
        const fps = Number((event.currentTarget as HTMLSelectElement).value);

        router.patch(
            updateProject(project.id),
            { fps },
            { preserveScroll: true },
        );
    }

    let selectedFrameId = $state<number | null>(null);

    const selectedFrame = $derived(
        frames.find((frame) => frame.id === selectedFrameId) ?? null,
    );

    const selectedIndex = $derived(
        frames.findIndex((frame) => frame.id === selectedFrameId),
    );

    function toggleFrameSelection(frame: Frame): void {
        selectedFrameId = selectedFrameId === frame.id ? null : frame.id;
    }

    // Selection is the confirmation step: tap to select, tap DELETE.
    function deleteSelectedFrame(): void {
        if (selectedFrame) {
            router.delete(destroyFrame([project.id, selectedFrame.id]), {
                preserveScroll: true,
            });
            selectedFrameId = null;
        }
    }

    function moveSelectedFrame(direction: 'earlier' | 'later'): void {
        if (selectedFrame) {
            router.patch(
                moveFrame([project.id, selectedFrame.id]),
                { direction },
                { preserveScroll: true },
            );
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
            <div class="flex items-center gap-4">
                <h2 class={microLabel}>VIDEO</h2>
                <label class="flex items-center gap-2">
                    <span class={microLabel}>FPS</span>
                    <select
                        value={project.fps}
                        disabled={renderer.busy || videoInFlight}
                        onchange={changeFps}
                        class={cn(select, 'px-2 py-1 sm:text-xs')}
                    >
                        {#each FPS_CHOICES as fps (fps)}
                            <option value={fps}>{fps}</option>
                        {/each}
                    </select>
                </label>
            </div>

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

        {#if selectedRender}
            <div class="mt-5 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        label={`${selectedRender.status} · ${selectedRender.fps} FPS`}
                        tone={selectedRender.status === 'completed'
                            ? 'success'
                            : selectedRender.status === 'failed'
                              ? 'danger'
                              : 'warning'}
                        pulse={selectedRender.status === 'pending' ||
                            selectedRender.status === 'processing'}
                    />
                    {#if selectedRender.status === 'failed' && selectedRender.error}
                        <p
                            class="min-w-0 break-words font-mono text-xs text-red-600 dark:text-red-400"
                        >
                            {selectedRender.error}
                        </p>
                    {/if}
                </div>

                {#if selectedRender.status === 'completed' && selectedRender.url}
                    <!-- Keyed so switching renders swaps the source instead
                         of leaving the previous one loaded. -->
                    {#key selectedRender.id}
                        <!-- svelte-ignore a11y_media_has_caption -->
                        <video
                            controls
                            src={selectedRender.url}
                            class={cn(
                                'max-h-96 border border-zinc-200 bg-black dark:border-zinc-800',
                                aspectClass,
                            )}
                        ></video>
                    {/key}
                    {#if selectedRender.downloadUrl}
                        <a
                            href={selectedRender.downloadUrl}
                            class="inline-flex items-center font-mono text-xs underline underline-offset-4 hover:text-zinc-600 pointer-coarse:min-h-12 dark:hover:text-zinc-300"
                        >
                            DOWNLOAD MP4 ({selectedRender.fps} FPS)
                        </a>
                    {/if}
                {/if}
            </div>
        {/if}

        {#if renders.length > 1}
            <div class="mt-6 space-y-2">
                <p class={microLabel}>
                    RENDERS · <span class="tabular-nums">{renders.length}</span>
                </p>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    {#each renders as render (render.id)}
                        <li>
                            <button
                                type="button"
                                aria-pressed={selectedRender?.id === render.id}
                                onclick={() => (selectedRenderId = render.id)}
                                class={cn(
                                    'flex w-full flex-wrap items-center gap-x-3 gap-y-2 px-2 py-2 text-left transition-colors duration-200 hover:bg-zinc-100 sm:flex-nowrap sm:gap-3 pointer-coarse:min-h-12 dark:hover:bg-zinc-800',
                                    selectedRender?.id === render.id &&
                                        'bg-zinc-100 dark:bg-zinc-800',
                                )}
                            >
                                <span
                                    class="w-14 shrink-0 font-mono text-xs tabular-nums"
                                >
                                    {render.fps} FPS
                                </span>
                                <StatusBadge
                                    label={render.status}
                                    tone={render.status === 'completed'
                                        ? 'success'
                                        : render.status === 'failed'
                                          ? 'danger'
                                          : 'warning'}
                                />
                                <span
                                    class="order-last min-w-0 grow basis-full truncate font-mono text-[10px] tracking-[0.15em] text-zinc-400 sm:order-none sm:basis-auto dark:text-zinc-500"
                                >
                                    {renderTime(render)}
                                </span>
                                {#if selectedRender?.id === render.id}
                                    <span class={cn(microLabel, 'shrink-0')}>
                                        PLAYING
                                    </span>
                                {/if}
                            </button>
                        </li>
                    {/each}
                </ul>
                <p class={cn(microLabel, 'text-zinc-400 dark:text-zinc-500')}>
                    EACH RENDER IS KEPT — CHANGE FPS AND RENDER AGAIN TO ADD ONE
                </p>
            </div>
        {/if}
    </section>

    <section class={cn(railCard, railAccent.blue)}>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class={microLabel}>
                AUDIO · <span class="tabular-nums">{layerCount}</span>
                {layerCount === 1 ? 'LAYER' : 'LAYERS'}
            </h2>

            {#if video?.status === 'completed'}
                <a href={dubUrl} class={btnPrimary}>Open dub workspace</a>
            {:else}
                <span class={emptyState}>RENDER A VIDEO TO START DUBBING</span>
            {/if}
        </div>

        {#if video?.status === 'completed'}
            <div class="mt-4 flex items-center gap-4">
                <QrCode value={dubUrl} class="w-24 shrink-0" />
                <div class="min-w-0 space-y-2">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Scan with your phone to record character voices over the
                        rendered video.
                    </p>
                    <p
                        class="truncate font-mono text-xs text-zinc-400 dark:text-zinc-500"
                    >
                        {dubUrl}
                    </p>
                </div>
            </div>
        {/if}

        {#if dubExport?.status === 'completed' && dubExport.url}
            <div class="mt-5 space-y-3">
                <p class={microLabel}>LATEST EXPORT WITH AUDIO</p>
                <!-- svelte-ignore a11y_media_has_caption -->
                <video
                    controls
                    src={dubExport.url}
                    class={cn(
                        'max-h-96 border border-zinc-200 bg-black dark:border-zinc-800',
                        aspectClass,
                    )}
                ></video>
                {#if dubExport.downloadUrl}
                    <a
                        href={dubExport.downloadUrl}
                        class="inline-flex items-center font-mono text-xs underline underline-offset-4 hover:text-zinc-600 pointer-coarse:min-h-12 dark:hover:text-zinc-300"
                    >
                        DOWNLOAD MP4 WITH AUDIO
                    </a>
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
            {#if selectedFrame}
                <div
                    class="mb-3 flex flex-wrap items-center gap-2 border-l-4 border-amber-400 bg-white p-2 pl-3 dark:bg-zinc-900"
                >
                    <span class={monoMeta}>
                        FRAME {selectedFrame.sequence}
                    </span>
                    <button
                        type="button"
                        class={btnGhostMono}
                        disabled={selectedIndex <= 0}
                        onclick={() => moveSelectedFrame('earlier')}
                    >
                        ← MOVE
                    </button>
                    <button
                        type="button"
                        class={btnGhostMono}
                        disabled={selectedIndex >= frames.length - 1}
                        onclick={() => moveSelectedFrame('later')}
                    >
                        MOVE →
                    </button>
                    <button
                        type="button"
                        class="border border-red-500 px-3 py-1 font-mono text-[10px] tracking-[0.25em] text-red-600 transition-colors duration-200 hover:bg-red-500/10 pointer-coarse:min-h-12 dark:text-red-400"
                        onclick={deleteSelectedFrame}
                    >
                        DELETE
                    </button>
                    <button
                        type="button"
                        class={btnGhostMono}
                        onclick={() => (selectedFrameId = null)}
                    >
                        DONE
                    </button>
                </div>
            {:else}
                <p class={cn(microLabel, 'mb-3')}>
                    TAP A FRAME TO MOVE OR DELETE IT
                </p>
            {/if}

            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {#each frames as frame (frame.id)}
                    <button
                        type="button"
                        aria-pressed={selectedFrameId === frame.id}
                        aria-label={`Select frame ${frame.sequence}`}
                        class={cn(
                            'relative touch-manipulation overflow-hidden bg-zinc-200 dark:bg-zinc-800',
                            aspectClass,
                            selectedFrameId === frame.id
                                ? 'ring-2 ring-amber-400'
                                : 'ring-2 ring-transparent',
                        )}
                        onclick={() => toggleFrameSelection(frame)}
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
                    </button>
                {/each}
            </div>
        {/if}
    </section>
</div>
