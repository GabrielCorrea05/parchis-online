<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import AppShell from '@/components/AppShell.vue';
import PlayerBadge from '@/components/PlayerBadge.vue';
import { configureGameEcho } from '@/lib/echo';
import { createRematch } from '@/lib/gameApi';
import { playerToken, TOKEN_HEADER } from '@/lib/playerSession';
import { playerLabel } from '@/lib/playerStyle';
import { followRematch } from '@/lib/rematch';
import type {
    GameSnapshot,
    RematchCreatedEventPayload,
    ResultRankingEntry,
} from '@/types';

const props = defineProps<{
    result: Record<string, unknown> & {
        game_code?: string;
        status?: string;
        winner?: { id?: number; nickname?: string; color?: string } | null;
        ranking?: ResultRankingEntry[];
        board_arms?: number;
        duration_seconds?: number;
        turns?: number;
        total_rolls?: number;
        total_captures?: number;
        started_at?: string | null;
        finished_at?: string | null;
        message?: string;
    };
    snapshot: GameSnapshot;
}>();

configureGameEcho({
    [TOKEN_HEADER]:
        playerToken(props.result.game_code ?? props.snapshot.code) ?? '',
});

const busy = ref(false);
const error = ref('');
const notice = ref('');
const connection = useConnectionStatus();
const connected = computed(() => connection.value === 'connected');
const code = computed(() => props.result.game_code ?? props.snapshot.code);
const ranking = computed<ResultRankingEntry[]>(
    () => props.result.ranking ?? [],
);
const winner = computed(() => props.result.winner ?? null);
const isHost = computed(() =>
    props.snapshot.players.some((player) => player.isHost && player.isYou),
);
const isYouWinner = computed(
    () => winner.value?.id === props.snapshot.youPlayerId,
);

useEcho<RematchCreatedEventPayload>(
    `game.${props.snapshot.gameId}`,
    '.RematchCreated',
    (payload) => {
        notice.value = payload.message ?? 'Nueva sala creada.';
        void followRematch(payload.oldCode, payload.newCode);
    },
);

const duration = computed(() => {
    const seconds = Number(props.result.duration_seconds ?? 0);
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return `${minutes} min ${String(rest).padStart(2, '0')} s`;
});

const dateLabel = computed(() => {
    const raw = props.result.finished_at;
    if (!raw) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(raw));
});

async function rematch(): Promise<void> {
    if (!isHost.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        const response = await createRematch(code.value);
        notice.value = response.message ?? 'Nueva sala creada.';
        await followRematch(code.value, response.newCode);
    } catch (caught) {
        error.value =
            caught instanceof Error
                ? caught.message
                : 'No se pudo crear la revancha.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AppShell
        wide
        eyebrow="Partida terminada"
        :title="isYouWinner ? '¡Has ganado!' : 'Resultado final'"
    >
        <div
            v-if="props.result.status === 'en_curso'"
            class="surface-panel mx-auto max-w-lg p-8 text-center"
        >
            <p class="text-lg font-bold text-[var(--c-ink)]">
                La partida aún está en curso.
            </p>
            <p class="hint mt-2">{{ props.result.message }}</p>
            <Link href="/partidas" class="btn btn-secondary mt-6"
                >Ver historial</Link
            >
        </div>

        <template v-else>
            <section class="surface-panel win-celebrate overflow-hidden">
                <div
                    class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center sm:p-8"
                >
                    <div
                        class="grid h-24 w-24 place-items-center rounded-full bg-[var(--c-accent-soft)]"
                    >
                        <PlayerBadge
                            v-if="winner"
                            :color="winner.color ?? 'red'"
                            :label="playerLabel(winner.color ?? 'red')"
                            :size="64"
                        />
                    </div>
                    <div>
                        <p class="eyebrow">Ganador</p>
                        <h2 class="display-title">
                            {{ winner?.nickname ?? '—' }}
                        </h2>
                        <p class="subtitle">
                            Sala {{ code }} ·
                            {{ props.result.board_arms }} brazos ·
                            {{ dateLabel }}
                        </p>
                    </div>
                </div>

                <div
                    class="stat-grid border-t border-[var(--c-line)] p-6 sm:p-8"
                >
                    <div class="stat">
                        <p class="stat-value">{{ duration }}</p>
                        <p class="stat-label">Duración</p>
                    </div>
                    <div class="stat">
                        <p class="stat-value">{{ props.result.turns ?? 0 }}</p>
                        <p class="stat-label">Turnos</p>
                    </div>
                    <div class="stat">
                        <p class="stat-value">
                            {{ props.result.total_rolls ?? 0 }}
                        </p>
                        <p class="stat-label">Tiradas</p>
                    </div>
                    <div class="stat">
                        <p class="stat-value">
                            {{ props.result.total_captures ?? 0 }}
                        </p>
                        <p class="stat-label">Capturas</p>
                    </div>
                </div>
            </section>

            <div
                class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]"
            >
                <section class="surface-card p-5">
                    <h2 class="mb-4 text-lg font-bold text-[var(--c-ink)]">
                        Clasificación
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm">
                            <caption class="sr-only">
                                Clasificación final de la partida
                                {{
                                    code
                                }}
                            </caption>
                            <thead>
                                <tr class="border-b border-[var(--c-line)]">
                                    <th scope="col" class="py-2 pr-3">
                                        Puesto
                                    </th>
                                    <th scope="col" class="py-2 pr-3">
                                        Jugador
                                    </th>
                                    <th scope="col" class="py-2 pr-3">
                                        Fichas en meta
                                    </th>
                                    <th scope="col" class="py-2 pr-3">Pasos</th>
                                    <th scope="col" class="py-2">Capturas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in ranking"
                                    :key="entry.playerId"
                                    class="border-b border-[var(--c-line)] last:border-0"
                                    :class="{
                                        'bg-[var(--c-accent-soft)]':
                                            entry.playerId === winner?.id,
                                    }"
                                >
                                    <td class="py-2 pr-3 font-bold">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-2 pr-3">
                                        <span class="flex items-center gap-2">
                                            <PlayerBadge
                                                :color="entry.color"
                                                :label="
                                                    playerLabel(entry.color)
                                                "
                                                :size="26"
                                            />
                                            <span>
                                                <span
                                                    class="block font-bold text-[var(--c-ink)]"
                                                    >{{ entry.nickname }}</span
                                                >
                                                <span class="hint"
                                                    >{{
                                                        playerLabel(entry.color)
                                                    }}
                                                    · Puesto
                                                    {{ entry.seat + 1 }}</span
                                                >
                                            </span>
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{ entry.pieces_meta }}/4
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{ entry.progress_total }}
                                    </td>
                                    <td class="py-2">{{ entry.captures }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="surface-card p-5">
                        <h2 class="mb-3 text-lg font-bold text-[var(--c-ink)]">
                            Volver a jugar
                        </h2>
                        <p
                            v-if="notice"
                            class="notice notice--success mb-3"
                            role="status"
                        >
                            {{ notice }}
                        </p>
                        <p
                            v-if="error"
                            class="notice notice--error mb-3"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <button
                            v-if="isHost"
                            type="button"
                            class="btn btn-primary w-full"
                            :disabled="busy"
                            @click="rematch"
                        >
                            <span
                                v-if="busy"
                                class="spinner"
                                aria-hidden="true"
                            />
                            {{ busy ? 'Creando sala…' : 'Crear revancha' }}
                        </button>
                        <p v-else class="hint">
                            El anfitrión creará la revancha. Entrarás
                            automáticamente con tu mismo apodo, color y token.
                        </p>
                    </section>

                    <section class="surface-card p-5">
                        <span class="chip" role="status" aria-live="polite">
                            <span
                                class="status-dot"
                                :class="connected ? 'status-dot--online' : ''"
                                aria-hidden="true"
                            />
                            {{ connected ? 'En vivo' : 'Conectando…' }}
                        </span>
                        <div class="mt-4 grid gap-2">
                            <Link
                                :href="`/partidas/${code}`"
                                class="btn btn-secondary w-full"
                                >Detalle público</Link
                            >
                            <Link href="/partidas" class="btn btn-ghost w-full"
                                >Ver historial</Link
                            >
                        </div>
                    </section>
                </aside>
            </div>
        </template>
    </AppShell>
</template>
