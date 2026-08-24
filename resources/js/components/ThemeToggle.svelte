<script lang="ts">
    import { btnIcon } from '@/lib/styles';

    let dark = $state(document.documentElement.classList.contains('dark'));

    function toggle(): void {
        dark = !dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', dark ? '#09090b' : '#f5f5f4');
    }
</script>

<button
    type="button"
    aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
    class={btnIcon}
    onclick={toggle}
>
    {#if dark}
        <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
        >
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"
            />
        </svg>
    {:else}
        <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
        </svg>
    {/if}
</button>
