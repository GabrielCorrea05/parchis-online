<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppShell from '@/components/AppShell.vue';
import PlayerBadge from '@/components/PlayerBadge.vue';
import { playerLabel } from '@/lib/playerStyle';
import type { GameHistoryEntry } from '@/types';

const props = defineProps<{ history: GameHistoryEntry[] }>();

function winnerInfo(entry: GameHistoryEntry): {
    nickname: string;
    color: string;
} {
    const winner = entry.winner;
    if (winner && typeof winner === 'object') {
        return {
            nickname: winner.nickname ?? entry.nickname ?? '—',
            color: winner.color ?? entry.color ?? 'red',
        };
    }

    return {
        nickname: typeof winner === 'string' ? winner : (entry.nickname ?? '—'),
        color: entry.color ?? 'red',
    };
}

function durationLabel(seconds = 0): string {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return `${minutes} min ${String(rest).padStart(2, '0')} s`;
}

function dateLabel(raw?: string): string {
    if (!raw) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(raw));
}
</script>

<template>
    <AppShell
        eyebrow="Registro público"
        title="Historial de partidas"
        subtitle="Solo se publican partidas terminadas: ganador, duración y enlace al resultado completo."
    >
        <section
            v-if="props.history.length === 0"
            class="surface-panel mx-auto max-w-xl p-10 text-center"
        >
            <h2 class="text-lg font-bold text-[var(--c-ink)]">
                Todavía no hay partidas terminadas.
            </h2>
            <p class="hint mt-2">
                Cuando alguien gane, su resultado aparecerá aquí.
            </p>
            <Link href="/" class="btn btn-primary mt-6">Crear una sala</Link>
        </section>

        <ol
            v-else
            class="surface-panel divide-y divide-[var(--c-line)] overflow-hidden"
        >
            <li v-for="entry in props.history" :key="entry.code">
                <Link
                    :href="`/partidas/${entry.code}`"
                    class="grid gap-3 p-5 transition hover:bg-[var(--c-surface-2)] sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <PlayerBadge
                            :color="winnerInfo(entry).color"
                            :label="playerLabel(winnerInfo(entry).color)"
                            :size="30"
                        />
                        <span class="min-w-0">
                            <span
                                class="block truncate text-sm font-bold text-[var(--c-ink)]"
                            >
                                {{ winnerInfo(entry).nickname }} gana la sala
                                {{ entry.code }}
                            </span>
                            <span class="hint"
                                >{{ entry.playerCount ?? 0 }} jugadores ·
                                {{ durationLabel(entry.durationSeconds) }}</span
                            >
                        </span>
                    </span>
                    <span class="text-left sm:text-right">
                        <span
                            class="block text-sm font-semibold text-[var(--c-ink)]"
                            >{{ dateLabel(entry.finishedAt) }}</span
                        >
                        <span class="hint">Ver detalle →</span>
                    </span>
                </Link>
            </li>
        </ol>
    </AppShell>
</template>
