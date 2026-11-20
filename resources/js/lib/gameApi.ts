import { TOKEN_HEADER, playerToken } from '@/lib/playerSession';
import type {
    GameResult,
    GameSnapshot,
    RematchResponse,
    RematchTokenResponse,
} from '@/types';

export class GameApiError extends Error {
    status: number;
    errors?: Record<string, string[] | string>;

    constructor(
        message: string,
        status: number,
        errors?: Record<string, string[] | string>,
    ) {
        super(message);
        this.name = 'GameApiError';
        this.status = status;
        this.errors = errors;
    }
}

function csrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? null
    );
}

function buildHeaders(code: string, json = true): Headers {
    const headers = new Headers({
        Accept: 'application/json',
    });

    if (json) {
        headers.set('Content-Type', 'application/json');
    }

    const token = playerToken(code);
    if (token) {
        headers.set(TOKEN_HEADER, token);
    }

    const csrf = csrfToken();
    if (csrf) {
        headers.set('X-CSRF-TOKEN', csrf);
    }

    return headers;
}

async function request<T>(
    url: string,
    code: string,
    init: RequestInit = {},
): Promise<T> {
    const response = await fetch(url, {
        ...init,
        headers: buildHeaders(code, init.body !== undefined),
        credentials: 'same-origin',
    });

    const text = await response.text();
    let data: unknown = null;

    if (text) {
        try {
            data = JSON.parse(text) as unknown;
        } catch {
            data = text;
        }
    }

    if (!response.ok) {
        const payload =
            data !== null && typeof data === 'object' && !Array.isArray(data)
                ? (data as Record<string, unknown>)
                : {};

        const message =
            typeof payload.message === 'string' && payload.message !== ''
                ? payload.message
                : `Error ${response.status}`;

        const errors =
            payload.errors !== null &&
            typeof payload.errors === 'object' &&
            !Array.isArray(payload.errors)
                ? (payload.errors as Record<string, string[] | string>)
                : undefined;

        throw new GameApiError(message, response.status, errors);
    }

    return data as T;
}

export async function getSnapshot(code: string): Promise<GameSnapshot> {
    return request<GameSnapshot>(`/api/games/${code}`, code, { method: 'GET' });
}

export async function roll(
    code: string,
    version: number,
): Promise<GameSnapshot> {
    return request<GameSnapshot>(`/api/games/${code}/roll`, code, {
        method: 'POST',
        body: JSON.stringify({ version }),
    });
}

export async function movePiece(
    code: string,
    pieceId: string,
    version: number,
): Promise<GameSnapshot> {
    return request<GameSnapshot>(`/api/games/${code}/moves`, code, {
        method: 'POST',
        body: JSON.stringify({ pieceId, version }),
    });
}

export async function getResult(code: string): Promise<GameResult> {
    return request<GameResult>(`/api/games/${code}/result`, code, {
        method: 'GET',
    });
}

export async function createRematch(code: string): Promise<RematchResponse> {
    return request<RematchResponse>(`/api/games/${code}/rematch`, code, {
        method: 'POST',
        body: JSON.stringify({}),
    });
}

export async function getRematchToken(
    oldCode: string,
): Promise<RematchTokenResponse> {
    return request<RematchTokenResponse>(
        `/api/games/${oldCode}/rematch/token`,
        oldCode,
        {
            method: 'POST',
            body: JSON.stringify({}),
        },
    );
}
