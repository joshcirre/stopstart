<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        label,
        tone = 'neutral',
        pulse = false,
    }: {
        label: string;
        tone?: 'neutral' | 'info' | 'success' | 'danger' | 'warning';
        pulse?: boolean;
    } = $props();

    const tones = {
        neutral:
            'border-zinc-300 text-zinc-500 dark:border-zinc-600 dark:text-zinc-400',
        info: 'border-blue-500 text-blue-600 dark:text-blue-400',
        success: 'border-emerald-500 text-emerald-600 dark:text-emerald-400',
        danger: 'border-red-500 text-red-600 dark:text-red-400',
        warning: 'border-amber-400 text-amber-600 dark:text-amber-400',
    };
</script>

<!-- `uppercase` is used here (not literal caps) because labels are dynamic data. -->
<span
    class={cn(
        'inline-flex items-center border px-1.5 py-0.5 font-mono text-[10px] tracking-[0.15em] uppercase',
        tones[tone],
        pulse && 'animate-pulse',
    )}
>
    {label}
</span>
