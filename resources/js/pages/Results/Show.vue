<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '@/components/AppShell.vue';
import PlayerBadge from '@/components/PlayerBadge.vue';
import { playerLabel } from '@/lib/playerStyle';
import type { GameResult, ResultRankingEntry } from '@/types';

const props = defineProps<{ result: GameResult }>();

const ranking = computed<ResultRankingEntry[]>(
    () => props.result.ranking ?? [],
);
const winner = computed(() => props.result.winner ?? null);

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
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(raw));
});
</script>

<template>
    <AppShell
        eyebrow="Detalle público"
        :title="`Partida ${result.game_code}`"
        :subtitle="`${result.board_arms} brazos · finalizada el ${dateLabel}`"
    >
        <section class="surface-panel p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-4">
                <PlayerBadge
                    v-if="winner"
                    :color="winner.color"
                    :label="playerLabel(winner.color)"
                    :size="48"
                />
                <div>
                    <p class="eyebrow">Ganador</p>
                    <h2 class="text-2xl font-bold text-[var(--c-ink)]">
                        {{ winner?.nickname ?? '—' }}
                    </h2>
                </div>
            </div>

            <div class="stat-grid mt-6">
                <div class="stat">
                    <p class="stat-value">{{ duration }}</p>
                    <p class="stat-label">Duración</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ result.turns }}</p>
                    <p class="stat-label">Turnos</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ result.total_rolls }}</p>
                    <p class="stat-label">Tiradas</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ result.total_captures }}</p>
                    <p class="stat-label">Capturas</p>
                </div>
            </div>
        </section>

        <section class="surface-card mt-4 p-5">
            <h2 class="mb-4 text-lg font-bold text-[var(--c-ink)]">
                Clasificación
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <caption class="sr-only">
                        Clasificación final de la partida
                        {{
                            result.game_code
                        }}
                    </caption>
                    <thead>
                        <tr class="border-b border-[var(--c-line)]">
                            <th scope="col" class="py-2 pr-3">Puesto</th>
                            <th scope="col" class="py-2 pr-3">Jugador</th>
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
                            <td class="py-2 pr-3 font-bold">{{ index + 1 }}</td>
                            <td class="py-2 pr-3">
                                <span class="flex items-center gap-2">
                                    <PlayerBadge
                                        :color="entry.color"
                                        :label="playerLabel(entry.color)"
                                        :size="26"
                                    />
                                    <span>
                                        <span
                                            class="block font-bold text-[var(--c-ink)]"
                                            >{{ entry.nickname }}</span
                                        >
                                        <span class="hint"
                                            >{{ playerLabel(entry.color) }} ·
                                            Puesto {{ entry.seat + 1 }}</span
                                        >
                                    </span>
                                </span>
                            </td>
                            <td class="py-2 pr-3">{{ entry.pieces_meta }}/4</td>
                            <td class="py-2 pr-3">
                                {{ entry.progress_total }}
                            </td>
                            <td class="py-2">{{ entry.captures }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4 flex justify-center">
            <Link href="/partidas" class="btn btn-secondary"
                >Volver al historial</Link
            >
        </div>
    </AppShell>
</template>
