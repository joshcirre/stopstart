<script lang="ts" module>
    export { default as layout } from '@/layouts/AppLayout.svelte';
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import { cn } from '@/lib/utils';
    import { capture, destroy, show, store } from '@/routes/projects';
    import type { ProjectsIndexProps, ProjectSummary } from '@/types';
    import { FPS_CHOICES } from '@/types';

    let { projects }: ProjectsIndexProps = $props();

    const videoTones = {
        pending: 'info',
        processing: 'warning',
        completed: 'success',
        failed: 'danger',
    } as const;

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

<div class="space-y-8">
    <section class="rounded-xl border border-neutral-200 bg-white p-5">
        <h2 class="text-base font-semibold">New project</h2>

        <Form action={store()} resetOnSuccess class="mt-4">
            {#snippet children({ errors, processing })}
                <div class="flex flex-wrap items-end gap-4">
                    <label class="flex min-w-48 flex-1 flex-col gap-1">
                        <span class="text-sm font-medium">Name</span>
                        <input
                            type="text"
                            name="name"
                            required
                            placeholder="Lego walk cycle"
                            class="rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none"
                        />
                    </label>

                    <fieldset class="flex gap-2">
                        <legend class="mb-1 text-sm font-medium">
                            Orientation
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
                                    class="flex flex-col rounded-lg border border-neutral-300 px-3 py-1.5 text-sm peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white"
                                >
                                    {option.label}
                                    <span class="text-xs opacity-70">
                                        {option.hint}
                                    </span>
                                </span>
                            </label>
                        {/each}
                    </fieldset>

                    <label class="flex flex-col gap-1">
                        <span class="text-sm font-medium">FPS</span>
                        <select
                            name="fps"
                            class="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                        >
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
                        class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {processing ? 'Creating…' : 'Create project'}
                    </button>
                </div>

                {#if errors.name || errors.orientation || errors.fps}
                    <p class="mt-2 text-sm text-red-600">
                        {errors.name ?? errors.orientation ?? errors.fps}
                    </p>
                {/if}
            {/snippet}
        </Form>
    </section>

    <section>
        <h2 class="mb-3 text-base font-semibold">Your projects</h2>

        {#if projects.length === 0}
            <p
                class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-sm text-neutral-500"
            >
                No projects yet — create one above and start capturing.
            </p>
        {:else}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {#each projects as project (project.id)}
                    <div
                        class="overflow-hidden rounded-xl border border-neutral-200 bg-white"
                    >
                        <Link
                            href={show(project.id)}
                            class={cn(
                                'block bg-neutral-100',
                                project.orientation === 'landscape'
                                    ? 'aspect-video'
                                    : 'aspect-video',
                            )}
                        >
                            {#if project.latestFrameThumbnailUrl}
                                <img
                                    src={project.latestFrameThumbnailUrl}
                                    alt={`Latest frame of ${project.name}`}
                                    class="h-full w-full object-contain"
                                />
                            {:else}
                                <span
                                    class="grid h-full w-full place-items-center text-sm text-neutral-400"
                                >
                                    No frames yet
                                </span>
                            {/if}
                        </Link>

                        <div class="space-y-2 p-4">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <Link
                                    href={show(project.id)}
                                    class="truncate font-medium hover:underline"
                                >
                                    {project.name}
                                </Link>
                                <span
                                    class="text-sm whitespace-nowrap text-neutral-500 tabular-nums"
                                >
                                    {project.frameCount}
                                    {project.frameCount === 1
                                        ? 'frame'
                                        : 'frames'}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <StatusBadge
                                    label={project.orientation === 'landscape'
                                        ? 'Landscape'
                                        : 'Portrait'}
                                />
                                <StatusBadge label={`${project.fps} fps`} />
                                {#if project.videoStatus}
                                    <StatusBadge
                                        label={project.videoStatus}
                                        tone={videoTones[project.videoStatus]}
                                        pulse={project.videoStatus ===
                                            'processing'}
                                    />
                                {/if}
                            </div>

                            <div class="flex items-center gap-3 pt-1">
                                <Link
                                    href={capture(project.id)}
                                    class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white"
                                >
                                    Capture
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm text-red-600 hover:underline"
                                    onclick={() => deleteProject(project)}
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </section>
</div>
