import type { BoardArm, Point } from '@/types';

/**
 * Cuña de color que se imprime en cada brazo del tablero clásico.
 * Se deriva del centro y de la base del brazo, así que sirve para los cuatro
 * brazos del tablero clásico sin dibujar plantillas distintas.
 */
export function armWedge(center: Point, arm: BoardArm, spread = 104): string {
    const angle = Math.atan2(arm.base.y - center.y, arm.base.x - center.x);
    const px = -Math.sin(angle);
    const py = Math.cos(angle);
    const inner = 34;
    const outer = spread;

    return [
        [center.x + px * inner, center.y + py * inner],
        [arm.base.x + px * outer, arm.base.y + py * outer],
        [arm.base.x - px * outer, arm.base.y - py * outer],
        [center.x - px * inner, center.y - py * inner],
    ]
        .map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`)
        .join(' ');
}

/** Estrella decorativa del centro (roseta del parchís clásico). */
export function rosettePoints(center: Point, radius: number): string {
    return Array.from({ length: 16 }, (_, index) => {
        const r = index % 2 === 0 ? radius : radius * 0.52;
        const angle = -Math.PI / 2 + (index * Math.PI) / 8;

        return `${(center.x + r * Math.cos(angle)).toFixed(1)},${(center.y + r * Math.sin(angle)).toFixed(1)}`;
    }).join(' ');
}
