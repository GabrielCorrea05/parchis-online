<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccessibilitySettings from '@/components/AccessibilitySettings.vue';
import SoundToggle from '@/components/SoundToggle.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        eyebrow?: string;
        wide?: boolean;
        showHistory?: boolean;
    }>(),
    {
        subtitle: '',
        eyebrow: '',
        wide: false,
        showHistory: true,
    },
);

const shellClass = computed(() => (props.wide ? 'max-w-7xl' : 'max-w-3xl'));
</script>

<template>
    <Head :title="props.title" />

    <div class="app-shell min-h-screen">
        <a href="#contenido-principal" class="skip-link"
            >Saltar al contenido principal</a
        >

        <header class="app-header">
            <div
                :class="[
                    'mx-auto flex w-full items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8',
                    shellClass,
                ]"
            >
                <Link
                    href="/"
                    class="brand"
                    aria-label="Parchís online, ir al inicio"
                >
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" class="h-6 w-6">
                            <circle
                                cx="9"
                                cy="9"
                                r="4"
                                fill="var(--player-red)"
                            />
                            <circle
                                cx="23"
                                cy="9"
                                r="4"
                                fill="var(--player-yellow)"
                            />
                            <circle
                                cx="9"
                                cy="23"
                                r="4"
                                fill="var(--player-blue)"
                            />
                            <circle
                                cx="23"
                                cy="23"
                                r="4"
                                fill="var(--player-green)"
                            />
                        </svg>
                    </span>
                    <span class="brand-text">Parchís online</span>
                </Link>

                <nav
                    class="flex items-center gap-1"
                    aria-label="Controles generales"
                >
                    <Link
                        v-if="props.showHistory"
                        href="/partidas"
                        class="btn btn-ghost hidden sm:inline-flex"
                    >
                        Historial
                    </Link>
                    <SoundToggle />
                    <ThemeToggle />
                    <AccessibilitySettings />
                </nav>
            </div>
        </header>

        <main
            id="contenido-principal"
            tabindex="-1"
            :class="[
                'mx-auto w-full px-4 pt-6 pb-12 sm:px-6 sm:pt-10 lg:px-8',
                shellClass,
            ]"
        >
            <div class="page-enter">
                <header
                    v-if="props.eyebrow || props.title || props.subtitle"
                    class="mb-8 text-center"
                >
                    <p v-if="props.eyebrow" class="eyebrow">
                        {{ props.eyebrow }}
                    </p>
                    <h1 class="display-title">{{ props.title }}</h1>
                    <p v-if="props.subtitle" class="subtitle mx-auto">
                        {{ props.subtitle }}
                    </p>
                </header>

                <slot />
            </div>
        </main>
    </div>
</template>
