<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useConnectionStatus, useEchoPresence } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppShell from '@/components/AppShell.vue';
import BoardPreview from '@/components/BoardPreview.vue';
import PlayerList from '@/components/PlayerList.vue';
import { configureGameEcho } from '@/lib/echo';
import {
    forgetPlayerToken,
    playerToken,
    TOKEN_HEADER,
} from '@/lib/playerSession';
import { followRematch } from '@/lib/rematch';
import type { Room } from '@/types';

const props = defineProps<{ room: Room }>();

configureGameEcho({
    [TOKEN_HEADER]: playerToken(props.room.code) ?? '',
});

const room = ref<Room>(props.room);
const notice = ref<string>('');
const copied = ref(false);
const starting = ref(false);
const connection = useConnectionStatus();

watch(
    () => props.room,
    (value) => {
        room.value = value;
    },
);

type RoomEventPayload = {
    room?: Room;
    oldCode?: string;
    newCode?: string;
    message: string;
};

const { leaveChannel } = useEchoPresence<RoomEventPayload>(
    `room.${props.room.code}`,
    [
        '.PlayerJoined',
        '.PlayerLeft',
        '.HostChanged',
        '.GameStarted',
        '.RematchCreated',
    ],
    (payload) => {
        notice.value = payload.message;

        if (payload.room) {
            room.value = payload.room;
        }

        if (payload.newCode && payload.oldCode === room.value.code) {
            void followRematch(payload.oldCode, payload.newCode);
            return;
        }

        if (room.value.status === 'in_progress') {
            router.visit(`/games/${room.value.code}`);
        }
    },
);

onBeforeUnmount(() => leaveChannel());

const hostName = computed(
    () => room.value.players.find((player) => player.isHost)?.nickname ?? '—',
);
const isHost = computed(() =>
    room.value.players.some((player) => player.isHost && player.isYou),
);
const isStarted = computed(() => room.value.status === 'in_progress');
const connected = computed(() => connection.value === 'connected');

const startGame = () => {
    starting.value = true;
    router.post(
        `/rooms/${props.room.code}/start`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (starting.value = false),
        },
    );
};

const leaveRoom = () => {
    router.post(
        `/rooms/${props.room.code}/leave`,
        {},
        {
            onSuccess: () => forgetPlayerToken(props.room.code),
        },
    );
};

const copyCode = async () => {
    await navigator.clipboard.writeText(room.value.code);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1500);
};
</script>

<template>
    <AppShell
        wide
        :eyebrow="isStarted ? 'Partida iniciada' : 'Sala de espera'"
        :title="isStarted ? 'Abriendo el tablero…' : 'Prepara la partida'"
        :subtitle="
            isStarted
                ? `El tablero ha quedado fijado con ${room.arms ?? room.board.arms} brazos.`
                : 'Comparte el código. La previsualización cambia en vivo según el número de jugadores.'
        "
        :show-history="false"
    >
        <div class="space-y-4">
            <header
                class="surface-card flex flex-wrap items-center justify-between gap-4 p-5"
            >
                <div>
                    <p class="label mb-1">Código de sala</p>
                    <button
                        type="button"
                        class="room-code text-left hover:underline"
                        :aria-label="`Código de sala ${room.code}. ${copied ? 'Copiado' : 'Pulsa para copiar'}`"
                        :title="copied ? 'Copiado' : 'Copiar código'"
                        @click="copyCode"
                    >
                        {{ room.code }}
                    </button>
                    <p class="hint mt-1">
                        {{ copied ? '¡Copiado!' : 'Pulsa para copiar' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="chip" role="status" aria-live="polite">
                        <span
                            class="status-dot"
                            :class="connected ? 'status-dot--online' : ''"
                            aria-hidden="true"
                        />
                        {{ connected ? 'En vivo' : 'Conectando…' }}
                    </span>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="leaveRoom"
                    >
                        Salir
                    </button>
                </div>
            </header>

            <p v-if="notice" class="notice" role="status">{{ notice }}</p>

            <div
                class="grid gap-4 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]"
            >
                <section class="surface-card p-5">
                    <div class="mb-4 flex items-baseline justify-between gap-3">
                        <h2 class="text-lg font-bold text-[var(--c-ink)]">
                            Jugadores
                        </h2>
                        <span class="chip"
                            >{{ room.playerCount }} /
                            {{ room.maxPlayers }}</span
                        >
                    </div>

                    <p class="mb-4 text-sm text-[var(--c-muted)]">
                        Anfitrión:
                        <strong class="text-[var(--c-ink)]">{{
                            hostName
                        }}</strong>
                    </p>

                    <PlayerList
                        :players="room.players"
                        :host-id="room.hostPlayerId"
                    />

                    <div
                        v-if="!isStarted"
                        class="mt-5 border-t border-[var(--c-line)] pt-4"
                    >
                        <button
                            v-if="isHost && room.canStart"
                            type="button"
                            class="btn btn-primary w-full"
                            :disabled="starting"
                            @click="startGame"
                        >
                            <span
                                v-if="starting"
                                class="spinner"
                                aria-hidden="true"
                            />
                            {{ starting ? 'Iniciando…' : 'Empezar partida' }}
                        </button>
                        <p
                            v-else-if="isHost"
                            class="text-sm text-[var(--c-muted)]"
                        >
                            Necesitas al menos {{ room.minPlayers }} jugadores
                            para empezar.
                        </p>
                        <p v-else class="text-sm text-[var(--c-muted)]">
                            Esperando a que {{ hostName }} inicie la partida…
                        </p>
                    </div>
                </section>

                <section class="surface-card p-5">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 class="text-lg font-bold text-[var(--c-ink)]">
                            Previsualización
                        </h2>
                        <span class="chip">
                            {{
                                isStarted ? 'Tablero fijado' : 'Cambia en vivo'
                            }}
                            · {{ room.board.arms }} brazos
                        </span>
                    </div>
                    <BoardPreview :board="room.board" />
                    <p class="hint mt-3 text-center">
                        Los brazos sin jugador se dibujan igual, pero no tienen
                        fichas ni casillas seguras.
                    </p>
                </section>
            </div>
        </div>
    </AppShell>
</template>
