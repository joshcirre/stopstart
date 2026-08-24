<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        fraction,
        seconds,
        class: className = '',
    }: {
        fraction: number;
        seconds: number;
        class?: string;
    } = $props();

    const radius = 26;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = $derived(circumference * (1 - fraction));
</script>

<div class={cn('relative h-16 w-16', className)}>
    <svg viewBox="0 0 60 60" class="h-full w-full -rotate-90">
        <circle
            cx="30"
            cy="30"
            r={radius}
            fill="none"
            stroke="currentColor"
            stroke-opacity="0.2"
            stroke-width="4"
        />
        <circle
            cx="30"
            cy="30"
            r={radius}
            fill="none"
            stroke="currentColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-dasharray={circumference}
            stroke-dashoffset={dashOffset}
            style="transition: stroke-dashoffset 100ms linear"
        />
    </svg>
    <span
        class="absolute inset-0 grid place-items-center font-mono text-xl font-semibold tabular-nums"
    >
        {seconds}
    </span>
</div>
