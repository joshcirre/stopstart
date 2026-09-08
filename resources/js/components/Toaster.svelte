<script lang="ts">
    import { toasts } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
</script>

<div
    class="pointer-events-none fixed inset-x-0 bottom-[max(1.5rem,env(safe-area-inset-bottom))] z-50 flex flex-col items-center gap-2 pr-[max(1rem,env(safe-area-inset-right))] pl-[max(1rem,env(safe-area-inset-left))]"
>
    {#each toasts as toast (toast.id)}
        <!-- Dark surface in both themes: overlays camera screens too. -->
        <div
            class={cn(
                'max-w-full border-l-4 bg-zinc-900 px-4 py-2 font-mono text-xs wrap-anywhere text-zinc-100 shadow-sm',
                toast.kind === 'error'
                    ? 'border-red-500'
                    : 'border-emerald-500',
            )}
        >
            {toast.message}
        </div>
    {/each}
</div>
