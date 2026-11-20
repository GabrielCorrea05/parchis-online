import { ref } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'parchis_theme';
const media =
    typeof window === 'undefined'
        ? null
        : window.matchMedia('(prefers-color-scheme: dark)');

const preference = ref<ThemePreference>(readPreference());

function readPreference(): ThemePreference {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' || stored === 'system'
        ? stored
        : 'system';
}

function apply(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const dark =
        preference.value === 'dark' ||
        (preference.value === 'system' && media?.matches === true);

    document.documentElement.classList.toggle('dark', dark);
}

export function initTheme(): void {
    apply();
    media?.addEventListener('change', apply);
}

export function useTheme() {
    const setPreference = (value: ThemePreference): void => {
        preference.value = value;
        window.localStorage.setItem(STORAGE_KEY, value);
        apply();
    };

    return {
        preference,
        setPreference,
        toggle: () =>
            setPreference(preference.value === 'dark' ? 'light' : 'dark'),
    };
}
