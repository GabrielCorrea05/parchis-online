export type PlayerShape = 'circle' | 'square' | 'triangle' | 'diamond';

const SHAPES: Record<string, PlayerShape> = {
    red: 'circle',
    yellow: 'square',
    blue: 'triangle',
    green: 'diamond',
};

const LABELS: Record<string, string> = {
    red: 'Rojo · círculo',
    yellow: 'Ámbar · cuadrado',
    blue: 'Azul · triángulo',
    green: 'Verde · rombo',
};

export function playerShape(color: string): PlayerShape {
    return SHAPES[color] ?? 'circle';
}

const SHORT_LABELS: Record<string, string> = {
    red: 'rojo',
    yellow: 'ámbar',
    blue: 'azul',
    green: 'verde',
};

export function playerLabel(color: string): string {
    return LABELS[color] ?? color;
}

export function playerShortLabel(color: string): string {
    return SHORT_LABELS[color] ?? color;
}

/** Devuelve una referencia al token CSS, nunca un color escrito a mano. */
export function playerColor(color: string): string {
    const safe = Object.hasOwn(SHAPES, color) ? color : 'red';

    return `var(--player-${safe})`;
}

/**
 * Colores impresos en el tablero físico: se mantienen oscuros también en el
 * tema oscuro para que las fichas y las casillas sigan siendo legibles sobre
 * el fieltro marfileño.
 */
export function boardPlayerColor(color: string): string {
    const safe = Object.hasOwn(SHAPES, color) ? color : 'red';

    return `var(--board-player-${safe})`;
}
