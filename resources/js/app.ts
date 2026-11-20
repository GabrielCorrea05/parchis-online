import { createInertiaApp, router } from '@inertiajs/vue3';
import { initAccessibility } from '@/lib/accessibility';
import { configureGameEcho } from '@/lib/echo';
import { initTheme } from '@/lib/theme';
import { playerTokenFromUrl, TOKEN_HEADER } from '@/lib/playerSession';

initTheme();
initAccessibility();

configureGameEcho();

router.on('before', (event) => {
    const token = playerTokenFromUrl(event.detail.visit.url);

    if (token) {
        event.detail.visit.headers = {
            ...event.detail.visit.headers,
            [TOKEN_HEADER]: token,
        };
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Parchís online';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: getComputedStyle(document.documentElement)
            .getPropertyValue('--c-accent')
            .trim(),
    },
});
