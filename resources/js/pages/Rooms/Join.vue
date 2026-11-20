<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useId } from 'vue';
import AppShell from '@/components/AppShell.vue';
import ColorPicker from '@/components/ColorPicker.vue';
import type { ColorOption } from '@/types';

const props = defineProps<{
    code: string;
    colors: ColorOption[];
    maxPlayers: number;
}>();

const nicknameId = useId();
const nicknameErrorId = useId();
const generalErrorId = useId();
const hintId = useId();

const form = useForm({
    nickname: '',
    color: 'red',
    code: props.code,
});

const generalError = () => form.errors.code ?? '';
</script>

<template>
    <AppShell
        eyebrow="Sala compartida"
        :title="`Únete a ${props.code}`"
        :subtitle="`Elige apodo y una combinación de color y forma libre. Máximo ${maxPlayers} jugadores por partida.`"
    >
        <section class="surface-panel mx-auto max-w-lg p-6 sm:p-8">
            <form
                class="grid gap-6"
                novalidate
                @submit.prevent="form.post(`/rooms/${props.code}/join`)"
            >
                <div>
                    <label :for="nicknameId" class="label">Apodo</label>
                    <input
                        :id="nicknameId"
                        v-model="form.nickname"
                        type="text"
                        maxlength="24"
                        autocomplete="nickname"
                        placeholder="Tu nombre en la partida"
                        class="field"
                        aria-required="true"
                        :aria-invalid="Boolean(form.errors.nickname)"
                        :aria-describedby="
                            form.errors.nickname ? nicknameErrorId : hintId
                        "
                        v-focus
                    />
                    <p
                        v-if="form.errors.nickname"
                        :id="nicknameErrorId"
                        class="error-text"
                        role="alert"
                    >
                        {{ form.errors.nickname }}
                    </p>
                    <p v-else :id="hintId" class="hint mt-2">
                        Pulsa Intro para entrar en la sala.
                    </p>
                </div>

                <ColorPicker
                    v-model="form.color"
                    :colors="colors"
                    :error="form.errors.color"
                />

                <p
                    v-if="generalError()"
                    :id="generalErrorId"
                    class="notice notice--error"
                    role="alert"
                >
                    {{ generalError() }}
                </p>

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
                    {{ form.processing ? 'Entrando…' : 'Entrar en la sala' }}
                </button>
            </form>
        </section>
    </AppShell>
</template>
