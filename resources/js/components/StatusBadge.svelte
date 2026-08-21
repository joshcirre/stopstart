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
        neutral: 'bg-neutral-200 text-neutral-700',
        info: 'bg-sky-100 text-sky-700',
        success: 'bg-emerald-100 text-emerald-700',
        danger: 'bg-red-100 text-red-700',
        warning: 'bg-amber-100 text-amber-700',
    };
</script>

<span
    class={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        tones[tone],
        pulse && 'animate-pulse',
    )}
>
    {label}
</span>
