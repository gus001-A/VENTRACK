<script setup lang="ts">
/** Pie de los modales de alta/edición: quién registró (fecha y hora), bitácora del registro y botones. */
import { CloseOutlined, SaveOutlined } from '@ant-design/icons-vue';
import Registro from './Registro.vue';
import HistorialBoton from './HistorialBoton.vue';
import type { Traza } from '@/utils';

defineProps<{ tabla?: string; registro?: { id: number; traza?: Traza | null } | null; guardando?: boolean; textoOk?: string }>();
const emit = defineEmits<{ cancelar: []; guardar: [] }>();
</script>

<template>
    <div class="vt-pie-modal">
        <div class="vt-pie-izq">
            <template v-if="registro && tabla">
                <span class="vt-small vt-muted">Registró</span>
                <Registro :traza="registro.traza" en-linea />
                <HistorialBoton :tabla="tabla" :id="registro.id" />
            </template>
        </div>
        <div class="flex gap-2">
            <a-button size="large" @click="emit('cancelar')"><CloseOutlined /> Cancelar</a-button>
            <a-button size="large" type="primary" :loading="guardando" @click="emit('guardar')"><SaveOutlined /> {{ textoOk || 'Guardar' }}</a-button>
        </div>
    </div>
</template>

<style>
.vt-pie-modal {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 14px;
    border-top: 1px solid var(--vt-line);
}
.vt-pie-izq {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    text-align: left;
}
</style>
