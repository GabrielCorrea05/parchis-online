import { beforeEach, vi } from 'vitest';
import { defineComponent, h, reactive } from 'vue';

const page = reactive({
    props: {
        errors: {} as Record<string, string>,
    },
});

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({
        setup: () => () => null,
    }),
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots, attrs }) {
            return () =>
                h('a', { ...attrs, href: props.href }, slots.default?.());
        },
    }),
    router: {
        post: vi.fn(),
        visit: vi.fn(),
        on: vi.fn(),
    },
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {} as Record<string, string>,
            processing: false,
            post: vi.fn(),
        }),
    usePage: () => page,
}));

export function setPageErrors(errors: Record<string, string>): void {
    page.props.errors = { ...errors };
}

vi.mock('@laravel/echo-vue', async () => {
    const { ref } = await import('vue');

    return {
        configureEcho: vi.fn(),
        useConnectionStatus: () => ref('connected'),
        useEcho: vi.fn(),
        useEchoPresence: () => ({ leaveChannel: vi.fn() }),
    };
});

vi.mock('@/lib/gameApi', () => {
    class GameApiError extends Error {
        status: number;

        constructor(message: string, status: number) {
            super(message);
            this.status = status;
        }
    }

    return {
        GameApiError,
        getSnapshot: vi.fn(),
        movePiece: vi.fn(),
        roll: vi.fn(),
        createRematch: vi.fn(),
        getRematchToken: vi.fn(),
    };
});

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

beforeEach(() => {
    page.props.errors = {};
    window.localStorage.clear();
    document.body.innerHTML = '';
    document.documentElement.lang = 'es';
    document.documentElement.title = 'Parchís online';
    document.documentElement.removeAttribute('style');
    document.documentElement.classList.remove('dark', 'reduce-motion');
});
