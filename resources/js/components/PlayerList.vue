<script setup lang="ts">
import PlayerBadge from '@/components/PlayerBadge.vue';
import { playerLabel } from '@/lib/playerStyle';
import type { RoomPlayer } from '@/types';

defineProps<{
    players: RoomPlayer[];
    hostId?: number | null;
}>();
</script>

<template>
    <ul class="space-y-2">
        <li
            v-for="(player, index) in players"
            :key="player.id"
            class="player-row"
        >
            <PlayerBadge
                :color="player.color"
                :label="playerLabel(player.color)"
                :size="26"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-[var(--c-ink)]">
                    {{ player.nickname }}
                    <span
                        v-if="player.id === hostId"
                        class="ml-1 text-[var(--c-accent)]"
                        title="Anfitrión"
                        aria-hidden="true"
                        >★</span
                    >
                    <span v-if="player.id === hostId" class="sr-only">
                        (anfitrión)</span
                    >
                </p>
                <p class="text-xs text-[var(--c-muted)]">
                    {{ playerLabel(player.color) }} · Puesto {{ index + 1 }}
                </p>
            </div>
            <span class="flex items-center gap-2">
                <span
                    class="status-dot"
                    :class="player.isConnected ? 'status-dot--online' : ''"
                    aria-hidden="true"
                />
                <span class="sr-only">{{
                    player.isConnected ? 'Conectado' : 'Desconectado'
                }}</span>
            </span>
        </li>
    </ul>
</template>
