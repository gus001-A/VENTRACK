<script setup lang="ts">
/** Quién registró el registro, con fecha y hora (en una sola línea). */
import { UserOutlined, ClockCircleOutlined } from '@ant-design/icons-vue';
import { fechaCorta, hora12, type Traza } from '@/utils';

defineProps<{ traza?: Traza | null; enLinea?: boolean }>();
</script>

<template>
    <div class="vt-reg" :class="{ linea: enLinea }">
        <b class="vt-reg-u"><UserOutlined v-if="enLinea" /> {{ traza?.creado?.usuario ?? 'Sistema' }}</b>
        <span v-if="traza?.creado" class="vt-reg-f"><ClockCircleOutlined /> {{ fechaCorta(traza.creado.fecha) }}, {{ hora12(traza.creado.hora) }}</span>
    </div>
</template>

<style>
.vt-reg {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
    min-width: 0;
}
.vt-reg-u {
    font-size: 12.5px;
    text-transform: uppercase;
    color: var(--vt-navy);
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-reg-f {
    font-size: 11.5px;
    color: var(--vt-muted);
    white-space: nowrap;
}
.vt-reg-f .anticon {
    color: var(--vt-teal);
    font-size: 10.5px;
}
.vt-reg.linea {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.vt-reg.linea .vt-reg-u .anticon {
    color: var(--vt-blue);
}
</style>
