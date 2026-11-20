<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/lib/theme';

const { preference, toggle } = useTheme();
const label = computed(() => {
    if (preference.value === 'system') {
        return 'Tema: según el sistema. Cambiar a claro/oscuro';
    }

    return preference.value === 'dark'
        ? 'Tema oscuro. Cambiar a claro'
        : 'Tema claro. Cambiar a oscuro';
});
</script>

<template>
    <button
        type="button"
        class="btn btn-ghost h-11 w-11 !px-0"
        :title="label"
        :aria-label="label"
        @click="toggle"
    >
        <svg
            v-if="preference === 'dark'"
            viewBox="0 0 24 24"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"
            />
        </svg>
        <svg
            v-else
            viewBox="0 0 24 24"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" />
        </svg>
    </button>
</template>
