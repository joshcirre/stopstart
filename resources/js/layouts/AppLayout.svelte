<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';
    import Toaster from '@/components/Toaster.svelte';
    import { index } from '@/routes/projects';

    let { children }: { children?: Snippet } = $props();
</script>

<div
    class="min-h-dvh bg-stone-100 pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100"
>
    <header
        class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"
    >
        <div
            class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3 sm:px-6"
        >
            <Link
                href={index()}
                class="relative touch-manipulation font-mono text-xs font-bold tracking-[0.25em]"
            >
                <span
                    class="absolute top-1/2 left-0 h-12 w-full -translate-y-1/2 pointer-fine:hidden"
                    aria-hidden="true"
                ></span>
                STOPSTART
            </Link>

            <ThemeToggle />
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6">
        {@render children?.()}
    </main>

    <Toaster />
</div>
