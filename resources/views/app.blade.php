<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#f5f5f4">

        {{-- Applies the stored theme before first paint to prevent a flash. --}}
        <script>
            (function () {
                const stored = localStorage.getItem('theme');
                const dark = stored === 'dark'
                    || (!stored && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.querySelector('meta[name="theme-color"]')
                    ?.setAttribute('content', dark ? '#09090b' : '#f5f5f4');
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-stone-100 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <x-inertia::app />
    </body>
</html>
