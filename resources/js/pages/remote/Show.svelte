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
    class="flex min-h-dvh flex-col bg-neutral-950 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] text-neutral-100 select-none"
>
    <header class="flex items-center justify-between px-5 py-4">
        <div>
            <p class="text-xs tracking-widest text-neutral-500 uppercase">
                Remote
            </p>
            <h1 class="text-lg font-semibold">{projectName}</h1>
        </div>

        <div class="flex items-center gap-2 text-sm">
            <span
                class={cn(
                    'h-2.5 w-2.5 rounded-full',
                    connected
                        ? 'bg-emerald-400'
                        : connection.state === 'connecting'
                          ? 'animate-pulse bg-amber-400'
                          : 'bg-red-500',
                )}
            ></span>
            <span class="text-neutral-400">
                {connected
                    ? 'Connected'
                    : connection.state === 'connecting'
                      ? 'Connecting…'
                      : 'Reconnecting…'}
            </span>
        </div>
    </header>

    <main class="grid flex-1 place-items-center px-6">
        <ShutterButton
            size="xl"
            disabled={!connected || commandRequest.processing}
            onpress={() => send('capture')}
        />
    </main>

    <footer class="space-y-4 px-5 pb-6">
        <div class="flex items-center justify-center gap-3">
            <select
                bind:value={intervalSeconds}
                disabled={intervalRunning}
                class="min-h-11 touch-manipulation rounded-xl border border-neutral-700 bg-neutral-900 px-3 py-2 text-sm text-neutral-200 disabled:opacity-50"
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
                    'min-h-11 touch-manipulation rounded-xl border px-5 py-2 text-sm font-medium transition disabled:opacity-40',
                    intervalRunning
                        ? 'border-red-500 bg-red-500/10 text-red-400'
                        : 'border-neutral-600 text-neutral-200',
                )}
            >
                {intervalRunning ? 'Stop interval' : 'Start interval'}
            </button>
        </div>

        <div
            class="flex items-center justify-between rounded-2xl bg-neutral-900 p-4"
        >
            <div
                class={cn(
                    'h-16 overflow-hidden rounded-lg bg-neutral-800',
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

            <div class="text-right">
                <p class="text-3xl font-semibold tabular-nums">{frameCount}</p>
                <p class="text-xs text-neutral-500">
                    frames · {fps} fps
                </p>
            </div>
        </div>
    </footer>

    <Toaster />
</div>
