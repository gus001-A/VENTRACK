<script setup lang="ts">
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { HistoryOutlined, SearchOutlined, ClearOutlined, FilterOutlined, UserOutlined, CalendarOutlined, ClockCircleOutlined, AppstoreOutlined, ThunderboltOutlined, FileTextOutlined } from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import CambiosDetalle from '@/Components/CambiosDetalle.vue';
import { fechaCorta, limpiar, type Paginado, type Opcion, hora12 } from '@/utils';

const props = defineProps<{
    logs: Paginado<any>;
    filtros: Record<string, any>;
    modulos: Record<string, string>;
    usuarios: Opcion[];
}>();

const f = reactive({
    q: props.filtros.q ?? '',
    modulo: props.filtros.modulo ?? null,
    usuario: props.filtros.usuario ? Number(props.filtros.usuario) : null,
    accion: props.filtros.accion ?? null,
    rango: props.filtros.desde ? [props.filtros.desde, props.filtros.hasta] : (null as any),
});
function buscar(page = 1) {
    router.get('/bitacora', limpiar({ q: f.q, modulo: f.modulo, usuario: f.usuario, accion: f.accion, desde: f.rango?.[0], hasta: f.rango?.[1], page: page > 1 ? page : null }),
        { preserveState: true, preserveScroll: true, replace: true });
}
function reset() {
    Object.assign(f, { q: '', modulo: null, usuario: null, accion: null, rango: null });
    buscar();
}

const ACC: Record<string, { t: string; c: string }> = { INSERT: { t: 'Alta', c: 'green' }, UPDATE: { t: 'Cambio', c: 'blue' }, DELETE: { t: 'Baja', c: 'red' } };
const columnas = [
    { title: 'Usuario', key: 'usuario', width: 190, icon: UserOutlined },
    { title: 'Fecha', key: 'fecha', width: 125, icon: CalendarOutlined },
    { title: 'Hora', key: 'hora', width: 100, icon: ClockCircleOutlined },
    { title: 'Módulo', key: 'modulo', width: 140, icon: AppstoreOutlined },
    { title: 'Acción', key: 'accion', width: 100, icon: ThunderboltOutlined },
    { title: 'Cambio', key: 'descripcion', ellipsis: true, icon: FileTextOutlined },
];

const altoTabla = useAlturaTabla(420);
</script>

<template>
    <PageHeader title="Bitácora de cambios" subtitle="Quién, cuándo (fecha y hora) y qué cambió en cualquier módulo" :icon="HistoryOutlined" />

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" :columns="columnas" :data-source="logs.data" row-key="id" size="middle"
            :pagination="{ current: logs.current_page, pageSize: logs.per_page, total: logs.total, onChange: buscar, showSizeChanger: false, showTotal: (t: number, r: number[]) => `Mostrando ${r[0]} a ${r[1]} de ${t} resultados` }"
            :expandable="{ rowExpandable: (r: any) => !!r.cambios, expandRowByClick: true }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'usuario'"><UserOutlined style="color: var(--vt-blue)" /> <b>{{ record.usuario }}</b><div class="vt-small vt-muted">{{ record.perfil }}</div></template>
                <template v-else-if="column.key === 'fecha'">{{ fechaCorta(record.fecha) }}</template>
                <template v-else-if="column.key === 'hora'">{{ hora12(record.hora, true) }}</template>
                <template v-else-if="column.key === 'modulo'"><a-tag color="blue">{{ record.modulo }}</a-tag></template>
                <template v-else-if="column.key === 'accion'"><a-tag :color="ACC[record.accion]?.c">{{ ACC[record.accion]?.t }}</a-tag></template>
                <template v-else-if="column.key === 'descripcion'">{{ record.descripcion }}</template>
            </template>
            <template #expandedRowRender="{ record }">
                <div class="vt-small vt-muted mb-1">Registro #{{ record.registro_id }} · tabla {{ record.tabla }} · IP {{ record.ip || '—' }}</div>
                <CambiosDetalle :cambios="record.accion === 'UPDATE' ? record.cambios : (record.cambios?.nuevo || record.cambios?.anterior || record.cambios)" />
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                <a-table-summary-row class="vt-fsum">
                    <a-table-summary-cell :index="0"></a-table-summary-cell>
                    <a-table-summary-cell :index="1">
                        <div class="vt-flabel"><UserOutlined /> Usuario</div>
                        <a-select v-model:value="f.usuario" allow-clear show-search option-filter-prop="label" placeholder="Todos" @change="buscar()" :options="usuarios.map((u) => ({ value: u.id, label: u.nombre }))" />
                    </a-table-summary-cell>
                    <a-table-summary-cell :index="2" :col-span="2">
                        <div class="vt-flabel"><CalendarOutlined /> Fecha</div>
                        <a-range-picker v-model:value="f.rango" value-format="YYYY-MM-DD" format="DD/MM/YY" :placeholder="['Desde', 'Hasta']" @change="buscar()" />
                    </a-table-summary-cell>
                    <a-table-summary-cell :index="4">
                        <div class="vt-flabel"><AppstoreOutlined /> Módulo</div>
                        <a-select v-model:value="f.modulo" allow-clear placeholder="Todos" @change="buscar()" :options="Object.entries(modulos).map(([v, l]) => ({ value: v, label: l }))" />
                    </a-table-summary-cell>
                    <a-table-summary-cell :index="5">
                        <div class="vt-flabel"><ThunderboltOutlined /> Acción</div>
                        <a-select v-model:value="f.accion" allow-clear placeholder="Todas" @change="buscar()" :options="Object.entries(ACC).map(([v, a]) => ({ value: v, label: a.t }))" />
                    </a-table-summary-cell>
                    <a-table-summary-cell :index="6">
                        <div class="vt-flabel"><FileTextOutlined /> Cambio</div>
                        <div class="flex gap-2">
                            <a-input v-model:value="f.q" placeholder="Buscar…" allow-clear @press-enter="buscar()" @change="(e: any) => !e.target.value && buscar()"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                            <a-button class="flex-none" @click="reset"><ClearOutlined /> Limpiar</a-button>
                        </div>
                    </a-table-summary-cell>
                </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
    </div>
</template>
