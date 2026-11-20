import { router } from '@inertiajs/vue3';
import { getRematchToken } from '@/lib/gameApi';
import { rememberPlayerToken } from '@/lib/playerSession';

/**
 * La revancha clona la sala reutilizando el token: el servidor copia los
 * tokens cifrados, así que cada cliente recupera el suyo con su token antiguo.
 */
export async function followRematch(
    oldCode: string,
    newCode: string,
): Promise<void> {
    const response = await getRematchToken(oldCode);
    rememberPlayerToken(response.code || newCode, response.playerToken);
    router.visit(`/rooms/${response.code || newCode}`);
}
