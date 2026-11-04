<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ cambios: Record<string, any> | null }>();

/** Solo cambios campo → {antes, despues}; el resto se muestra como JSON. */
const filas = computed(() =>
    Object.entries(props.cambios || {}).map(([campo, v]) => ({
        campo: campo.replace(/_/g, ' '),
        antes: v && typeof v === 'object' && 'antes' in v ? v.antes : null,
        despues: v && typeof v === 'object' && 'despues' in v ? v.despues : v,
        simple: v && typeof v === 'object' && 'antes' in v,
    })),
);
const fmt = (x: any) => (x === null || x === undefined || x === '' ? '∅' : typeof x === 'object' ? JSON.stringify(x) : String(x));
</script>

<template>
    <div v-if="filas.length" class="vt-cambios">
        <div v-for="f in filas" :key="f.campo">
            <span class="vt-muted">{{ f.campo }}:</span>
            <template v-if="f.simple">
                <del>{{ fmt(f.antes) }}</del> → <b>{{ fmt(f.despues) }}</b>
            </template>
            <code v-else>{{ fmt(f.despues) }}</code>
        </div>
    </div>
</template>

<style>
.vt-cambios {
    margin-top: 6px;
    font-size: 12.5px;
    background: #f7fafc;
    border: 1px solid var(--vt-line);
    border-radius: 8px;
    padding: 6px 10px;
    word-break: break-word;
}
.vt-cambios del {
    color: #c0262e;
}
.vt-cambios code {
    font-size: 11.5px;
}
</style>
