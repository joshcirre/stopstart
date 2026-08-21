let audioContext: AudioContext | null = null;

/**
 * A short shutter beep. The AudioContext is created lazily because
 * browsers only allow audio after a user gesture.
 */
export function playShutterBeep(): void {
    try {
        audioContext ??= new AudioContext();

        if (audioContext.state === 'suspended') {
            void audioContext.resume();
        }

        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;

        gain.gain.setValueAtTime(0.15, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(
            0.001,
            audioContext.currentTime + 0.08,
        );

        oscillator.connect(gain);
        gain.connect(audioContext.destination);

        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.08);
    } catch {
        // Audio is a nicety; never let it break a capture.
    }
}
