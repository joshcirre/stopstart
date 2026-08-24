<script lang="ts">
    import { cn } from '@/lib/utils';
    import type { StripFrame } from '@/types/models';

    let {
        frames,
        aspectClass,
    }: {
        frames: StripFrame[];
        aspectClass: string;
    } = $props();

    let container = $state<HTMLDivElement | null>(null);

    $effect(() => {
        if (frames.length > 0 && container) {
            container.scrollTo({
                left: container.scrollWidth,
                behavior: 'smooth',
            });
        }
    });
</script>

<div bind:this={container} class="flex gap-1.5 overflow-x-auto px-1 py-2">
    {#each frames as frame (frame.key)}
        <div
            class={cn(
                'relative h-16 shrink-0 overflow-hidden ring-2',
                aspectClass,
                frame.failed
                    ? 'ring-red-500'
                    : frame.pending
                      ? 'animate-pulse ring-amber-400'
                      : 'ring-transparent',
            )}
        >
            <img
                src={frame.src}
                alt={`Frame ${frame.sequence}`}
                class="h-full w-full object-cover"
            />
            <span
                class="absolute right-0 bottom-0 bg-black/60 px-1 font-mono text-[10px] text-white tabular-nums"
            >
                {frame.sequence}
            </span>
        </div>
    {/each}
</div>
