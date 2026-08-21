/**
 * Deadline-based interval timer: each cycle schedules against
 * performance.now() so long shoots never drift, while a 100ms tick
 * keeps the countdown UI smooth.
 */
export class IntervalTimer {
    running = $state(false);

    intervalSeconds = $state(5);

    msRemaining = $state(0);

    readonly secondsRemaining = $derived(Math.ceil(this.msRemaining / 1000));

    readonly fraction = $derived(
        this.running ? this.msRemaining / (this.intervalSeconds * 1000) : 0,
    );

    #deadline = 0;

    #handle: ReturnType<typeof setInterval> | null = null;

    constructor(private readonly onFire: () => void) {}

    start(seconds?: number): void {
        if (seconds) {
            this.intervalSeconds = seconds;
        }

        this.#deadline = performance.now() + this.intervalSeconds * 1000;
        this.msRemaining = this.intervalSeconds * 1000;
        this.running = true;
        this.#handle ??= setInterval(this.#tick, 100);
    }

    stop(): void {
        if (this.#handle) {
            clearInterval(this.#handle);
            this.#handle = null;
        }

        this.running = false;
        this.msRemaining = 0;
    }

    #tick = (): void => {
        this.msRemaining = Math.max(0, this.#deadline - performance.now());

        if (this.msRemaining === 0) {
            this.#deadline = performance.now() + this.intervalSeconds * 1000;
            this.msRemaining = this.intervalSeconds * 1000;
            this.onFire();
        }
    };
}
