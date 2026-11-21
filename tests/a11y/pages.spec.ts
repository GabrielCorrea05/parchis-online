import axe from 'axe-core';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import Welcome from '@/pages/Welcome.vue';
import Join from '@/pages/Rooms/Join.vue';
import Waiting from '@/pages/Rooms/Waiting.vue';
import Board from '@/pages/Games/Board.vue';
import Result from '@/pages/Games/Result.vue';
import ResultsIndex from '@/pages/Results/Index.vue';
import ResultsShow from '@/pages/Results/Show.vue';
import { useAccessibility } from '@/lib/accessibility';
import { personaliseRoom } from '@/lib/room';
import type { Room } from '@/types';
import { board, colors, history, result, room, snapshot } from './fixtures';

const wrappers: VueWrapper[] = [];

function render(
    component: unknown,
    props: Record<string, unknown> = {},
): VueWrapper {
    const wrapper = mount(component as never, {
        props,
        attachTo: document.body,
        global: {
            directives: { focus: {} },
        },
    }) as VueWrapper;
    wrappers.push(wrapper);
    return wrapper;
}

afterEach(() => {
    while (wrappers.length > 0) {
        wrappers.pop()?.unmount();
    }
});

async function expectNoViolations(wrapper: VueWrapper): Promise<void> {
    const results = await axe.run(wrapper.element as HTMLElement, {
        runOnly: {
            type: 'tag',
            values: [
                'wcag2a',
                'wcag2aa',
                'wcag21a',
                'wcag21aa',
                'wcag22aa',
                'best-practice',
            ],
        },
        rules: {
            /* El contraste se mide con el script de tokens y con Chrome; jsdom no calcula CSS. */
            'color-contrast': { enabled: false },
        },
    });

    const summary = results.violations.map(
        (violation) =>
            `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`,
    );
    expect(summary).toEqual([]);
}

describe('axe en las pantallas principales', () => {
    it('portada', async () => {
        await expectNoViolations(render(Welcome, { colors, maxPlayers: 6 }));
    });

    it('unirse a sala', async () => {
        await expectNoViolations(
            render(Join, { code: 'K7P2', colors, maxPlayers: 6 }),
        );
    });

    it('sala de espera', async () => {
        await expectNoViolations(render(Waiting, { room }));
    });

    it('partida', async () => {
        await expectNoViolations(render(Board, { snapshot }));
    });

    it('resultado final', async () => {
        await expectNoViolations(render(Result, { result, snapshot }));
    });

    it('historial', async () => {
        await expectNoViolations(render(ResultsIndex, { history }));
    });

    it('detalle público', async () => {
        await expectNoViolations(render(ResultsShow, { result }));
    });
});

describe('comprobaciones específicas de accesibilidad', () => {
    it('incluye enlace para saltar al contenido', () => {
        const wrapper = render(Welcome, { colors, maxPlayers: 6 });
        const skip = wrapper.find('.skip-link');
        expect(skip.exists()).toBe(true);
        expect(skip.attributes('href')).toBe('#contenido-principal');
        expect(wrapper.find('#contenido-principal').exists()).toBe(true);
    });

    it('la ficha movible describe dueño, posición y disponibilidad', () => {
        const wrapper = render(Board, { snapshot });
        const label = wrapper
            .find('[data-legal-piece="true"]')
            .attributes('aria-label');
        expect(label).toContain('Ficha 2 de Marta');
        expect(label).toContain('en el recorrido, casilla 5');
        expect(label).toContain('se puede mover');
    });

    it('el selector de color navega con flechas', async () => {
        const wrapper = render(Welcome, { colors, maxPlayers: 6 });
        const radios = wrapper.findAll('[role="radio"]');
        expect(radios[0]?.attributes('aria-checked')).toBe('true');
        await radios[0]?.trigger('keydown', { key: 'ArrowRight' });
        await nextTick();
        expect(
            wrapper.findAll('[role="radio"]')[1]?.attributes('aria-checked'),
        ).toBe('true');
    });

    it('el ajuste de texto y movimiento se aplica al documento', async () => {
        render(Welcome, { colors, maxPlayers: 6 });
        const { setTextScale, setReduceMotion } = useAccessibility();
        setTextScale(2);
        setReduceMotion(true);
        await nextTick();
        expect(
            document.documentElement.style.getPropertyValue(
                '--a11y-text-scale',
            ),
        ).toBe('2');
        expect(
            document.documentElement.classList.contains('reduce-motion'),
        ).toBe(true);
    });

    it('el tablero ofrece una tabla de texto', () => {
        const wrapper = render(Board, { snapshot });
        const table = wrapper.find('table');
        expect(table.exists()).toBe(true);
        expect(table.findAll('th[scope="col"]').length).toBe(5);
        expect(table.text()).toContain('Marta');
    });

    it('el dado y el indicador de conexión tienen nombre accesible', () => {
        const wrapper = render(Board, { snapshot });
        expect(wrapper.find('[role="img"]').attributes('aria-label')).toContain(
            'Dado: 5',
        );
        expect(wrapper.find('[role="status"]').exists()).toBe(true);
    });

    it('el tablero usa el constructor clásico para 4 brazos', () => {
        expect(board.arms).toBe(4);
        expect(board.cellsPerArm).toBe(17);
        expect(board.homeLength).toBe(7);
        expect(board.startIndices).toEqual([0, 17, 34, 51]);
        expect(board.safeCells).toHaveLength(6);
    });
});

describe('sala de espera en vivo', () => {
    it('el host conserva «Empezar partida» con un payload de evento neutro', () => {
        const broadcastRoom = {
            ...room,
            currentPlayerId: null,
            canStart: false,
            players: room.players.map((player) => ({
                ...player,
                isYou: false,
            })),
        } as unknown as Room;

        const hostRoom = personaliseRoom(broadcastRoom, 1);
        expect(hostRoom.canStart).toBe(true);
        expect(hostRoom.players.find((p) => p.id === 1)?.isYou).toBe(true);
        expect(hostRoom.players.find((p) => p.id === 2)?.isYou).toBe(false);

        const guestRoom = personaliseRoom(broadcastRoom, 2);
        expect(guestRoom.canStart).toBe(false);
        expect(guestRoom.players.find((p) => p.id === 2)?.isYou).toBe(true);
    });

    it('el botón del host sigue visible y se deshabilita con un jugador', () => {
        const soloHost = {
            ...room,
            playerCount: 1,
            players: room.players.slice(0, 1),
            currentPlayerId: 1,
        } as unknown as Room;

        const wrapper = render(Waiting, { room: soloHost });
        const button = wrapper.find('button.btn-primary');

        expect(button.exists()).toBe(true);
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.text()).toContain('Faltan jugadores para empezar');
        expect(wrapper.text()).not.toContain('Esperando a que');
    });

    it('los demás jugadores ven quién debe iniciar', () => {
        const guestRoom = {
            ...room,
            currentPlayerId: 2,
            canStart: false,
        } as unknown as Room;

        const wrapper = render(Waiting, { room: guestRoom });

        expect(wrapper.text()).toContain(
            'Esperando a que Marta inicie la partida',
        );
        expect(wrapper.find('button.btn-primary').exists()).toBe(false);
    });
});
