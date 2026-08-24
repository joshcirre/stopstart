import { cn } from '@/lib/utils';

/**
 * The shared wyrd-style design language. Conventions:
 * - Micro-label / empty-state TEXT is written in literal uppercase
 *   ("FRAMES", not "Frames"); the classes never add `uppercase`.
 * - Compose rails as cn(railCard, railAccent.blue).
 * - Numbers that change get `tabular-nums`.
 * - Color is semantic only: amber = headers/prompts, blue = info/primary
 *   accents, emerald = success/live, red = danger/recording.
 */

// ── Typography ─────────────────────────────────────────────────────────

export const microLabel = cn(
    'font-mono text-[10px] tracking-[0.25em] text-zinc-400 dark:text-zinc-500',
);

export const displayHeading = cn(
    'font-display text-2xl leading-snug font-bold sm:text-4xl',
);

export const monoMeta = cn(
    'font-mono text-xs text-zinc-500 tabular-nums dark:text-zinc-400',
);

export const emptyState = cn(
    'font-mono text-[10px] tracking-[0.25em] text-zinc-300 dark:text-zinc-600',
);

// ── Rails ──────────────────────────────────────────────────────────────

export const headerRail = cn('border-l-4 border-amber-400 pl-4 sm:pl-6');

export const railCard = cn(
    'border-l-4 bg-white p-4 shadow-sm sm:p-6 dark:bg-zinc-900',
);

export const railAccent = {
    amber: 'border-amber-400',
    emerald: 'border-emerald-500',
    red: 'border-red-500',
    blue: 'border-blue-500',
    muted: 'border-zinc-200 dark:border-zinc-700',
} as const;

export const listRail = cn(
    'border-l-2 border-zinc-200 pl-3 transition-colors duration-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500',
);

// ── Hairline bars ──────────────────────────────────────────────────────

export const hairlineTrack = cn('h-px w-full bg-zinc-200 dark:bg-zinc-700');

export const hairlineTrackPage = cn(
    'h-0.5 w-full bg-zinc-200 dark:bg-zinc-700',
);

export const hairlineFill = cn('h-full transition-all duration-700');

// ── Buttons (square) ───────────────────────────────────────────────────

export const btnPrimary = cn(
    'bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-zinc-700 disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white',
);

export const btnGhost = cn(
    'border border-zinc-300 px-3 py-1.5 text-sm text-zinc-600 transition-colors duration-200 hover:border-zinc-500 hover:text-zinc-900 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:text-zinc-100',
);

export const btnGhostMono = cn(
    'border border-zinc-300 px-3 py-1 font-mono text-[10px] tracking-[0.25em] text-zinc-600 transition-colors duration-200 hover:border-zinc-500 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500',
);

export const btnIcon = cn(
    'p-1 text-zinc-400 transition-colors duration-200 hover:text-zinc-600 dark:hover:text-zinc-300',
);

export const btnDangerText = cn(
    'font-mono text-xs text-red-600 transition-colors duration-200 hover:text-red-500 dark:text-red-400',
);

// ── Form controls (square) ─────────────────────────────────────────────

export const input = cn(
    'border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-400',
);

export const select = input;
