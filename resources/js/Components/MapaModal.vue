<script setup lang="ts">
/** Mapa de Google dentro de un modal (sin abrir otra pestaña). */
import { computed } from 'vue';
import { EnvironmentOutlined } from '@ant-design/icons-vue';

const props = defineProps<{ titulo: string; direccion?: string | null; latitud?: number | null; longitud?: number | null }>();
const open = defineModel<boolean>('open', { default: false });

const q = computed(() => (props.latitud && props.longitud ? `${props.latitud},${props.longitud}` : props.direccion || ''));
const src = computed(() => `https://maps.google.com/maps?q=${encodeURIComponent(q.value)}&z=16&output=embed`);
</script>

<template>
    <a-modal v-model:open="open" :footer="null" width="min(900px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="flex items-center gap-3 pr-8">
                <span class="vt-head-icon" style="width: 40px; height: 40px; font-size: 18px; background: linear-gradient(135deg, #f08c00, #e0457b)"><EnvironmentOutlined /></span>
                <div class="min-w-0">
                    <div class="truncate">{{ titulo }}</div>
                    <div class="vt-muted vt-small font-normal truncate">{{ direccion }}<template v-if="latitud"> · {{ latitud?.toFixed(5) }}, {{ longitud?.toFixed(5) }}</template></div>
                </div>
            </div>
        </template>
        <iframe v-if="open && q" :src="src" class="vt-mapa-modal" loading="lazy" referrerpolicy="no-referrer-when-downgrade" :title="titulo"></iframe>
    </a-modal>
</template>

<style>
.vt-mapa-modal {
    width: 100%;
    height: 68vh;
    border: 0;
    border-radius: 12px;
    display: block;
    margin-top: 10px;
}
</style>
