import { configureEcho } from '@laravel/echo-vue';

/**
 * Unifica la configuración del cliente Echo.
 *
 * Reverb es el valor por defecto (también en Laravel Cloud). Si se cambia a
 * Pusher basta con cambiar BROADCAST_CONNECTION / VITE_BROADCAST_CONNECTION;
 * las credenciales públicas las lee el paquete desde VITE_PUSHER_*.
 */
export function configureGameEcho(headers: Record<string, string> = {}): void {
    if (import.meta.env.VITE_BROADCAST_CONNECTION === 'pusher') {
        configureEcho({
            broadcaster: 'pusher',
            auth: { headers },
        });

        return;
    }

    configureEcho({
        broadcaster: 'reverb',
        auth: { headers },
    });
}
