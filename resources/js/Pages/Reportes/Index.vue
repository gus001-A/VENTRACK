<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs, { type Dayjs } from 'dayjs';
import {
    BarChartOutlined, FilePdfOutlined, FileExcelOutlined, CalendarOutlined, UserOutlined, FlagOutlined, LikeOutlined,
    EnvironmentOutlined, TrophyOutlined, TeamOutlined, ClearOutlined, CheckCircleFilled, InfoCircleOutlined,
    CheckCircleOutlined, DislikeOutlined, WarningOutlined, PercentageOutlined, RiseOutlined, StopOutlined, AimOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import SucursalSelect from '@/Components/SucursalSelect.vue';
import { limpiar, type Opcion } from '@/utils';
import { useAlturaTabla } from '@/composables/useAlturaTabla';

type Columna = { key: string; label: string; ancho?: number; align?: string };
const props = defineProps<{
    tipo: string;
    tipos: Record<string, { titulo: string; descripcion: string }>;
    filtros: Record<string, any>;
    reporte: {
        titulo: string; descripcion: string; sucursal: string; periodo: string; generado_en: string;
        columnas: Columna[]; filas: Record<string, any>[]; total: number; resumen: { label: string; valor: string | number; color: string }[];
    };
    promotores: Opcion[];
    estatus: Record<string, string>;
}>();

const ICONOS: Record<string, any> = { visitas: EnvironmentOutlined, promotores: TrophyOutlined, prospectos: TeamOutlined };
const COLORES: Record<string, string> = { visitas: '#0461d8', promotores: '#7c4ddb', prospectos: '#f08c00' };

const f = reactive({
    desde: (props.filtros.desde ?? null) as string | null,
    hasta: (props.filtros.hasta ?? null) as string | null,
    promotor: props.filtros.promotor ? Number(props.filtros.promotor) : null,
    estatus: props.filtros.estatus ?? null,
    resultado: props.filtros.resultado ?? null,
});
const deVisitas = computed(() => props.tipo !== 'prospectos');
const consulta = computed(() => limpiar({ ...f, ...(deVisitas.value ? {} : { promotor: null, estatus: null, resultado: null }) }));

function ver(tipo = props.tipo) {
    router.get('/reportes', { tipo, ...consulta.value }, { preserveState: true, preserveScroll: true, replace: true });
}
function cambioDesde() {
    if (f.desde && f.hasta && f.hasta < f.desde) f.hasta = null;
    ver();
}
const sinFuturo = (d: Dayjs) => d.isAfter(dayjs(), 'day');
const antesDeDesde = (d: Dayjs) => !!f.desde && d.isBefore(dayjs(f.desde), 'day');
const hayFiltros = computed(() => Object.keys(consulta.value).length > 0);
function limpiarFiltros() {
    Object.assign(f, { desde: null, hasta: null, promotor: null, estatus: null, resultado: null });
    ver();
}

const enlace = (formato: 'pdf' | 'excel') => `/reportes/${props.tipo}/${formato}?${new URLSearchParams(consulta.value as any).toString()}`;
const altoTabla = useAlturaTabla(560);
const columnas = computed(() => props.reporte.columnas.map((c) => ({
    title: c.label, dataIndex: c.key, key: c.key, align: c.align ?? 'left', ellipsis: true, width: (c.ancho ?? 14) * 8,
})));

const COLOR_VALOR: Record<string, string> = {
    Programada: '#2563eb', 'En proceso': '#d97706', Realizada: '#16a34a', Revisada: '#0891b2', Supervisada: '#9333ea',
    'Con venta': '#1faf5c', 'Sin venta': '#e5484d', Vencida: '#dc2626', 'Sin resultado': '#94a3b8',
    Comprobada: '#16a34a', Pendiente: '#94a3b8', Activo: '#16a34a', Inactivo: '#94a3b8',
};
const CON_CHIP = ['estatus', 'resultado', 'ubicacion', 'estado'];
// Indicadores: cada uno muestra su proporción respecto al total del reporte
const kpis = computed(() => {
    const r = props.reporte.resumen;
    const base = Number(r.find((k) => ['Visitas', 'Prospectos'].includes(k.label))?.valor ?? 0);
    return r.map((k) => {
        const esPct = typeof k.valor === 'string' && k.valor.endsWith('%');
        const num = esPct ? parseFloat(k.valor as string) : Number(k.valor);
        const esBase = ['Visitas', 'Prospectos'].includes(k.label);
        const sinBarra = esBase || k.label === 'Promotores';
        const pct = esPct ? num : sinBarra ? null : base ? Math.round((num * 100) / base) : 0;
        return {
            ...k,
            pct,
            esBase,
            nota: esPct ? (k.label === 'Efectividad' ? 'ventas / realizadas' : 'realizadas / programadas')
                : esBase ? 'total del periodo' : sinBarra ? 'con visitas en el periodo' : `${pct}% del total`,
        };
    });
});
const ICONO_KPI: Record<string, any> = {
    Visitas: EnvironmentOutlined, Realizadas: CheckCircleOutlined, 'Con venta': LikeOutlined, 'Sin venta': DislikeOutlined,
    Vencidas: WarningOutlined, Efectividad: PercentageOutlined, Cumplimiento: RiseOutlined, Promotores: UserOutlined,
    Prospectos: TeamOutlined, Activos: CheckCircleOutlined, 'Con visitas': CalendarOutlined, 'Sin visitar': StopOutlined,
};
</script>

<template>
    <PageHeader title="Reportes" subtitle="Genera reportes de visitas, desempeño y prospectos en PDF o Excel" :icon="BarChartOutlined">
        <template #extra>
            <a-button size="large" class="rp-btn pdf" :href="enlace('pdf')"><FilePdfOutlined /> Descargar PDF</a-button>
            <a-button size="large" class="rp-btn xls" :href="enlace('excel')"><FileExcelOutlined /> Descargar Excel</a-button>
        </template>
    </PageHeader>

    <!-- Tipo de reporte -->
    <div class="rp-tipos">
        <button v-for="(t, k) in tipos" :key="k" type="button" class="rp-tipo" :class="{ on: tipo === k }" :style="{ '--c': COLORES[k] }" @click="ver(k as string)">
            <span class="rp-tipo-ic"><component :is="ICONOS[k]" /></span>
            <span class="min-w-0">
                <b>{{ t.titulo }}</b>
                <small>{{ t.descripcion }}</small>
            </span>
            <CheckCircleFilled v-if="tipo === k" class="rp-tipo-ok" />
        </button>
    </div>

    <!-- Filtros (una sola línea) -->
    <div class="vt-toolbar rp-filtros">
        <SucursalSelect />
        <span class="vt-toolbar-label"><CalendarOutlined /> Periodo</span>
        <a-date-picker v-model:value="f.desde" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Desde" :disabled-date="sinFuturo" class="rp-f-fecha" @change="cambioDesde" />
        <a-date-picker v-model:value="f.hasta" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Hasta" :disabled-date="antesDeDesde" class="rp-f-fecha" @change="ver()" />
        <template v-if="deVisitas">
            <span class="rp-sep"></span>
            <a-select v-if="promotores.length" v-model:value="f.promotor" allow-clear show-search option-filter-prop="label" placeholder="Promotor" class="rp-f-sel"
                :options="promotores.map((p) => ({ value: p.id, label: p.nombre }))" @change="ver()">
                <template #suffixIcon><UserOutlined /></template>
            </a-select>
            <a-select v-model:value="f.estatus" allow-clear placeholder="Estatus" class="rp-f-sel" :options="Object.entries(estatus).map(([value, label]) => ({ value, label }))" @change="ver()">
                <template #suffixIcon><FlagOutlined /></template>
            </a-select>
            <a-select v-model:value="f.resultado" allow-clear placeholder="Resultado" class="rp-f-sel" @change="ver()"
                :options="[{ value: 'positiva', label: 'Con venta' }, { value: 'negativa', label: 'Sin venta' }, { value: 'sin', label: 'Sin resultado' }]">
                <template #suffixIcon><LikeOutlined /></template>
            </a-select>
        </template>
        <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo rp-limpiar" :disabled="!hayFiltros" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
    </div>

    <!-- Vista previa -->
    <div class="vt-card rp-prev">
        <div class="rp-prev-h">
            <div class="min-w-0">
                <h2>{{ reporte.titulo }}</h2>
                <p><b>Sucursal:</b> {{ reporte.sucursal }} · <b>Periodo:</b> {{ reporte.periodo }} · <b>Registros:</b> {{ reporte.total }}</p>
            </div>
            <span class="rp-prev-tag"><InfoCircleOutlined /> Vista previa</span>
        </div>
        <div class="rp-kpis" :style="{ '--n': kpis.length }">
            <div v-for="(k, i) in kpis" :key="k.label" class="rp-kpi" :class="{ base: k.esBase, 'sin-barra': k.pct === null && !k.esBase }" :style="{ '--c': k.color, animationDelay: i * 0.05 + 's' }">
                <div class="rp-kpi-top">
                    <span class="rp-kpi-etq">{{ k.label }}</span>
                    <i class="rp-kpi-ic"><component :is="ICONO_KPI[k.label] || BarChartOutlined" /></i>
                </div>
                <strong class="rp-kpi-val">{{ k.valor }}</strong>
                <div v-if="k.pct !== null" class="rp-kpi-barra"><i :style="{ width: Math.min(100, k.pct) + '%' }"></i></div>
                <small class="rp-kpi-nota">{{ k.nota }}</small>
            </div>
        </div>
        <a-table table-layout="fixed" size="small" class="vt-table" :columns="columnas" :data-source="reporte.filas" :row-key="(_: any, i: number) => i"
            :pagination="false" :scroll="{ y: altoTabla }" :locale="{ emptyText: 'No hay información para los filtros seleccionados.' }">
            <template #bodyCell="{ column, text }">
                <span v-if="CON_CHIP.includes(column.key as string) && COLOR_VALOR[text]" class="vt-tag solido" :style="{ background: COLOR_VALOR[text] }">{{ text }}</span>
            </template>
        </a-table>
        <div class="vt-total-pie">
            <template v-if="reporte.total > reporte.filas.length">Vista previa de {{ reporte.filas.length }} de {{ reporte.total }} registros · el archivo incluye todos</template>
            <template v-else>{{ reporte.total }} registro(s)</template>
        </div>
    </div>
</template>

<style>
.rp-tipos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}
.rp-tipo {
    position: relative;
    display: flex;
    gap: 12px;
    align-items: center;
    text-align: left;
    padding: 14px 16px;
    border: 1.5px solid var(--vt-line);
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.rp-tipo:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(10, 31, 68, 0.08);
}
.rp-tipo.on {
    border-color: var(--c);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 15%, transparent);
}
.rp-tipo b {
    display: block;
    color: #0a1f44;
    font-size: 14.5px;
}
.rp-tipo small {
    display: block;
    color: #6b7a90;
    font-size: 12px;
    line-height: 1.35;
}
.rp-tipo-ic {
    flex: none;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 19px;
    color: #fff;
    background: var(--c);
}
.rp-tipo-ok {
    position: absolute;
    top: 10px;
    right: 10px;
    color: var(--c);
}
.rp-prev {
    padding: 0 !important;
    overflow: hidden;
}
.rp-prev-h {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 18px 0;
}
.rp-prev-h h2 {
    margin: 0;
    font: 800 18px 'Bricolage Grotesque', sans-serif;
    color: #0a1f44;
}
.rp-prev-h p {
    margin: 2px 0 0;
    font-size: 12.5px;
    color: #5d7085;
}
.rp-prev-tag {
    flex: none;
    font-size: 12px;
    font-weight: 600;
    color: #0461d8;
    background: #eef5ff;
    padding: 4px 10px;
    border-radius: 99px;
}
.rp-kpis {
    display: grid;
    grid-template-columns: repeat(var(--n), minmax(0, 1fr));
    gap: 12px;
    padding: 14px 18px 18px;
}
.rp-kpi {
    min-width: 0;
    padding: 14px 16px 12px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--vt-line);
    box-shadow: 0 1px 2px rgba(10, 31, 68, 0.04);
    animation: vt-rise 0.4s both;
    transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}
.rp-kpi:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--c) 35%, var(--vt-line));
    box-shadow: 0 12px 24px color-mix(in srgb, var(--c) 16%, transparent);
}
/* El total se destaca con el color de fondo */
.rp-kpi.base {
    background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 70%, #0a1f44));
    border-color: transparent;
}
.rp-kpi.base .rp-kpi-etq,
.rp-kpi.base .rp-kpi-nota {
    color: rgba(255, 255, 255, 0.85);
}
.rp-kpi.base .rp-kpi-val {
    color: #fff;
}
.rp-kpi.base .rp-kpi-ic {
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
}
.rp-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.rp-kpi-etq {
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #5d7085;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rp-kpi-ic {
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 17px;
    font-style: normal;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 13%, #fff);
}
.rp-kpi-val {
    display: block;
    margin: 4px 0 8px;
    font: 800 28px/1 'Bricolage Grotesque', sans-serif;
    color: #0a1f44;
}
.rp-kpi-barra {
    height: 6px;
    border-radius: 99px;
    background: #edf1f6;
    overflow: hidden;
}
.rp-kpi-barra i {
    display: block;
    height: 100%;
    border-radius: 99px;
    background: var(--c);
    transition: width 0.6s ease;
}
.rp-kpi-nota {
    display: block;
    margin-top: 6px;
    font-size: 11.5px;
    color: #6b7a90;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rp-kpi.base .rp-kpi-nota,
.rp-kpi.sin-barra .rp-kpi-nota {
    margin-top: 12px;
}
/* Filtros en una sola línea */
.rp-filtros {
    flex-wrap: nowrap;
    gap: 10px;
}
.rp-filtros .vt-suc-sel .ant-select {
    width: 180px !important;
}
.rp-f-fecha {
    width: 135px;
    flex: none;
}
.rp-f-sel {
    flex: 1 1 140px;
    min-width: 120px;
}
.rp-limpiar {
    flex: none;
    margin-left: auto;
}
.rp-sep {
    width: 1px;
    height: 26px;
    background: var(--vt-line);
    flex: none;
}
@media (max-width: 1100px) {
    .rp-filtros {
        flex-wrap: wrap;
    }
    .rp-kpis {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>
