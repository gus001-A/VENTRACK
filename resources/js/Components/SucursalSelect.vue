<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ShopOutlined } from '@ant-design/icons-vue';
import { useShared } from '@/composables/useShared';

/**
 * Sucursal de trabajo (obligatoria). La selección se guarda en el servidor,
 * así Visitas, Prospectos, Usuarios y Productos muestran siempre la misma.
 */
const { props } = useShared();
const opciones = computed(() => props.value.misSucursales.map((s) => ({ value: s.id, label: s.nombre })));

function cambiar(id: number) {
    // Al cambiar de sucursal se reinician los filtros y la paginación
    router.get(window.location.pathname, { sucursal: id }, { preserveScroll: true });
}
</script>

<template>
    <div v-if="opciones.length" class="vt-suc-sel">
        <span class="vt-toolbar-label"><ShopOutlined /> Sucursal</span>
        <a-select :value="props.sucursalActual ?? undefined" :options="opciones" show-search option-filter-prop="label"
            style="width: 220px" @change="(v: any) => cambiar(v)">
            <template #suffixIcon><ShopOutlined /></template>
        </a-select>
    </div>
</template>

<style>
.vt-suc-sel {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding-right: 14px;
    border-right: 1px solid var(--vt-line);
}
.vt-suc-sel .ant-select-selector {
    font-weight: 700;
    color: #0a1f44;
}
@media (max-width: 700px) {
    .vt-suc-sel {
        border-right: 0;
        padding-right: 0;
    }
}
</style>
