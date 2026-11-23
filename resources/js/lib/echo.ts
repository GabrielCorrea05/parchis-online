import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-vue';

/**
 * Configuración pública inyectada por el servidor en resources/views/app.blade.php.
 * Así el mismo bundle sirve para Reverb (local/Cloud) y para Pusher (Wasmer)
 * sin tener que reconstruir los assets al cambiar de entorno.
 */
type BroadcastConfig = {
    connection?: string;
    pusher?: {
        key?: string;
        cluster?: string;
        host?: string;
        port?: number | string;
        scheme?: string;
    };
    reverb?: {
        key?: string;
        host?: string;
        port?: number | string;
        scheme?: string;
    };
};

declare global {
    interface Window {
        __PARCHIS_BROADCAST__?: BroadcastConfig;
    }
}

const asPort = (value: unknown, fallback: number): number => {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

const serverConfig = (): BroadcastConfig =>
    typeof window === 'undefined' ? {} : (window.__PARCHIS_BROADCAST__ ?? {});

function applyAuthHeaders(headers: Record<string, string>): void {
    if (!echoIsConfigured()) {
        return;
    }

    try {
        const instance = echo() as unknown as {
            options?: { auth?: { headers?: Record<string, string> } };
            connector?: {
                options?: { auth?: { headers?: Record<string, string> } };
                pusher?: {
                    config?: { auth?: { headers?: Record<string, string> } };
                };
            };
        };

        if (instance.options?.auth) {
            instance.options.auth.headers = {
                ...instance.options.auth.headers,
                ...headers,
            };
        }

        if (instance.connector?.options?.auth) {
            instance.connector.options.auth.headers = {
                ...instance.connector.options.auth.headers,
                ...headers,
            };
        }

        if (instance.connector?.pusher?.config?.auth) {
            instance.connector.pusher.config.auth.headers = {
                ...instance.connector.pusher.config.auth.headers,
                ...headers,
            };
        }
    } catch {
        // Si Echo aún no está listo, la configuración inicial aplicará los headers.
    }
}

/**
 * Configura el cliente Echo una sola vez. Las llamadas posteriores solo
 * actualizan las cabeceras de autenticación (token por pestaña/sala), porque
 * @laravel/echo-vue no recrea la instancia ya creada.
 */
export function configureGameEcho(headers: Record<string, string> = {}): void {
    const config = serverConfig();
    const connection =
        config.connection ??
        import.meta.env.VITE_BROADCAST_CONNECTION ??
        'reverb';

    if (echoIsConfigured()) {
        applyAuthHeaders(headers);
        return;
    }

    if (connection === 'pusher') {
        const pusher = config.pusher ?? {};
        const scheme =
            pusher.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? 'https';

        configureEcho({
            broadcaster: 'pusher',
            key: pusher.key ?? import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: pusher.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER,
            host: pusher.host ?? import.meta.env.VITE_PUSHER_HOST,
            port: asPort(pusher.port ?? import.meta.env.VITE_PUSHER_PORT, 443),
            scheme,
            forceTLS: scheme === 'https',
            auth: { headers },
        });
        applyAuthHeaders(headers);

        return;
    }

    const reverb = config.reverb ?? {};
    const scheme =
        reverb.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    configureEcho({
        broadcaster: 'reverb',
        key: reverb.key ?? import.meta.env.VITE_REVERB_APP_KEY,
        host: reverb.host ?? import.meta.env.VITE_REVERB_HOST,
        port: asPort(reverb.port ?? import.meta.env.VITE_REVERB_PORT, 443),
        scheme,
        auth: { headers },
    });
    applyAuthHeaders(headers);
}
