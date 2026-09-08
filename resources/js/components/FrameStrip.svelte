<script lang="ts">
    import { cn } from '@/lib/utils';
    import type { StripFrame } from '@/types/models';

    let {
        frames,
        aspectClass,
        selectedKey = null,
        onselect = null,
    }: {
        frames: StripFrame[];
        aspectClass: string;
        selectedKey?: string | null;
        /** When set, each frame becomes a button that anchors capture to it. */
        onselect?: ((frame: StripFrame) => void) | null;
    } = $props();

    let container = $state<HTMLDivElement | null>(null);

    // Auto-scrolling to the end fights the user while they are picking an
    // insertion point, so it only follows new frames in append mode.
    $effect(() => {
        if (frames.length > 0 && container && selectedKey === null) {
            container.scrollTo({
                left: container.scrollWidth,
                behavior: 'smooth',
            });
        }
    });

    function ringFor(frame: StripFrame): string {
        if (frame.key === selectedKey) {
            return 'ring-amber-400';
        }

        if (frame.failed) {
            return 'ring-red-500';
        }

        return frame.pending
            ? 'animate-pulse ring-amber-400/50'
            : 'ring-transparent';
    }
</script>

{#snippet thumbnail(frame: StripFrame)}
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
{/snippet}

<div bind:this={container} class="flex gap-1.5 overflow-x-auto px-1 py-2">
    {#each frames as frame (frame.key)}
        {#if onselect}
            <button
                type="button"
                aria-pressed={frame.key === selectedKey}
                aria-label={`Resume capture from frame ${frame.sequence}`}
                disabled={frame.id === undefined}
                onclick={() => onselect(frame)}
                class={cn(
                    'relative h-16 shrink-0 touch-manipulation overflow-hidden ring-2 disabled:opacity-40',
                    aspectClass,
                    ringFor(frame),
                )}
            >
                {@render thumbnail(frame)}
            </button>
        {:else}
            <div
                class={cn(
                    'relative h-16 shrink-0 overflow-hidden ring-2',
                    aspectClass,
                    ringFor(frame),
                )}
            >
                {@render thumbnail(frame)}
            </div>
        {/if}
    {/each}
</div>
