<script lang="ts">
    import { toasts } from '@/lib/toast.svelte';
    import { cn } from '@/lib/utils';
</script>

<div
    class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex flex-col items-center gap-2 px-4"
>
    {#each toasts as toast (toast.id)}
        <div
            class={cn(
                'rounded-full px-4 py-2 text-sm font-medium text-white shadow-lg',
                toast.kind === 'error' ? 'bg-red-600' : 'bg-emerald-600',
            )}
        >
            {toast.message}
        </div>
    {/each}
</div>
