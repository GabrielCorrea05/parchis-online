<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useId } from 'vue';
import AppShell from '@/components/AppShell.vue';
import ColorPicker from '@/components/ColorPicker.vue';
import type { ColorOption } from '@/types';

defineProps<{
    colors: ColorOption[];
    maxPlayers: number;
}>();

const nicknameId = useId();
const nicknameErrorId = useId();
const codeId = useId();
const codeErrorId = useId();
const createHintId = useId();

const form = useForm({
    nickname: '',
    color: 'red',
    code: '',
});

const createRoom = () => form.post('/rooms');
const joinRoom = () =>
    form.post(`/rooms/${form.code.trim().toUpperCase()}/join`);
</script>

<template>
    <AppShell
        eyebrow="Juego de mesa en tu navegador"
        title="Parchís online"
        subtitle="Elige apodo, color y forma. Crea una sala para ser el anfitrión o entra con el código que te pasen. Sin registro y con el servidor validando cada tirada."
    >
        <section class="surface-panel mx-auto max-w-2xl p-6 sm:p-8">
            <div class="grid gap-6">
                <form
                    class="grid gap-6"
                    novalidate
                    @submit.prevent="createRoom"
                >
                    <div>
                        <label :for="nicknameId" class="label">Apodo</label>
                        <input
                            :id="nicknameId"
                            v-model="form.nickname"
                            type="text"
                            maxlength="24"
                            autocomplete="nickname"
                            placeholder="Cómo quieres que te vean"
                            class="field"
                            aria-required="true"
                            :aria-invalid="Boolean(form.errors.nickname)"
                            :aria-describedby="
                                form.errors.nickname
                                    ? nicknameErrorId
                                    : createHintId
                            "
                        />
                        <p
                            v-if="form.errors.nickname"
                            :id="nicknameErrorId"
                            class="error-text"
                            role="alert"
                        >
                            {{ form.errors.nickname }}
                        </p>
                        <p v-else :id="createHintId" class="hint mt-2">
                            Escribe tu apodo y pulsa Intro para crear la sala.
                        </p>
                    </div>

                    <ColorPicker
                        v-model="form.color"
                        :colors="colors"
                        :error="form.errors.color"
                    />

                    <button
                        type="submit"
                        class="btn btn-primary w-full"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner"
                            aria-hidden="true"
                        />
                        {{
                            form.processing
                                ? 'Creando…'
                                : 'Crear sala · ser host'
                        }}
                    </button>
                </form>

                <form
                    class="grid gap-3 border-t border-[var(--c-line)] pt-6"
                    novalidate
                    @submit.prevent="joinRoom"
                >
                    <label :for="codeId" class="label"
                        >¿Tienes un código? Únete a una sala</label
                    >
                    <div class="flex gap-2">
                        <input
                            :id="codeId"
                            v-model="form.code"
                            type="text"
                            maxlength="4"
                            inputmode="text"
                            autocomplete="off"
                            placeholder="ABCD"
                            class="field min-w-0 text-center font-bold tracking-[0.25em] uppercase"
                            aria-required="true"
                            :aria-invalid="Boolean(form.errors.code)"
                            :aria-describedby="
                                form.errors.code ? codeErrorId : undefined
                            "
                            @input="form.code = form.code.toUpperCase()"
                        />
                        <button
                            type="submit"
                            class="btn btn-secondary shrink-0"
                            :disabled="
                                form.processing || form.code.trim().length !== 4
                            "
                        >
                            Unirse
                        </button>
                    </div>
                    <p
                        v-if="form.errors.code"
                        :id="codeErrorId"
                        class="error-text"
                        role="alert"
                    >
                        {{ form.errors.code }}
                    </p>
                    <p class="hint">
                        Usa el mismo apodo y color/forma de arriba.
                    </p>
                </form>
            </div>

            <footer
                class="mt-8 flex flex-wrap items-center justify-center gap-2 border-t border-[var(--c-line)] pt-5"
            >
                <span class="chip">Hasta {{ maxPlayers }} jugadores</span>
                <span class="chip">Sin registro</span>
                <span class="chip">Turnos en tiempo real</span>
            </footer>
        </section>
    </AppShell>
</template>
