<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs, { type Dayjs } from 'dayjs';
import {
    EnvironmentOutlined, PlusCircleOutlined, SearchOutlined, AimOutlined, ClearOutlined, IdcardOutlined, ShopOutlined,
    UserOutlined, CalendarOutlined, FlagOutlined, LikeOutlined, HistoryOutlined, EyeOutlined, UnorderedListOutlined,
    PlayCircleOutlined, CheckCircleOutlined, SafetyCertificateOutlined, WarningOutlined, ThunderboltOutlined, SettingOutlined,
    FilePdfOutlined, FileExcelOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import EstatusTag from '@/Components/EstatusTag.vue';
import ResultadoTag from '@/Components/ResultadoTag.vue';
import VisitaModal from '@/Components/VisitaModal.vue';
import ProgramarVisitaModal from '@/Components/ProgramarVisitaModal.vue';
import KpiBar from '@/Components/KpiBar.vue';
import SucursalSelect from '@/Components/SucursalSelect.vue';
import Registro from '@/Components/Registro.vue';
import { ESTATUS, ESTATUS_INFO, ESTATUS_DOT, fechaCorta, hora12, limpiar, type Paginado, type VisitaFila, type Opcion, type Estatus } from '@/utils';
import { useShared } from '@/composables/useShared';
import { useAlturaTabla } from '@/composables/useAlturaTabla';

const props = defineProps<{
    visitas: Paginado<VisitaFila & { traza?: any }>;
    conteos: Partial<Record<Estatus, number>>;
    vencidas: number;
    filtros: Record<string, any>;
    promotores: Opcion[];
    opciones: { prospectos: Opcion[]; promotores: Opcion[] };
    abrir: number | null;
}>();

const { user, can } = useShared();
const abierta = ref<number | null>(props.abrir);
const programar = ref(false);
const altoTabla = useAlturaTabla(500);

const f = reactive({
    estatus: props.filtros.estatus ?? null,
    q: props.filtros.q ?? '',
    promotor: props.filtros.promotor ? Number(props.filtros.promotor) : null,
    resultado: props.filtros.resultado ?? null,
    desde: (props.filtros.desde ?? null) as string | null,
    hasta: (props.filtros.hasta ?? null) as string | null,
    vencidas: props.filtros.vencidas ?? null,
});

function buscar(page = 1) {
    router.get('/visitas', limpiar({
        estatus: f.estatus, q: f.q, promotor: f.promotor, resultado: f.resultado,
        desde: f.desde, hasta: f.hasta, vencidas: f.vencidas, page: page > 1 ? page : null,
    }), { preserveState: true, preserveScroll: true, replace: true });
}
function limpiarFiltros() {
    Object.assign(f, { estatus: null, q: '', promotor: null, resultado: null, desde: null, hasta: null, vencidas: null });
    buscar();
}
const hayFiltros = computed(() => !!(f.q || f.promotor || f.resultado || f.desde || f.hasta || f.vencidas || f.estatus));

// "Desde" no puede ser posterior a hoy; "Hasta" no puede ser anterior a "Desde"
const sinFuturo = (d: Dayjs) => d.isAfter(dayjs(), 'day');
const antesDeDesde = (d: Dayjs) => !!f.desde && d.isBefore(dayjs(f.desde), 'day');
function cambioDesde() {
    if (f.desde && f.hasta && f.hasta < f.desde) f.hasta = null;
    buscar();
}
const hayFecha = computed(() => !!(f.desde || f.hasta));

// Rangos rápidos de fecha (filtro superior)
const RANGOS = [
    { k: 'hoy', t: 'Hoy', r: () => [dayjs(), dayjs()] },
    { k: 'semana', t: 'Esta semana', r: () => [dayjs().startOf('week'), dayjs().endOf('week')] },
    { k: 'mes', t: 'Este mes', r: () => [dayjs().startOf('month'), dayjs().endOf('month')] },
];
function rango(k: string) {
    const r = RANGOS.find((x) => x.k === k)!.r();
    [f.desde, f.hasta] = r.map((d) => d.format('YYYY-MM-DD'));
    buscar();
}
const rangoActivo = computed(() => {
    if (!f.desde || !f.hasta) return null;
    return RANGOS.find((x) => {
        const r = x.r().map((d) => d.format('YYYY-MM-DD'));
        return r[0] === f.desde && r[1] === f.hasta;
    })?.k ?? null;
});

// Indicadores por estatus (clic = filtrar)
const ICONOS: Record<string, any> = {
    programada: CalendarOutlined, proceso: PlayCircleOutlined, realizada: CheckCircleOutlined, revisada: EyeOutlined, supervisada: SafetyCertificateOutlined,
};
const total = computed(() => Object.values(props.conteos).reduce((a, b) => a + (b || 0), 0));
const kpis = computed(() => [
    { key: 'todas', icon: UnorderedListOutlined, valor: total.value, label: 'Todas', color: '#0a1f44', activo: !f.estatus && !f.vencidas },
    ...ESTATUS.map((s) => ({ key: s, icon: ICONOS[s], valor: props.conteos[s] || 0, label: ESTATUS_INFO[s].label, color: ESTATUS_DOT[s], activo: f.estatus === s })),
    { key: 'vencidas', icon: WarningOutlined, valor: props.vencidas, label: 'Vencidas', color: '#e5484d', activo: !!f.vencidas },
]);
function elegirKpi(k: string) {
    f.vencidas = k === 'vencidas' ? 1 : null;
    f.estatus = ESTATUS.includes(k as Estatus) ? k : null;
    buscar();
}

const opcionesEstatus = ESTATUS.map((s) => ({ value: s, label: ESTATUS_INFO[s].label }));
const columnas = computed(() => [
    { title: 'Prospecto', key: 'prospecto', icon: IdcardOutlined, ellipsis: true },
    ...(user.value.perfil !== 'Promotor' ? [{ title: 'Promotor', key: 'promotor', icon: UserOutlined, width: 170, ellipsis: true }] : []),
    { title: 'Fecha y hora', key: 'fecha', icon: CalendarOutlined, width: 200 },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 145 },
    { title: 'Resultado', key: 'resultado', icon: LikeOutlined, width: 125 },
    ...(user.value.perfil !== 'Prospecto' ? [{ title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 200 }] : []),
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 100, align: 'center' },
]);

const subtitulo = computed(() =>
    user.value.perfil === 'Promotor' ? 'Tus visitas asignadas'
        : user.value.perfil === 'Prospecto' ? 'Visitas a tu establecimiento'
        : 'Visitas de la sucursal seleccionada',
);
// Exportar la lista con los filtros aplicados
const exportar = (formato: 'pdf' | 'excel') => `/reportes/visitas/${formato}?${new URLSearchParams(limpiar({
    estatus: f.estatus, q: f.q, promotor: f.promotor, resultado: f.resultado, desde: f.desde, hasta: f.hasta, vencidas: f.vencidas,
}) as any).toString()}`;
const TOTAL = (t: number, r: number[]) => `Mostrando ${r[0]} a ${r[1]} de ${t} resultados`;
</script>

<template>
    <PageHeader title="Lista de trabajo" :subtitle="subtitulo" :icon="EnvironmentOutlined">
        <template #extra>
            <template v-if="user.perfil !== 'Prospecto'">
                <a-tooltip title="Exportar la lista a PDF"><a-button size="large" class="rp-btn pdf" :href="exportar('pdf')"><FilePdfOutlined /> PDF</a-button></a-tooltip>
                <a-tooltip title="Exportar la lista a Excel"><a-button size="large" class="rp-btn xls" :href="exportar('excel')"><FileExcelOutlined /> Excel</a-button></a-tooltip>
            </template>
            <a-button v-if="can.programar" type="primary" size="large" @click="programar = true"><PlusCircleOutlined /> Programar visita</a-button>
        </template>
    </PageHeader>

    <!-- Filtro de fecha (arriba de la tabla) -->
    <div class="vt-toolbar">
        <SucursalSelect />
        <span class="vt-toolbar-label"><CalendarOutlined /> Fecha</span>
        <a-date-picker v-model:value="f.desde" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Desde" :disabled-date="sinFuturo" style="width: 150px" @change="cambioDesde" />
        <a-date-picker v-model:value="f.hasta" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Hasta" :disabled-date="antesDeDesde" style="width: 150px" @change="buscar()" />
        <div class="vt-rangos">
            <button v-for="r in RANGOS" :key="r.k" type="button" :class="{ on: rangoActivo === r.k }" @click="rango(r.k)"><ThunderboltOutlined /> {{ r.t }}</button>
            <button v-if="hayFecha" type="button" class="quitar" @click="(f.desde = null), (f.hasta = null), buscar()"><ClearOutlined /> Quitar fecha</button>
        </div>
    </div>

    <KpiBar :items="kpis" clicables @elegir="elegirKpi" />

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" class="vt-table" :columns="columnas" :data-source="visitas.data" row-key="id"
            :pagination="{ current: visitas.current_page, pageSize: visitas.per_page, total: visitas.total, showSizeChanger: false, onChange: buscar, showTotal: TOTAL }"
            :custom-row="(r: VisitaFila) => ({ onClick: () => (abierta = r.id) })"
            :locale="{ emptyText: 'No hay visitas con estos filtros.' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'prospecto'">
                    <b class="block truncate">{{ record.prospecto.nombre }}</b>
                    <div v-if="record.objetivo" class="vt-small vt-muted truncate">{{ record.objetivo }}</div>
                </template>
                <template v-else-if="column.key === 'promotor'"><span class="vt-nowrap"><UserOutlined style="color: #1faf5c" /> {{ record.promotor.nombre }}</span></template>
                <template v-else-if="column.key === 'fecha'">
                    <span class="vt-nowrap">
                        <CalendarOutlined style="color: #7c4ddb" /> {{ fechaCorta(record.fecha) }}<template v-if="record.hora"> · {{ hora12(record.hora) }}</template>
                        <a-tooltip v-if="record.geo" title="Ubicación capturada"><AimOutlined class="ml-1" style="color: #1faf5c" /></a-tooltip>
                    </span>
                </template>
                <template v-else-if="column.key === 'estatus'"><EstatusTag :estatus="record.estatus" /></template>
                <template v-else-if="column.key === 'resultado'"><ResultadoTag :resultado="record.resultado" :vencida="record.vencida" /></template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <a-tooltip title="Ver visita"><a-button class="vt-ab azul" @click.stop="abierta = record.id"><EyeOutlined /></a-button></a-tooltip>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0">
                            <a-input v-model:value="f.q" placeholder="Filtrar" allow-clear @press-enter="buscar()" @change="(e: any) => !e.target.value && buscar()"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                        </a-table-summary-cell>
                        <a-table-summary-cell v-if="user.perfil !== 'Promotor'" :index="1">
                            <a-select v-model:value="f.promotor" allow-clear show-search option-filter-prop="label" placeholder="Todos" :disabled="!promotores.length" @change="buscar()" :options="promotores.map((s) => ({ value: s.id, label: s.nombre }))" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="2"><span class="vt-small vt-muted"><CalendarOutlined /> Usa el filtro de arriba</span></a-table-summary-cell>
                        <a-table-summary-cell :index="3">
                            <a-select v-model:value="f.estatus" allow-clear placeholder="Todos" @change="(f.vencidas = null), buscar()" :options="opcionesEstatus" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="4">
                            <a-select v-model:value="f.resultado" allow-clear placeholder="Todos" @change="buscar()" :options="[{ value: 'positiva', label: 'Positiva' }, { value: 'negativa', label: 'Negativa' }, { value: 'sin', label: 'Sin resultado' }]" />
                        </a-table-summary-cell>
                        <a-table-summary-cell v-if="user.perfil !== 'Prospecto'" :index="5"></a-table-summary-cell>
                        <a-table-summary-cell :index="6" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
    </div>

    <VisitaModal :visita-id="abierta" :opciones="opciones" @close="abierta = null" />
    <ProgramarVisitaModal v-if="can.programar" v-model:open="programar" :opciones="opciones" />
</template>
