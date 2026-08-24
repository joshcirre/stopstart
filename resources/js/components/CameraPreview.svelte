<script lang="ts">
    import CountdownRing from '@/components/CountdownRing.svelte';
    import type { Camera } from '@/lib/camera.svelte';
    import { emptyState } from '@/lib/styles';
    import { cn } from '@/lib/utils';

    let {
        camera,
        videoElement = $bindable(null),
        onionSkinSrc = null,
        onionSkinOpacity = 0,
        flash = false,
        countdown = null,
        aspectClass,
    }: {
        camera: Camera;
        videoElement?: HTMLVideoElement | null;
        onionSkinSrc?: string | null;
        onionSkinOpacity?: number;
        flash?: boolean;
        countdown?: { fraction: number; seconds: number } | null;
        aspectClass: string;
    } = $props();

    $effect(() => {
        if (videoElement && camera.status === 'active') {
            videoElement.srcObject = camera.stream;
        }
    });
</script>

<div
    class={cn(
        'relative max-h-full max-w-full overflow-hidden bg-zinc-900',
        aspectClass,
    )}
>
    <video
        bind:this={videoElement}
        autoplay
        playsinline
        muted
        class={cn(
            'h-full w-full object-cover',
            camera.mirrored && '-scale-x-100',
        )}
    ></video>

    {#if onionSkinSrc && onionSkinOpacity > 0}
        <img
            src={onionSkinSrc}
            alt="Previous frame"
            style:opacity={onionSkinOpacity}
            class={cn(
                'pointer-events-none absolute inset-0 h-full w-full object-cover',
                camera.mirrored && '-scale-x-100',
            )}
        />
    {/if}

    {#if flash}
        <div class="absolute inset-0 bg-white"></div>
    {/if}

    {#if countdown}
        <div class="absolute top-3 right-3 text-white">
            <CountdownRing
                fraction={countdown.fraction}
                seconds={countdown.seconds}
            />
        </div>
    {/if}

    {#if camera.status !== 'active'}
        <div
            class="absolute inset-0 grid place-items-center bg-zinc-950/90 p-6 text-center text-zinc-300"
        >
            {#if camera.status === 'starting' || camera.status === 'idle'}
                <p class={cn(emptyState, 'animate-pulse')}>STARTING CAMERA</p>
            {:else if camera.status === 'denied'}
                <div class="space-y-3">
                    <p class="text-sm">
                        Camera access was denied. Allow camera access for this
                        site in your browser settings, then retry.
                    </p>
                    <button
                        type="button"
                        class="bg-white px-4 py-1.5 text-sm font-medium text-zinc-900"
                        onclick={() => void camera.start()}
                    >
                        Retry
                    </button>
                </div>
            {:else}
                <div class="space-y-3">
                    <p class="text-sm">
                        {camera.errorMessage ?? 'The camera is unavailable.'}
                    </p>
                    {#if camera.status === 'error'}
                        <button
                            type="button"
                            class="bg-white px-4 py-1.5 text-sm font-medium text-zinc-900"
                            onclick={() => void camera.start()}
                        >
                            Retry
                        </button>
                    {/if}
                </div>
            {/if}
        </div>
    {/if}
</div>
