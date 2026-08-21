<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import Toaster from '@/components/Toaster.svelte';
    import { index } from '@/routes/projects';

    let { children }: { children?: Snippet } = $props();
</script>

<div class="min-h-dvh bg-neutral-100 text-neutral-900">
    <header class="border-b border-neutral-200 bg-white">
        <div
            class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3 sm:px-6"
        >
            <Link href={index()} class="text-lg font-semibold tracking-tight">
                StopStart
            </Link>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6">
        {@render children?.()}
    </main>

    <Toaster />
</div>
