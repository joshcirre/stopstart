<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import CameraPreview from '@/components/CameraPreview.svelte';
    import FrameStrip from '@/components/FrameStrip.svelte';
    import QrCode from '@/components/QrCode.svelte';
    import ShutterButton from '@/components/ShutterButton.svelte';
    import Toaster from '@/components/Toaster.svelte';
    import { Camera } from '@/lib/camera.svelte';
    import { subscribeToProjectChannel } from '@/lib/echo.svelte';
    import { playShutterBeep } from '@/lib/feedback';
    import { FrameUploader } from '@/lib/frame-uploader.svelte';
    import { IntervalTimer } from '@/lib/interval-timer.svelte';
    import { toast } from '@/lib/toast.svelte';
    import { createWakeLock } from '@/lib/wake-lock';
    import { show } from '@/routes/projects';
    import { store as storeFrame } from '@/routes/projects/frames';
    import type {
        FrameStoreResponse,
        ProjectCaptureProps,
        StripFrame,
    } from '@/types';
    import { INTERVAL_CHOICES } from '@/types';

    let {
        project,
        frames,
        frameCount: initialFrameCount,
        remoteUrl,
        videoInFlight,
    }: ProjectCaptureProps = $props();

    const aspectClass = $derived(
        project.orientation === 'landscape' ? 'aspect-video' : 'aspect-[9/16]',
    );

    // Dimensions, seed values, and the upload URL are intentionally captured
    // once: these pages fully reload per project, and live updates flow
    // through Echo rather than prop changes.
    const camera = untrack(() => new Camera(project.width, project.height));

    let videoElement = $state<HTMLVideoElement | null>(null);
    let frameCount = $state(untrack(() => initialFrameCount));
    let strip = $state<StripFrame[]>(
        untrack(() =>
            frames.map((frame) => ({
                key: `frame-${frame.id}`,
                src: frame.thumbnailUrl,
                sequence: frame.sequence,
            })),
        ),
    );
    let captureInFlight = $state(false);
    let onionSkinEnabled = $state(true);
    let onionSkinOpacity = $state(0.4);
    let flash = $state(false);
    let showPairing = $state(false);
    let nextClientSequence = $derived(
        (strip.at(-1)?.sequence ?? frameCount) + 1,
    );

    const uploader = new FrameUploader(
        untrack(() => storeFrame.url(project.id)),
        onFrameUploaded,
        onFrameFailed,
    );

    const timer = new IntervalTimer(() => void captureFrame());

    const wakeLock = createWakeLock();

    const onionSkinSrc = $derived(
        onionSkinEnabled ? (strip.at(-1)?.src ?? null) : null,
    );

    function onFrameUploaded(key: string, response: FrameStoreResponse): void {
        const entry = strip.find((frame) => frame.key === key);

        if (entry) {
            entry.pending = false;
            entry.sequence = response.frame.sequence;
        }

        frameCount = response.frameCount;
    }

    function onFrameFailed(key: string, message: string): void {
        const entry = strip.find((frame) => frame.key === key);

        if (entry) {
            entry.pending = false;
            entry.failed = true;
        }

        toast.error(`Frame upload failed: ${message}`);
    }

    async function captureFrame(): Promise<void> {
        if (
            captureInFlight ||
            camera.status !== 'active' ||
            videoElement === null
        ) {
            return;
        }

        captureInFlight = true;

        try {
            flash = true;
            setTimeout(() => (flash = false), 150);
            playShutterBeep();

            const blob = await camera.captureBlob(videoElement);
            const key = `capture-${Date.now()}-${Math.random().toString(36).slice(2)}`;

            strip.push({
                key,
                src: URL.createObjectURL(blob),
                sequence: nextClientSequence,
                pending: true,
            });

            uploader.enqueue(key, blob);
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Capture failed',
            );
        } finally {
            captureInFlight = false;
        }
    }

    function toggleInterval(): void {
        if (timer.running) {
            timer.stop();
        } else {
            timer.start();
        }
    }

    function handleKeydown(event: KeyboardEvent): void {
        const target = event.target as HTMLElement;

        if (
            ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(target.tagName)
        ) {
            return;
        }

        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            void captureFrame();
        }
    }

    $effect(() => {
        void camera.start();

        return () => camera.stop();
    });

    $effect(() => {
        return () => timer.stop();
    });

    $effect(() => {
        if (!timer.running) {
            return;
        }

        void wakeLock.acquire();

        return () => wakeLock.release();
    });

    $effect(() =>
        subscribeToProjectChannel(project.remoteToken, {
            onRemoteCommand: (event) => {
                if (event.command === 'capture') {
                    void captureFrame();
                } else if (event.command === 'interval-start') {
                    timer.start(event.intervalSeconds ?? undefined);
                } else if (event.command === 'interval-stop') {
                    timer.stop();
                }
            },
            onVideoStatusUpdated: () =>
                router.reload({ only: ['videoInFlight'] }),
        }),
    );
</script>

<svelte:window onkeydown={handleKeydown} />

<AppHead title={`Capture – ${project.name}`} />

<div
    class="dark flex h-dvh flex-col bg-zinc-950 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] text-zinc-100"
>
    <header class="flex items-center justify-between gap-3 px-4 py-3">
        <Link
            href={show(project.id)}
            class="font-mono text-xs text-zinc-400 transition-colors duration-200 hover:text-zinc-100"
        >
            ← {project.name}
        </Link>

        <div class="flex items-center gap-3">
            <span class="font-mono text-sm text-zinc-300 tabular-nums">
                #{frameCount}
                {#if uploader.inFlight}
                    <span class="text-amber-400">↑</span>
                {/if}
            </span>
            <button
                type="button"
                class="border border-zinc-700 px-3 py-1 font-mono text-[10px] tracking-[0.25em] text-zinc-300 transition-colors duration-200 hover:border-zinc-500"
                onclick={() => (showPairing = !showPairing)}
            >
                REMOTE
            </button>
        </div>
    </header>

    {#if showPairing}
        <div
            class="mx-4 mb-2 flex items-center gap-4 border-l-4 border-blue-500 bg-zinc-900 p-4"
        >
            <QrCode value={remoteUrl} class="w-28 shrink-0" />
            <div class="min-w-0 space-y-2 text-sm">
                <p class="text-zinc-300">
                    Scan with your phone to use it as a remote shutter.
                </p>
                <p class="truncate font-mono text-xs text-zinc-500">
                    {remoteUrl}
                </p>
                <button
                    type="button"
                    class="border border-zinc-700 px-3 py-1 font-mono text-[10px] tracking-[0.25em] text-zinc-300 transition-colors duration-200 hover:border-zinc-500"
                    onclick={() => {
                        void navigator.clipboard?.writeText(remoteUrl);
                        toast.success('Remote link copied');
                    }}
                >
                    COPY LINK
                </button>
            </div>
        </div>
    {/if}

    <main class="grid min-h-0 flex-1 place-items-center px-4">
        <CameraPreview
            {camera}
            bind:videoElement
            {onionSkinSrc}
            {onionSkinOpacity}
            {flash}
            countdown={timer.running
                ? {
                      fraction: timer.fraction,
                      seconds: timer.secondsRemaining,
                  }
                : null}
            {aspectClass}
        />
    </main>

    {#if camera.devices.length > 1}
        <div class="flex justify-center px-4 pt-2">
            <select
                class="border border-zinc-700 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-300"
                value={camera.activeDeviceId}
                onchange={(event) =>
                    void camera.switchTo(event.currentTarget.value)}
            >
                {#each camera.devices as device (device.deviceId)}
                    <option value={device.deviceId}>
                        {device.label || 'Camera'}
                    </option>
                {/each}
            </select>
        </div>
    {/if}

    <footer class="space-y-1 px-4 pt-3 pb-2">
        <div class="flex items-center justify-between gap-4">
            <div class="flex w-40 flex-col gap-1">
                <label
                    class="flex items-center gap-2 font-mono text-[10px] tracking-[0.25em] text-zinc-400"
                >
                    <input
                        type="checkbox"
                        bind:checked={onionSkinEnabled}
                        class="accent-white"
                    />
                    ONION SKIN
                </label>
                <input
                    type="range"
                    min="0.1"
                    max="0.8"
                    step="0.05"
                    bind:value={onionSkinOpacity}
                    disabled={!onionSkinEnabled}
                    class="accent-white disabled:opacity-30"
                />
            </div>

            <ShutterButton
                onpress={() => void captureFrame()}
                disabled={camera.status !== 'active'}
            />

            <div class="flex w-40 flex-col items-end gap-1.5">
                <select
                    bind:value={timer.intervalSeconds}
                    disabled={timer.running}
                    class="border border-zinc-700 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-300 disabled:opacity-50"
                >
                    {#each INTERVAL_CHOICES as seconds (seconds)}
                        <option value={seconds}>every {seconds}s</option>
                    {/each}
                </select>
                <button
                    type="button"
                    disabled={videoInFlight || camera.status !== 'active'}
                    onclick={toggleInterval}
                    class="border px-4 py-1.5 font-mono text-xs tracking-[0.15em] transition-colors duration-200 disabled:opacity-40 {timer.running
                        ? 'border-red-500 bg-red-500/10 text-red-400'
                        : 'border-zinc-600 text-zinc-200'}"
                >
                    {timer.running ? 'STOP INTERVAL' : 'START INTERVAL'}
                </button>
            </div>
        </div>

        {#if videoInFlight}
            <p
                class="text-center font-mono text-[10px] tracking-[0.25em] text-amber-400"
            >
                RENDERING IN PROGRESS — INTERVAL PAUSED
            </p>
        {/if}

        <FrameStrip frames={strip} {aspectClass} />
    </footer>

    <Toaster />
</div>
