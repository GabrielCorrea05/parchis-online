<script setup lang="ts">
import AppShell from '@/components/AppShell.vue';
import ParchisBoard from '@/components/ParchisBoard.vue';
import type { GameBoard } from '@/types';

defineProps<{ board: GameBoard }>();
</script>

<template>
    <AppShell
        title="Tablero clásico"
        eyebrow="Vista de prueba"
        subtitle="Dibujo estático del parchís español de 19×19. Sin fichas, dado ni lógica de juego."
        wide
        :show-history="false"
    >
        <div class="board-frame">
            <ParchisBoard
                :board="board"
                :pieces="[]"
                description-id="board-preview-description"
            />
        </div>

        <p id="board-preview-description" class="sr-only">
            Tablero de parchís español con 68 casillas numeradas del 1 al 68 en
            sentido antihorario. Salidas: amarillo 5, azul 22, rojo 39 y verde
            56. Entradas a los pasillos: verde 17, amarillo 68, azul 34 y rojo
            51. Hay doce casillas seguras marcadas con una estrella.
        </p>

        <div
            class="mt-6 rounded-xl border border-[var(--c-line)] bg-[var(--c-surface)] p-4 text-sm"
        >
            <h2 class="font-semibold">Comprobación visual</h2>
            <ul class="mt-2 list-inside list-disc space-y-1 opacity-80">
                <li>
                    Rejilla de 19×19 con cruz de cuatro brazos y centro de 3×3.
                </li>
                <li>
                    Recorrido antihorario: la casilla 1 está en la punta
                    inferior derecha.
                </li>
                <li>
                    Las doce seguras llevan estrella y los números permanecen
                    derechos.
                </li>
                <li>
                    Los cuatro brazos y sus casas se dibujan aunque no haya
                    jugadores.
                </li>
            </ul>
        </div>
    </AppShell>
</template>
