<script lang="ts">
    import { toasts } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
</script>

<div
    class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex flex-col items-center gap-2 px-4"
>
    {#each toasts as toast (toast.id)}
        <!-- Dark surface in both themes: overlays camera screens too. -->
        <div
            class={cn(
                'border-l-4 bg-zinc-900 px-4 py-2 font-mono text-xs text-zinc-100 shadow-sm',
                toast.kind === 'error'
                    ? 'border-red-500'
                    : 'border-emerald-500',
            )}
        >
            {toast.message}
        </div>
    {/each}
</div>
