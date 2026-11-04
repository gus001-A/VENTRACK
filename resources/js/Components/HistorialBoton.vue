<script setup lang="ts">
/** Botón "Bitácora" que muestra el historial de cambios de un solo registro. */
import { ref } from 'vue';
import axios from 'axios';
import { HistoryOutlined, UserOutlined, CalendarOutlined, ClockCircleOutlined, PlusCircleOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import { fechaCorta, hora12 } from '@/utils';
import CambiosDetalle from './CambiosDetalle.vue';

const props = defineProps<{ tabla: string; id: number; titulo?: string }>();

const open = ref(false);
const cargando = ref(false);
const logs = ref<any[]>([]);

async function ver() {
    open.value = true;
    cargando.value = true;
    try {
        logs.value = (await axios.get('/bitacora/registro', { params: { tabla: props.tabla, id: props.id } })).data;
    } finally {
        cargando.value = false;
    }
}

const ACC: Record<string, { t: string; c: string; hex: string; i: any }> = {
    INSERT: { t: 'Registro', c: 'green', hex: '#1faf5c', i: PlusCircleOutlined },
    UPDATE: { t: 'Cambio', c: 'blue', hex: '#0461d8', i: EditOutlined },
    DELETE: { t: 'Baja', c: 'red', hex: '#e5484d', i: DeleteOutlined },
};
</script>

<template>
    <a-button class="vt-btn-hist" @click="ver"><HistoryOutlined /> Bitácora</a-button>

    <a-modal v-model:open="open" :footer="null" width="min(760px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #5d7085, #0a1f44)"><HistoryOutlined /></span>
                <div>Bitácora del registro<small>{{ titulo || 'Usuario, fecha y hora de cada movimiento' }}</small></div>
            </div>
        </template>
        <a-spin :spinning="cargando">
            <div class="vt-hist">
                <div v-for="l in logs" :key="l.id" class="vt-hist-item" :style="{ '--c': ACC[l.accion]?.hex }">
                    <span class="vt-hist-ic"><component :is="ACC[l.accion]?.i" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a-tag :color="ACC[l.accion]?.c" class="m-0">{{ ACC[l.accion]?.t }}</a-tag>
                            <span><UserOutlined style="color: var(--vt-blue)" /> <b>{{ l.usuario }}</b> <span class="vt-muted vt-small">{{ l.perfil }}</span></span>
                            <span class="vt-muted vt-small ml-auto whitespace-nowrap"><CalendarOutlined /> {{ fechaCorta(l.fecha) }} <ClockCircleOutlined class="ml-1" /> {{ hora12(l.hora, true) }}</span>
                        </div>
                        <div class="mt-1">{{ l.descripcion }}</div>
                        <CambiosDetalle v-if="l.accion === 'UPDATE'" :cambios="l.cambios" />
                    </div>
                </div>
                <a-empty v-if="!cargando && !logs.length" description="Sin movimientos registrados" />
            </div>
        </a-spin>
    </a-modal>
</template>

<style>
.vt-btn-hist .anticon {
    color: var(--vt-teal);
}
.vt-hist {
    margin-top: 14px;
    max-height: 64vh;
    overflow: auto;
    padding-right: 4px;
}
.vt-hist-item {
    display: flex;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--vt-line);
    border-left: 4px solid var(--c);
    border-radius: 12px;
    margin-bottom: 10px;
    animation: vt-rise 0.3s both;
}
.vt-hist-ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex: none;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 12%, transparent);
}
</style>
