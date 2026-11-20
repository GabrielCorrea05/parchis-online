/**
 * El token de jugador vive en sessionStorage: así dos pestañas del mismo
 * navegador pueden ser dos jugadores distintos. La cookie `parchis_session`
 * solo se usa una vez, para descubrir el token al cargar la página.
 */

export const TOKEN_HEADER = 'X-Player-Token';

const COOKIE_NAME = 'parchis_session';
const STORAGE_PREFIX = 'parchis_token_';

const storageKey = (code: string): string =>
    STORAGE_PREFIX + code.toUpperCase();

function tokensFromCookie(): Record<string, string> {
    const entry = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${COOKIE_NAME}=`));

    if (!entry) {
        return {};
    }

    try {
        const raw = decodeURIComponent(entry.slice(COOKIE_NAME.length + 1));
        const parsed: unknown = JSON.parse(raw);

        return parsed !== null &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed)
            ? (parsed as Record<string, string>)
            : {};
    } catch {
        return {};
    }
}

export function playerToken(code: string): string | null {
    const stored = sessionStorage.getItem(storageKey(code));

    if (stored) {
        return stored;
    }

    const fromCookie = tokensFromCookie()[code.toUpperCase()];

    if (fromCookie) {
        sessionStorage.setItem(storageKey(code), fromCookie);
    }

    return fromCookie ?? null;
}

export function playerTokenFromUrl(url: string | URL): string | null {
    const path = new URL(url.toString(), window.location.origin).pathname;
    const match = path.match(/\/(?:rooms|games|partidas)\/([A-Za-z0-9]+)/);

    return match ? playerToken(match[1]) : null;
}

export function rememberPlayerToken(code: string, token: string): void {
    sessionStorage.setItem(storageKey(code), token);
}

export function forgetPlayerToken(code: string): void {
    sessionStorage.removeItem(storageKey(code));
}
