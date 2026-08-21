<script lang="ts">
    import { renderSVG } from 'uqr';
    import { cn } from '@/lib/utils';

    let {
        value,
        class: className = '',
    }: {
        value: string;
        class?: string;
    } = $props();

    const svg = $derived(renderSVG(value, { ecc: 'M', border: 2 }));
</script>

<div
    class={cn(
        'rounded-lg bg-white p-2 [&_svg]:h-auto [&_svg]:w-full',
        className,
    )}
>
    <!-- eslint-disable-next-line svelte/no-at-html-tags -- self-generated SVG from uqr, not user markup -->
    {@html svg}
</div>
