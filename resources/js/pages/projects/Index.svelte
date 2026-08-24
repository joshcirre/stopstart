<script lang="ts" module>
    export { default as layout } from '@/layouts/AppLayout.svelte';
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import {
        btnDangerText,
        btnPrimary,
        displayHeading,
        emptyState,
        headerRail,
        input,
        microLabel,
        monoMeta,
        railAccent,
        select,
    } from '@/lib/styles';
    import { cn } from '@/lib/utils';
    import { capture, destroy, show, store } from '@/routes/projects';
    import type { ProjectsIndexProps, ProjectSummary } from '@/types';
    import { FPS_CHOICES } from '@/types';

    let { projects }: ProjectsIndexProps = $props();

    const statusRails = {
        pending: railAccent.blue,
        processing: railAccent.amber,
        completed: railAccent.emerald,
        failed: railAccent.red,
    } as const;

    function railFor(project: ProjectSummary): string {
        return project.videoStatus
            ? statusRails[project.videoStatus]
            : railAccent.muted;
    }

    function deleteProject(project: ProjectSummary): void {
        if (
            confirm(
                `Delete "${project.name}" and all of its frames and videos?`,
            )
        ) {
            router.delete(destroy(project.id));
        }
    }
</script>

<AppHead title="Projects" />

<div class="space-y-8 sm:space-y-10">
    <div class={headerRail}>
        <p class={cn(microLabel, 'mb-2')}>STOP MOTION</p>
        <h1 class={displayHeading}>Projects</h1>
    </div>

    <section
        class={cn(
            'border-l-4 bg-white p-4 shadow-sm sm:p-6 dark:bg-zinc-900',
            railAccent.amber,
        )}
    >
        <h2 class={cn(microLabel, 'mb-4')}>NEW PROJECT</h2>

        <Form action={store()} resetOnSuccess>
            {#snippet children({ errors, processing })}
                <div class="flex flex-wrap items-end gap-4 sm:gap-6">
                    <label class="flex min-w-48 flex-1 flex-col gap-1.5">
                        <span class={microLabel}>NAME</span>
                        <input
                            type="text"
                            name="name"
                            required
                            placeholder="Lego walk cycle"
                            class={input}
                        />
                    </label>

                    <fieldset class="flex gap-2">
                        <legend class={cn(microLabel, 'mb-1.5')}>
                            ORIENTATION
                        </legend>
                        {#each [{ value: 'landscape', label: 'Landscape', hint: '1920×1080' }, { value: 'portrait', label: 'Portrait', hint: '1080×1920' }] as option (option.value)}
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="orientation"
                                    value={option.value}
                                    checked={option.value === 'landscape'}
                                    class="peer sr-only"
                                />
                                <span
                                    class="flex flex-col border border-zinc-300 px-3 py-1.5 text-sm peer-checked:border-zinc-900 peer-checked:bg-zinc-900 peer-checked:text-white dark:border-zinc-700 dark:peer-checked:border-zinc-100 dark:peer-checked:bg-zinc-100 dark:peer-checked:text-zinc-900"
                                >
                                    {option.label}
                                    <span
                                        class="font-mono text-[10px] opacity-70 tabular-nums"
                                    >
                                        {option.hint}
                                    </span>
                                </span>
                            </label>
                        {/each}
                    </fieldset>

                    <label class="flex flex-col gap-1.5">
                        <span class={microLabel}>FPS</span>
                        <select name="fps" class={select}>
                            {#each FPS_CHOICES as fps (fps)}
                                <option value={fps} selected={fps === 12}>
                                    {fps}
                                </option>
                            {/each}
                        </select>
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        class={btnPrimary}
                    >
                        {processing ? 'Creating…' : 'Create project'}
                    </button>
                </div>

                {#if errors.name || errors.orientation || errors.fps}
                    <p
                        class="mt-3 font-mono text-xs text-red-600 dark:text-red-400"
                    >
                        {errors.name ?? errors.orientation ?? errors.fps}
                    </p>
                {/if}
            {/snippet}
        </Form>
    </section>

    <section>
        <h2 class={cn(microLabel, 'mb-3')}>YOUR PROJECTS</h2>

        {#if projects.length === 0}
            <p
                class="border-l-4 border-zinc-200 py-4 pl-4 dark:border-zinc-700"
            >
                <span class={emptyState}>
                    NO PROJECTS YET — CREATE ONE ABOVE
                </span>
            </p>
        {:else}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {#each projects as project (project.id)}
                    <div
                        class={cn(
                            'border-l-4 bg-white shadow-sm transition-colors duration-200 dark:bg-zinc-900',
                            railFor(project),
                        )}
                    >
                        <Link
                            href={show(project.id)}
                            class="block aspect-video bg-zinc-100 dark:bg-zinc-800"
                        >
                            {#if project.latestFrameThumbnailUrl}
                                <img
                                    src={project.latestFrameThumbnailUrl}
                                    alt={`Latest frame of ${project.name}`}
                                    class="h-full w-full object-contain"
                                />
                            {:else}
                                <span
                                    class={cn(
                                        'grid h-full w-full place-items-center',
                                        emptyState,
                                    )}
                                >
                                    NO FRAMES YET
                                </span>
                            {/if}
                        </Link>

                        <div class="space-y-2 p-4">
                            <Link
                                href={show(project.id)}
                                class="block truncate font-medium hover:underline"
                            >
                                {project.name}
                            </Link>

                            <p class={monoMeta}>
                                {project.frameCount}
                                {project.frameCount === 1 ? 'FRAME' : 'FRAMES'} ·
                                {project.fps} FPS · {project.orientation ===
                                'landscape'
                                    ? 'LANDSCAPE'
                                    : 'PORTRAIT'}
                            </p>

                            <div class="flex items-center gap-3 pt-1">
                                <Link
                                    href={capture(project.id)}
                                    class={cn(btnPrimary, 'px-3 py-1.5')}
                                >
                                    Capture
                                </Link>
                                <button
                                    type="button"
                                    class={btnDangerText}
                                    onclick={() => deleteProject(project)}
                                >
                                    DELETE
                                </button>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </section>
</div>
