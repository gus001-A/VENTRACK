<script setup lang="ts">
import { computed } from 'vue';
import {
    CalendarOutlined, PlayCircleOutlined, CheckCircleOutlined, EyeOutlined, SafetyCertificateOutlined,
} from '@ant-design/icons-vue';
import { ESTATUS_INFO, ESTATUS_DOT, type Estatus } from '@/utils';

const props = defineProps<{ estatus: Estatus; suave?: boolean }>();
const ICONS = {
    programada: CalendarOutlined, proceso: PlayCircleOutlined, realizada: CheckCircleOutlined,
    revisada: EyeOutlined, supervisada: SafetyCertificateOutlined,
};
const info = computed(() => ESTATUS_INFO[props.estatus]);
const estilo = computed(() =>
    props.suave ? { color: info.value.color, background: info.value.bg } : { background: ESTATUS_DOT[props.estatus] },
);
</script>

<template>
    <span class="vt-tag" :class="{ solido: !suave }" :style="estilo">
        <component :is="ICONS[estatus]" />{{ info.label }}
    </span>
</template>
