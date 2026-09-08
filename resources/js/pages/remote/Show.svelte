<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShutterButton from '@/components/ShutterButton.svelte';
    import Toaster from '@/components/Toaster.svelte';
    import {
        subscribeToProjectChannel,
        watchConnection,
    } from '@/lib/echo.svelte';
    import { toast } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
    import { command as commandRoute } from '@/routes/remote';
    import type { RemoteCommand, RemoteShowProps } from '@/types';
    import { INTERVAL_CHOICES } from '@/types';

    let {
        projectName,
        remoteToken,
        orientation,
        fps,
        frameCount: initialFrameCount,
        lastFrameThumbnailUrl,
    }: RemoteShowProps = $props();

    const aspectClass = $derived(
        orientation === 'landscape' ? 'aspect-video' : 'aspect-[9/16]',
    );

    // Seed values are intentionally captured once; Echo events keep them live.
    let frameCount = $state(untrack(() => initialFrameCount));
    let thumbnailUrl = $state(untrack(() => lastFrameThumbnailUrl));
    let intervalSeconds = $state(5);
    let intervalRunning = $state(false);

    const connection = watchConnection();

    const connected = $derived(connection.state === 'connected');

    const commandRequest = useHttp<{
        command: RemoteCommand;
        intervalSeconds: number | null;
    }>({ command: 'capture', intervalSeconds: null });

    function send(
        command: RemoteCommand,
        seconds: number | null = null,
        onSuccess?: () => void,
    ): void {
        commandRequest.setStore({ command, intervalSeconds: seconds });

        commandRequest.post(commandRoute(remoteToken).url, {
            onSuccess,
            onError: () => toast.error('Command failed — try again'),
        });
    }

    function toggleInterval(): void {
        if (intervalRunning) {
            send('interval-stop', null, () => (intervalRunning = false));
        } else {
            send('interval-start', intervalSeconds, () => {
                intervalRunning = true;
            });
        }
    }

    $effect(() =>
        subscribeToProjectChannel(remoteToken, {
            onFrameCaptured: (event) => {
                frameCount = event.frameCount;
                thumbnailUrl = event.thumbnailUrl;
            },
        }),
    );
</script>

<AppHead title={`Remote – ${projectName}`} />

<div
    class="dark flex min-h-dvh flex-col bg-zinc-950 pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] text-zinc-100 select-none"
>
    <header class="flex items-center justify-between gap-3 px-5 py-4">
        <div class="min-w-0">
            <p class="font-mono text-[10px] tracking-[0.25em] text-zinc-500">
                REMOTE
            </p>
            <h1 class="truncate font-display text-lg leading-snug font-bold">
                {projectName}
            </h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 text-sm">
            <span
                class={cn(
                    'h-2.5 w-2.5 shrink-0 rounded-full',
                    connected
                        ? 'animate-pulse bg-emerald-400'
                        : connection.state === 'connecting'
                          ? 'animate-pulse bg-amber-400'
                          : 'bg-red-500',
                )}
            ></span>
            <span class="font-mono text-[10px] tracking-[0.25em] text-zinc-400">
                {connected
                    ? 'CONNECTED'
                    : connection.state === 'connecting'
                      ? 'CONNECTING'
                      : 'RECONNECTING'}
            </span>
        </div>
    </header>

    <main class="grid flex-1 place-items-center px-6 py-6">
        <ShutterButton
            size="xl"
            disabled={!connected || commandRequest.processing}
            onpress={() => send('capture')}
        />
    </main>

    <footer class="space-y-4 px-5 pb-6">
        <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3">
            <select
                bind:value={intervalSeconds}
                disabled={intervalRunning}
                aria-label="Capture interval"
                class="min-h-11 touch-manipulation border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-base text-zinc-200 disabled:opacity-50 sm:text-xs pointer-coarse:min-h-12 pointer-coarse:text-base"
            >
                {#each INTERVAL_CHOICES as seconds (seconds)}
                    <option value={seconds}>every {seconds}s</option>
                {/each}
            </select>

            <button
                type="button"
                disabled={!connected}
                onclick={toggleInterval}
                class={cn(
                    'min-h-11 touch-manipulation border px-3 py-2 font-mono text-xs tracking-[0.15em] transition-colors duration-200 disabled:opacity-40 sm:px-5 pointer-coarse:min-h-12',
                    intervalRunning
                        ? 'border-red-500 bg-red-500/10 text-red-400'
                        : 'border-zinc-600 text-zinc-200',
                )}
            >
                {intervalRunning ? 'STOP INTERVAL' : 'START INTERVAL'}
            </button>
        </div>

        <div
            class={cn(
                'flex items-center justify-between gap-3 border-l-4 bg-zinc-900 p-4',
                connected ? 'border-emerald-500' : 'border-zinc-700',
            )}
        >
            <div
                class={cn(
                    'h-16 shrink-0 overflow-hidden bg-zinc-800',
                    aspectClass,
                )}
            >
                {#if thumbnailUrl}
                    <img
                        src={thumbnailUrl}
                        alt="Last captured frame"
                        class="h-full w-full object-cover"
                    />
                {/if}
            </div>

            <div class="min-w-0 text-right">
                <p class="font-mono text-3xl font-semibold tabular-nums">
                    {frameCount}
                </p>
                <p
                    class="font-mono text-[10px] tracking-[0.25em] text-zinc-500"
                >
                    FRAMES · {fps} FPS
                </p>
            </div>
        </div>
    </footer>

    <Toaster />
</div>
