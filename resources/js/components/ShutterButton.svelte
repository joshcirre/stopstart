<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        size = 'md',
        disabled = false,
        onpress,
    }: {
        size?: 'md' | 'xl';
        disabled?: boolean;
        onpress: () => void;
    } = $props();
</script>

<button
    type="button"
    aria-label="Take picture"
    {disabled}
    onclick={onpress}
    class={cn(
        'grid touch-manipulation place-items-center rounded-full border-4 border-white/90 transition select-none',
        'active:scale-95 disabled:cursor-not-allowed disabled:opacity-40',
        size === 'xl' ? 'h-44 w-44' : 'h-20 w-20',
    )}
>
    <span
        class={cn(
            'rounded-full bg-red-500',
            size === 'xl' ? 'h-36 w-36' : 'h-15 w-15',
        )}
    ></span>
</button>
