import { ref } from 'vue';

const STORAGE_KEY = 'parchis_sound';
const enabled = ref(false);
let context: AudioContext | null = null;

if (typeof window !== 'undefined') {
    enabled.value = window.localStorage.getItem(STORAGE_KEY) === '1';
}

type Tone = {
    frequency: number;
    duration: number;
    delay?: number;
    type?: OscillatorType;
    volume?: number;
};

function contextFor(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (context === null) {
        const Ctor =
            window.AudioContext ??
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (Ctor === undefined) {
            return null;
        }

        context = new Ctor();
    }

    if (context.state === 'suspended') {
        void context.resume();
    }

    return context;
}

function tone({
    frequency,
    duration,
    delay = 0,
    type = 'sine',
    volume = 0.06,
}: Tone): void {
    const audio = contextFor();

    if (audio === null) {
        return;
    }

    const start = audio.currentTime + delay;
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(volume, start + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(gain).connect(audio.destination);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.02);
}

export function useSound() {
    const toggle = (): void => {
        enabled.value = !enabled.value;
        window.localStorage.setItem(STORAGE_KEY, enabled.value ? '1' : '0');

        if (enabled.value) {
            tone({ frequency: 660, duration: 0.09 });
        }
    };

    const playRoll = (): void => {
        if (!enabled.value) {
            return;
        }

        tone({ frequency: 420, duration: 0.06, type: 'triangle' });
        tone({ frequency: 520, duration: 0.06, delay: 0.07, type: 'triangle' });
    };

    const playMove = (): void => {
        if (!enabled.value) {
            return;
        }

        tone({ frequency: 300, duration: 0.08, type: 'sine' });
    };

    const playCapture = (): void => {
        if (!enabled.value) {
            return;
        }

        tone({ frequency: 220, duration: 0.12, type: 'square', volume: 0.04 });
        tone({
            frequency: 160,
            duration: 0.16,
            delay: 0.09,
            type: 'square',
            volume: 0.04,
        });
    };

    const playWin = (): void => {
        if (!enabled.value) {
            return;
        }

        [523, 659, 784, 1047].forEach((frequency, index) => {
            tone({
                frequency,
                duration: 0.18,
                delay: index * 0.1,
                type: 'triangle',
            });
        });
    };

    return { enabled, toggle, playRoll, playMove, playCapture, playWin };
}
