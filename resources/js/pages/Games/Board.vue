<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import AppShell from '@/components/AppShell.vue';
import Dice from '@/components/Dice.vue';
import ParchisBoard from '@/components/ParchisBoard.vue';
import PlayerBadge from '@/components/PlayerBadge.vue';
import PlayerList from '@/components/PlayerList.vue';
import { GameApiError, getSnapshot, movePiece, roll } from '@/lib/gameApi';
import { configureGameEcho } from '@/lib/echo';
import { playerToken, TOKEN_HEADER } from '@/lib/playerSession';
import { playerLabel } from '@/lib/playerStyle';
import { useSound } from '@/lib/sound';
import type { GameEventPayload, GamePiece, GameSnapshot } from '@/types';

const props = defineProps<{ snapshot: GameSnapshot }>();

configureGameEcho({
    [TOKEN_HEADER]: playerToken(props.snapshot.code) ?? '',
});

const boardSummaryId = useId();
const boardTableId = useId();

const youId = ref<number | null>(
    props.snapshot.youPlayerId ??
        props.snapshot.players.find((player) => player.isYou)?.id ??
        null,
);

/** El servidor personaliza el snapshot para quien ejecuta la acción; aquí lo re-personalizamos por pestaña. */
function personalise(snapshot: GameSnapshot): GameSnapshot {
    return {
        ...snapshot,
        youPlayerId: youId.value,
        players: snapshot.players.map((player) => ({
            ...player,
            isYou: player.id === youId.value,
        })),
    };
}

const snapshot = ref<GameSnapshot>(personalise(props.snapshot));
const busy = ref(false);
const rolling = ref(false);
const captureFlash = ref(false);
const winning = ref(false);
const error = ref('');
const announcement = ref('');
const log = ref<string[]>([]);
const rollButton = ref<HTMLButtonElement | null>(null);
const legalList = ref<HTMLElement | null>(null);
const connection = useConnectionStatus();
const {
    enabled: soundEnabled,
    toggle: toggleSound,
    playRoll,
    playMove,
    playCapture,
    playWin,
} = useSound();

const version = computed(() => snapshot.value.version);
const pieces = computed<GamePiece[]>(() =>
    Object.values(snapshot.value.state.pieces ?? {}),
);
const legalMoves = computed(
    () => snapshot.value.legalMoves ?? snapshot.value.state.legalMoves ?? [],
);
const ringCells = computed(
    () => snapshot.value.board.arms * snapshot.value.board.cellsPerArm,
);
const goalProgress = computed(
    () => ringCells.value + snapshot.value.board.homeLength,
);
const currentPlayer = computed(
    () =>
        snapshot.value.players.find(
            (player) => player.id === snapshot.value.currentPlayerId,
        ) ?? null,
);
const isMyTurn = computed(() => snapshot.value.currentPlayerId === youId.value);
const canRoll = computed(
    () => isMyTurn.value && snapshot.value.pendingRoll === null && !busy.value,
);
const canMove = computed(
    () =>
        isMyTurn.value &&
        snapshot.value.pendingRoll !== null &&
        legalMoves.value.length > 0 &&
        !busy.value,
);
const connected = computed(() => connection.value === 'connected');
const boardSummary = computed(
    () =>
        `Tablero de ${snapshot.value.board.arms} brazos. Turno de ${playerName(snapshot.value.currentPlayerId)}. ` +
        `Dado: ${snapshot.value.pendingRoll ?? snapshot.value.state.dice.last ?? 'sin tirar'}. ` +
        `Fichas movibles: ${canMove.value ? legalMoves.value.length : 0}.`,
);

const playerName = (id: number | null | undefined): string =>
    snapshot.value.players.find((player) => player.id === id)?.nickname ?? '—';

function pieceLocation(piece: GamePiece): string {
    if (piece.progress === null) {
        return `en casa, hueco ${piece.slot}`;
    }
    if (piece.progress >= goalProgress.value) {
        return 'en la meta';
    }
    if (piece.progress >= ringCells.value) {
        return `en la columna de casa, casilla ${piece.progress - ringCells.value + 1}`;
    }
    const exit = snapshot.value.board.cells.find(
        (cell) => cell.arm === piece.seat && cell.kind === 'salida',
    );
    const index = exit
        ? (exit.index + piece.progress) % ringCells.value
        : piece.progress;
    return `en el recorrido, casilla ${index + 1}`;
}

function pieceAccessibleLabel(piece: GamePiece): string {
    const owner =
        snapshot.value.players.find((player) => player.id === piece.playerId)
            ?.nickname ?? 'jugador';
    const movable =
        canMove.value && legalMoves.value.includes(piece.id)
            ? 'se puede mover'
            : 'no se puede mover';
    return `Ficha ${piece.slot} de ${owner}, ${pieceLocation(piece)}, ${movable}`;
}

const reasonText: Record<string, string> = {
    roll: 'ha tirado el dado',
    move: 'ha movido una ficha',
    third_six: 'ha perdido el turno por tres seises',
    no_legal_move: 'no tenía movimiento legal',
    extra_turn: 'vuelve a tirar por un 6',
    finish: 'ha terminado la partida',
};

function addLog(message: string): void {
    log.value = [message, ...log.value].slice(0, 12);
}

let announcementParts: string[] = [];
let announcementVersion = -1;
let flushTimer = 0;

function queueAnnouncement(versionNumber: number, message: string): void {
    if (versionNumber > announcementVersion) {
        announcementVersion = versionNumber;
        announcementParts = [];
    }
    if (
        versionNumber === announcementVersion &&
        message !== '' &&
        !announcementParts.includes(message)
    ) {
        announcementParts.push(message);
    }
}

function queueReason(versionNumber: number, reason: string): void {
    if (reason === '') {
        return;
    }

    const message = reasonText[reason] ?? reason;
    const redundant =
        (reason === 'roll' &&
            announcementParts.some((part) => part.includes('ha sacado un'))) ||
        (reason === 'move' &&
            announcementParts.some((part) =>
                part.includes('ha movido la ficha'),
            )) ||
        (reason === 'finish' &&
            announcementParts.some((part) =>
                part.startsWith('Partida terminada'),
            ));

    if (!redundant) {
        queueAnnouncement(versionNumber, message);
    }
}

function flushAnnouncement(): void {
    window.clearTimeout(flushTimer);
    flushTimer = window.setTimeout(() => {
        if (announcementParts.length > 0) {
            announcement.value = announcementParts.join(' ');
            announcementParts = [];
        }
    }, 0);
}

function applyIncoming(incoming: GameSnapshot, reason = ''): void {
    const previous = snapshot.value;
    const isNewer = incoming.version > previous.version;

    if (!isNewer) {
        queueReason(incoming.version, reason);
        flushAnnouncement();
        return;
    }

    const lastRoll = incoming.state.dice.history.at(-1);
    if (
        lastRoll &&
        lastRoll.value !== previous.state.dice.history.at(-1)?.value
    ) {
        queueAnnouncement(
            incoming.version,
            `${playerName(lastRoll.playerId)} ha sacado un ${lastRoll.value}`,
        );
    }
    if (incoming.currentPlayerId !== previous.currentPlayerId) {
        queueAnnouncement(
            incoming.version,
            `Turno de ${playerName(incoming.currentPlayerId)}`,
        );
    }
    queueReason(incoming.version, reason);

    for (const player of incoming.players) {
        const before = previous.players.find((item) => item.id === player.id);
        if (before && before.isConnected !== player.isConnected) {
            queueAnnouncement(
                incoming.version,
                player.isConnected
                    ? `${player.nickname} se ha conectado`
                    : `${player.nickname} se ha desconectado`,
            );
        }
    }

    if (incoming.status === 'finished' && previous.status !== 'finished') {
        const winner = incoming.players.find(
            (player) => player.id === incoming.state.winnerPlayerId,
        );
        queueAnnouncement(
            incoming.version,
            winner
                ? `Partida terminada. Gana ${winner.nickname}`
                : 'Partida terminada',
        );
    }

    snapshot.value = personalise(incoming);

    if (incoming.status === 'finished' && !winning.value) {
        winning.value = true;
        playWin();
        window.setTimeout(() => router.visit(`/games/${incoming.code}`), 1000);
    }

    flushAnnouncement();
}

useEcho<GameEventPayload>(
    `game.${props.snapshot.gameId}`,
    [
        '.GameStartedForGame',
        '.DiceRolled',
        '.PieceMoved',
        '.CaptureOccurred',
        '.TurnAdvanced',
        '.GameStateUpdated',
        '.GameFinished',
    ],
    (payload) => {
        if (!('snapshot' in payload)) {
            return;
        }

        const versionNumber = payload.snapshot.version;
        const reason = 'reason' in payload ? String(payload.reason ?? '') : '';

        queueReason(versionNumber, reason);
        if (reason !== '') {
            addLog(
                `${playerName(payload.snapshot.currentPlayerId)}: ${reasonText[reason] ?? reason}`,
            );
        }

        if ('value' in payload) {
            playRoll();
            rolling.value = true;
            window.setTimeout(() => (rolling.value = false), 560);
            const sixes = payload.consecutiveSixes
                ? ` (${payload.consecutiveSixes}/3 seises)`
                : '';
            queueAnnouncement(
                versionNumber,
                `${playerName(payload.playerId)} ha sacado un ${payload.value}${sixes}`,
            );
            addLog(
                `${playerName(payload.playerId)} ha sacado un ${payload.value}`,
            );
        }

        if ('pieceId' in payload) {
            playMove();
            const piece = Object.values(
                payload.snapshot.state.pieces ?? {},
            ).find((item) => item.id === payload.pieceId);
            queueAnnouncement(
                versionNumber,
                `${playerName(payload.playerId)} ha movido la ficha ${piece?.slot ?? payload.pieceId}`,
            );
            addLog(`${playerName(payload.playerId)} ha movido una ficha`);
        }

        if ('captured' in payload && payload.captured.length > 0) {
            playCapture();
            captureFlash.value = true;
            window.setTimeout(() => (captureFlash.value = false), 700);
            const count = payload.captured.length;
            queueAnnouncement(
                versionNumber,
                `¡Captura! ${count} ${count === 1 ? 'ficha vuelve' : 'fichas vuelven'} a casa`,
            );
            addLog(`Captura: ${count} ficha(s) a casa`);
        }

        applyIncoming(payload.snapshot);
    },
);

async function focusNextAction(): Promise<void> {
    await nextTick();
    const target = legalList.value?.querySelector<HTMLElement>(
        '[data-legal-piece-button]',
    );
    if (canMove.value && target) {
        target.focus();
        return;
    }
    if (canRoll.value) {
        rollButton.value?.focus();
    }
}

async function refresh(): Promise<void> {
    try {
        const incoming = await getSnapshot(props.snapshot.code);
        applyIncoming(incoming);
    } catch (caught) {
        error.value =
            caught instanceof Error
                ? caught.message
                : 'No se pudo actualizar la partida.';
    }
}

async function doRoll(): Promise<void> {
    if (!canRoll.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    rolling.value = true;
    playRoll();

    try {
        applyIncoming(await roll(props.snapshot.code, version.value), 'roll');
        await focusNextAction();
    } catch (caught) {
        if (caught instanceof GameApiError && caught.status === 409) {
            await refresh();
        }
        error.value =
            caught instanceof Error
                ? caught.message
                : 'No se pudo tirar el dado.';
    } finally {
        rolling.value = false;
        busy.value = false;
    }
}

async function doMove(pieceId: string): Promise<void> {
    if (!canMove.value || !legalMoves.value.includes(pieceId)) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        applyIncoming(
            await movePiece(props.snapshot.code, pieceId, version.value),
            'move',
        );
        playMove();
        await focusNextAction();
    } catch (caught) {
        if (caught instanceof GameApiError && caught.status === 409) {
            await refresh();
        }
        error.value =
            caught instanceof Error
                ? caught.message
                : 'No se pudo mover la ficha.';
    } finally {
        busy.value = false;
    }
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (
        event.key.toLowerCase() !== 'r' ||
        event.ctrlKey ||
        event.altKey ||
        event.metaKey
    ) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
        return;
    }

    if (canRoll.value) {
        event.preventDefault();
        void doRoll();
    }
}

watch(connection, (value) => {
    queueAnnouncement(
        version.value,
        value === 'connected'
            ? 'Conexión restablecida'
            : 'Conexión perdida; reconectando…',
    );
    flushAnnouncement();
});

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    window.clearTimeout(flushTimer);
    announcementParts = [];
});
</script>

<template>
    <AppShell wide :title="`Partida ${snapshot.code}`" eyebrow="Turno en curso">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="grid gap-4">
                <section
                    class="board-frame"
                    :class="{ 'capture-flash': captureFlash }"
                >
                    <h2 class="sr-only">Tablero de la partida</h2>
                    <p :id="boardSummaryId" class="sr-only">
                        {{ boardSummary }}
                    </p>
                    <ParchisBoard
                        :board="snapshot.board"
                        :pieces="pieces"
                        :players="snapshot.players"
                        :legal-moves="canMove ? legalMoves : []"
                        :piece-label="pieceAccessibleLabel"
                        :description-id="boardSummaryId"
                        @move="doMove"
                    />
                </section>

                <details class="surface-card p-4">
                    <summary class="btn btn-ghost w-full justify-start">
                        Ver tablero y fichas en formato texto
                    </summary>
                    <div class="mt-4 overflow-x-auto">
                        <table
                            :id="boardTableId"
                            class="w-full border-collapse text-left text-sm"
                        >
                            <caption class="sr-only">
                                Estado de todas las fichas de la partida
                            </caption>
                            <thead>
                                <tr class="border-b border-[var(--c-line)]">
                                    <th scope="col" class="py-2 pr-3">Ficha</th>
                                    <th scope="col" class="py-2 pr-3">
                                        Jugador
                                    </th>
                                    <th scope="col" class="py-2 pr-3">
                                        Color y forma
                                    </th>
                                    <th scope="col" class="py-2 pr-3">
                                        Posición
                                    </th>
                                    <th scope="col" class="py-2">Movible</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="piece in pieces"
                                    :key="piece.id"
                                    class="border-b border-[var(--c-line)] last:border-0"
                                >
                                    <td class="py-2 pr-3">{{ piece.slot }}</td>
                                    <td class="py-2 pr-3">
                                        {{
                                            snapshot.players.find(
                                                (player) =>
                                                    player.id ===
                                                    piece.playerId,
                                            )?.nickname
                                        }}
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{
                                            playerLabel(
                                                snapshot.players.find(
                                                    (player) =>
                                                        player.id ===
                                                        piece.playerId,
                                                )?.color ?? 'red',
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{ pieceLocation(piece) }}
                                    </td>
                                    <td class="py-2">
                                        {{
                                            canMove &&
                                            legalMoves.includes(piece.id)
                                                ? 'Sí'
                                                : 'No'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>

            <aside class="space-y-4">
                <div class="surface-card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="label mb-1">Dado</p>
                            <Dice
                                :value="
                                    snapshot.pendingRoll ??
                                    snapshot.state.dice.last
                                "
                                :rolling="rolling"
                            />
                        </div>
                        <span class="chip">
                            <span
                                class="status-dot"
                                :class="connected ? 'status-dot--online' : ''"
                                aria-hidden="true"
                            />
                            <span class="sr-only">Estado de la conexión: </span>
                            {{ connected ? 'En vivo' : 'Reconectando…' }}
                        </span>
                    </div>

                    <div
                        class="turn-banner mt-4"
                        :class="{ 'turn-banner--you': isMyTurn }"
                    >
                        <p
                            class="text-xs font-semibold tracking-wider text-[var(--c-muted)] uppercase"
                        >
                            Turno
                        </p>
                        <div
                            v-if="currentPlayer"
                            class="mt-2 flex items-center gap-2"
                        >
                            <PlayerBadge
                                :color="currentPlayer.color"
                                :label="playerLabel(currentPlayer.color)"
                                :size="24"
                            />
                            <span class="text-sm font-bold text-[var(--c-ink)]">
                                {{ currentPlayer.nickname }}
                                <span v-if="isMyTurn">· tú</span>
                            </span>
                        </div>
                        <p v-else class="mt-2 text-sm text-[var(--c-muted)]">
                            —
                        </p>
                    </div>

                    <p v-if="snapshot.state.consecutiveSixes" class="hint mt-3">
                        Seises consecutivos:
                        {{ snapshot.state.consecutiveSixes }}/3
                    </p>

                    <div class="mt-4 grid gap-2">
                        <button
                            ref="rollButton"
                            type="button"
                            class="btn btn-primary w-full"
                            :disabled="!canRoll"
                            @click="doRoll"
                        >
                            <span
                                v-if="busy"
                                class="spinner"
                                aria-hidden="true"
                            />
                            {{ busy ? 'Procesando…' : 'Tirar dado' }}
                        </button>

                        <p
                            v-if="
                                isMyTurn &&
                                snapshot.pendingRoll !== null &&
                                legalMoves.length === 0
                            "
                            class="hint"
                        >
                            No hay movimientos legales; el turno pasará
                            automáticamente.
                        </p>
                        <p
                            v-else-if="
                                isMyTurn && snapshot.pendingRoll !== null
                            "
                            class="hint"
                        >
                            Elige una ficha resaltada en el tablero.
                        </p>
                        <p v-else-if="!isMyTurn" class="hint">
                            Esperando el turno de
                            {{ playerName(snapshot.currentPlayerId) }}…
                        </p>
                        <p class="hint">
                            Atajo: pulsa <kbd class="font-bold">R</kbd> para
                            tirar. En el tablero usa Tab o flechas y luego Intro
                            o Espacio.
                        </p>
                    </div>

                    <p
                        v-if="error"
                        class="notice notice--error mt-3"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                    <p
                        class="notice mt-3"
                        :class="{ 'sr-only': announcement === '' }"
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        {{ announcement }}
                    </p>
                </div>

                <section
                    v-if="canMove"
                    ref="legalList"
                    class="surface-card p-4"
                >
                    <h2 class="mb-3 text-sm font-bold text-[var(--c-ink)]">
                        Tus fichas legales
                    </h2>
                    <div class="grid gap-2">
                        <button
                            v-for="piece in pieces.filter((item) =>
                                legalMoves.includes(item.id),
                            )"
                            :key="piece.id"
                            type="button"
                            data-legal-piece-button
                            class="btn btn-secondary w-full justify-start"
                            :disabled="busy"
                            :aria-label="pieceAccessibleLabel(piece)"
                            @click="doMove(piece.id)"
                        >
                            <PlayerBadge
                                :color="
                                    snapshot.players.find(
                                        (p) => p.id === piece.playerId,
                                    )?.color ?? 'red'
                                "
                                :label="
                                    playerLabel(
                                        snapshot.players.find(
                                            (p) => p.id === piece.playerId,
                                        )?.color ?? 'red',
                                    )
                                "
                                :size="20"
                            />
                            Ficha {{ piece.slot }}
                            <span class="hint">
                                {{
                                    piece.progress === null
                                        ? 'en casa'
                                        : `paso ${piece.progress}`
                                }}
                            </span>
                        </button>
                    </div>
                </section>

                <section class="surface-card p-4">
                    <h2 class="mb-3 text-sm font-bold text-[var(--c-ink)]">
                        Jugadores
                    </h2>
                    <PlayerList :players="snapshot.players" />
                </section>

                <section class="surface-card p-4">
                    <h2 class="mb-3 text-sm font-bold text-[var(--c-ink)]">
                        Últimos movimientos
                    </h2>
                    <div class="event-log">
                        <p v-if="log.length === 0" class="hint">
                            Todavía no hay movimientos.
                        </p>
                        <ol v-else class="space-y-0.5">
                            <li
                                v-for="(item, index) in log"
                                :key="index"
                                class="py-0.5"
                            >
                                {{ item }}
                            </li>
                        </ol>
                    </div>
                    <button
                        type="button"
                        class="btn btn-ghost mt-3 w-full"
                        @click="toggleSound"
                    >
                        Sonido {{ soundEnabled ? 'activado' : 'desactivado' }}
                    </button>
                </section>
            </aside>
        </div>
    </AppShell>
</template>
