import { ref } from 'vue';

export type TextScale = 1 | 1.25 | 1.5 | 2;

const TEXT_KEY = 'parchis_text_scale';
const MOTION_KEY = 'parchis_reduce_motion';

const motionQuery =
    typeof window === 'undefined'
        ? null
        : window.matchMedia('(prefers-reduced-motion: reduce)');
const prefersReducedMotion = motionQuery?.matches ?? false;

function readTextScale(): TextScale {
    if (typeof window === 'undefined') {
        return 1;
    }

    const stored = Number(window.localStorage.getItem(TEXT_KEY));
    return stored === 1.25 || stored === 1.5 || stored === 2 ? stored : 1;
}

function readReduceMotion(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const stored = window.localStorage.getItem(MOTION_KEY);
    return stored === null ? prefersReducedMotion : stored === '1';
}

const textScale = ref<TextScale>(readTextScale());
const reduceMotion = ref<boolean>(readReduceMotion());

function apply(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.style.setProperty(
        '--a11y-text-scale',
        String(textScale.value),
    );
    document.documentElement.classList.toggle(
        'reduce-motion',
        reduceMotion.value,
    );
}

export function initAccessibility(): void {
    apply();
    motionQuery?.addEventListener('change', (event) => {
        if (window.localStorage.getItem(MOTION_KEY) === null) {
            reduceMotion.value = event.matches;
            apply();
        }
    });
}

export function useAccessibility() {
    const setTextScale = (value: TextScale): void => {
        textScale.value = value;
        window.localStorage.setItem(TEXT_KEY, String(value));
        apply();
    };

    const setReduceMotion = (value: boolean): void => {
        reduceMotion.value = value;
        window.localStorage.setItem(MOTION_KEY, value ? '1' : '0');
        apply();
    };

    return { textScale, reduceMotion, setTextScale, setReduceMotion };
}
