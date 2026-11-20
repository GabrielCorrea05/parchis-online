<script setup lang="ts">
import { useId } from 'vue';
import { useAccessibility, type TextScale } from '@/lib/accessibility';

const { textScale, reduceMotion, setTextScale, setReduceMotion } =
    useAccessibility();
const titleId = useId();
const motionId = useId();
const motionHintId = useId();

const scales: { value: TextScale; label: string }[] = [
    { value: 1, label: '100 %' },
    { value: 1.25, label: '125 %' },
    { value: 1.5, label: '150 %' },
    { value: 2, label: '200 %' },
];

function onScaleChange(event: Event): void {
    const value = Number((event.target as HTMLInputElement).value);
    if (value === 1 || value === 1.25 || value === 1.5 || value === 2) {
        setTextScale(value);
    }
}

function onMotionChange(event: Event): void {
    setReduceMotion((event.target as HTMLInputElement).checked);
}
</script>

<template>
    <details class="relative">
        <summary
            class="btn btn-ghost a11y-summary"
            aria-label="Ajustes de accesibilidad"
        >
            <svg
                viewBox="0 0 24 24"
                class="h-5 w-5"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
            >
                <circle cx="12" cy="4" r="1.6" />
                <path
                    d="M4 8h16M12 8v5m0 0-4 7m4-7 4 7"
                    stroke-linecap="round"
                />
            </svg>
            <span class="hidden sm:inline">Accesibilidad</span>
        </summary>

        <div class="a11y-panel">
            <h2 :id="titleId" class="text-sm font-bold text-[var(--c-ink)]">
                Accesibilidad
            </h2>

            <fieldset class="mt-3 border-0 p-0">
                <legend class="label">Tamaño del texto</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="option in scales"
                        :key="option.value"
                        class="a11y-option rounded-[var(--radius-sm)] border border-[var(--c-line)] px-3"
                    >
                        <input
                            type="radio"
                            name="a11y-text-scale"
                            :value="option.value"
                            :checked="textScale === option.value"
                            @change="onScaleChange"
                        />
                        <span class="text-sm text-[var(--c-ink)]">{{
                            option.label
                        }}</span>
                    </label>
                </div>
                <p class="hint mt-2">
                    También puedes ampliar con el zoom del navegador hasta el
                    200 %.
                </p>
            </fieldset>

            <div class="mt-4 border-t border-[var(--c-line)] pt-3">
                <label :for="motionId" class="a11y-option">
                    <input
                        :id="motionId"
                        type="checkbox"
                        :checked="reduceMotion"
                        :aria-describedby="motionHintId"
                        @change="onMotionChange"
                    />
                    <span class="text-sm text-[var(--c-ink)]"
                        >Reducir animaciones</span
                    >
                </label>
                <p :id="motionHintId" class="hint">
                    Se respeta la preferencia del sistema; este ajuste la
                    fuerza.
                </p>
            </div>
        </div>
    </details>
</template>
