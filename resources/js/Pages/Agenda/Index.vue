<script setup lang="ts">
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { message } from 'ant-design-vue';
import {
    CalendarOutlined, LeftOutlined, RightOutlined, PlusCircleOutlined, CheckOutlined, CloseOutlined, SearchOutlined, ClearOutlined,
    IdcardOutlined, UserOutlined, ShopOutlined, FlagOutlined, WarningOutlined, EyeOutlined, SettingOutlined, PlusOutlined,
    AimOutlined, ClockCircleOutlined, DragOutlined, TableOutlined, ScheduleOutlined, UnorderedListOutlined, PlayCircleOutlined,
    CheckCircleOutlined, SafetyCertificateOutlined, InfoCircleOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import EstatusTag from '@/Components/EstatusTag.vue';
import VisitaModal from '@/Components/VisitaModal.vue';
import ProgramarVisitaModal from '@/Components/ProgramarVisitaModal.vue';
import { ESTATUS, ESTATUS_INFO, ESTATUS_DOT, diaMes, fechaCorta, type VisitaFila, type Opcion, hora12 } from '@/utils';
import { useShared } from '@/composables/useShared';

const props = defineProps<{
    vista: 'mes' | 'semana' | 'lista';
    fecha: string;
    desde: string | null;
    visitas: VisitaFila[];
    opciones: { prospectos: Opcion[]; promotores: Opcion[] };
}>();

const { can, hoy } = useShared();
const abierta = ref<number | null>(null);
const programar = ref(false);
const seleccion = ref(props.fecha);
const arrastrando = ref<number | null>(null);
const sobre = ref<string | null>(null);

const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const vencida = (v: VisitaFila) => v.estatus === 'programada' && v.fecha < hoy.value;

function ir(vista: string, fecha: string) {
    router.get('/agenda', { vista, fecha }, { preserveState: true, preserveScroll: true, replace: true });
}
function mover(k: number) {
    const unidad = props.vista === 'mes' ? 'month' : 'week';
    ir(props.vista, dayjs(props.fecha).add(k, unidad).format('YYYY-MM-DD'));
}

const dias = computed(() => {
    if (!props.desde) return [];
    const n = props.vista === 'mes' ? 42 : 7;
    return Array.from({ length: n }, (_, i) => dayjs(props.desde).add(i, 'day').format('YYYY-MM-DD'));
});
const VISTAS = [
    { value: 'mes', label: 'Mes', icon: TableOutlined },
    { value: 'semana', label: 'Semana', icon: ScheduleOutlined },
    { value: 'lista', label: 'Pendientes', icon: UnorderedListOutlined },
];
const ICONOS: Record<string, any> = {
    programada: CalendarOutlined, proceso: PlayCircleOutlined, realizada: CheckCircleOutlined, revisada: EyeOutlined, supervisada: SafetyCertificateOutlined,
};

// Filtro por promotor del calendario
const filtroPromotor = ref<string | null>(null);
const promotoresCal = computed(() => [...new Set(props.visitas.map((v) => v.promotor.nombre))].sort());
const visitasCal = computed(() => props.visitas.filter((v) => !filtroPromotor.value || v.promotor.nombre === filtroPromotor.value));

const porDia = computed(() => {
    const m: Record<string, VisitaFila[]> = {};
    for (const v of visitasCal.value) (m[v.fecha] ||= []).push(v);
    return m;
});

// Resumen del periodo visible (en la vista mensual, solo el mes actual)
const resumen = computed(() => {
    const l = visitasCal.value.filter((v) => props.vista !== 'mes' || dayjs(v.fecha).month() === dayjs(props.fecha).month());
    return [
        { t: 'Visitas del periodo', n: l.length, c: '#0461d8', i: CalendarOutlined },
        { t: 'Por realizar', n: l.filter((v) => ['programada', 'proceso'].includes(v.estatus)).length, c: '#f08c00', i: ClockCircleOutlined },
        { t: 'Realizadas', n: l.filter((v) => !['programada', 'proceso'].includes(v.estatus)).length, c: '#1faf5c', i: CheckCircleOutlined },
        { t: 'Vencidas', n: l.filter(vencida).length, c: '#e5484d', i: WarningOutlined },
    ];
});
// Colores sólidos de los eventos del calendario
const EV_COLOR: Record<string, string> = {
    programada: '#2563eb', proceso: '#d97706', realizada: '#16a34a', revisada: '#0891b2', supervisada: '#9333ea',
};
const EV_VENCIDA = '#dc2626';
const colorEv = (v: VisitaFila) => (vencida(v) ? EV_VENCIDA : EV_COLOR[v.estatus]);
const horaCorta = (h: string | null) => (h ? hora12(h).replace(':00', '').replace(' a. m.', ' am').replace(' p. m.', ' pm') : '');

// Conteo por estatus en el periodo visible (para la simbología)
const conteoEstatus = computed(() => {
    const c: Record<string, number> = {};
    for (const v of visitasCal.value) {
        if (props.vista === 'mes' && dayjs(v.fecha).month() !== dayjs(props.fecha).month()) continue;
        c[v.estatus] = (c[v.estatus] || 0) + 1;
    }
    return c;
});
function programarEn(d: string) {
    seleccion.value = d < hoy.value ? hoy.value : d;
    programar.value = true;
}
const mesActual = computed(() => dayjs(props.fecha).month());
const titulo = computed(() =>
    props.vista === 'mes'
        ? dayjs(props.fecha).format('MMMM YYYY')
        : `${diaMes(props.desde!)} – ${diaMes(dayjs(props.desde).add(6, 'day').format('YYYY-MM-DD'))}`,
);
const delDia = computed(() => porDia.value[seleccion.value] || []);

// Reprogramar arrastrando (solo visitas programadas y perfiles que programan)
const arrastrable = (v: VisitaFila) => can.value.programar && v.estatus === 'programada';
function soltar(fecha: string) {
    const id = arrastrando.value;
    sobre.value = null;
    arrastrando.value = null;
    const v = props.visitas.find((x) => x.id === id);
    if (!v || v.fecha === fecha) return;
    router.put(`/visitas/${v.id}`, { fecha, hora: v.hora, objetivo: v.objetivo }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            seleccion.value = fecha;
            message.success(`Visita reprogramada al ${diaMes(fecha)}`);
        },
    });
}

const altoTabla = useAlturaTabla(400);

// Filtros de la vista de lista (alineados a cada columna)
const fl = reactive({ q: '', sucursal: null as string | null, promotor: null as string | null, estatus: null as string | null, alerta: null as string | null });
const sucursalesLista = computed(() => [...new Set(props.visitas.map((v) => v.sucursal.nombre))].sort());
const promotoresLista = computed(() => [...new Set(props.visitas.map((v) => v.promotor.nombre))].sort());
const listaFiltrada = computed(() => props.visitas.filter((v) =>
    (!fl.q || v.prospecto.nombre.toLowerCase().includes(fl.q.toLowerCase()))
    && (!fl.sucursal || v.sucursal.nombre === fl.sucursal)
    && (!fl.promotor || v.promotor.nombre === fl.promotor)
    && (!fl.estatus || v.estatus === fl.estatus)
    && (!fl.alerta || (fl.alerta === 'vencidas' ? vencida(v) : !vencida(v)))));
const hayFiltrosLista = computed(() => !!(fl.q || fl.sucursal || fl.promotor || fl.estatus || fl.alerta));
const columnasLista = [
    { title: 'Fecha y hora', key: 'fecha', icon: CalendarOutlined, width: 200 },
    { title: 'Prospecto', key: 'prospecto', icon: IdcardOutlined, ellipsis: true },
    { title: 'Sucursal', key: 'sucursal', icon: ShopOutlined, width: 150 },
    { title: 'Promotor', key: 'promotor', icon: UserOutlined, width: 180, ellipsis: true },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 145 },
    { title: 'Alerta', key: 'alerta', icon: WarningOutlined, width: 130 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 100, align: 'center' },
];
const opcionesEstatus = ESTATUS.filter((s) => ['programada', 'proceso'].includes(s)).map((s) => ({ value: s, label: ESTATUS_INFO[s].label }));
</script>

<template>
    <PageHeader title="Agenda" subtitle="Planeación de visitas por día, semana y mes, con alertas de incumplimiento" :icon="CalendarOutlined">
        <template #extra>
            <a-button v-if="can.programar" type="primary" size="large" @click="programarEn(seleccion)"><PlusCircleOutlined /> Programar visita</a-button>
        </template>
    </PageHeader>

    <!-- Barra de herramientas -->
    <div class="ag-bar">
        <div class="ag-vistas">
            <button v-for="o in VISTAS" :key="o.value" :class="{ on: vista === o.value }" @click="ir(o.value, fecha)"><component :is="o.icon" /> {{ o.label }}</button>
        </div>
        <div v-if="vista !== 'lista'" class="ag-nav">
            <button class="ag-flecha" aria-label="Anterior" @click="mover(-1)"><LeftOutlined /></button>
            <Transition name="ag-titulo" mode="out-in"><b :key="titulo" class="ag-titulo">{{ titulo }}</b></Transition>
            <button class="ag-flecha" aria-label="Siguiente" @click="mover(1)"><RightOutlined /></button>
            <a-button @click="(seleccion = hoy), ir(vista, hoy)"><AimOutlined /> Hoy</a-button>
        </div>
        <a-select v-if="vista !== 'lista' && promotoresCal.length > 1" v-model:value="filtroPromotor" allow-clear placeholder="Todos los promotores" style="width: 230px"
            :options="promotoresCal.map((p) => ({ value: p, label: p }))">
            <template #suffixIcon><UserOutlined /></template>
        </a-select>
    </div>

    <!-- Calendario + simbología -->
    <div v-if="vista !== 'lista'" class="ag-layout">
        <div class="ag-main">
            <div class="ag-resumen">
                <div v-for="k in resumen" :key="k.t" class="ag-res" :style="{ '--c': k.c }">
                    <span class="ag-res-ic"><component :is="k.i" /></span>
                    <span><b>{{ k.n }}</b><small>{{ k.t }}</small></span>
                </div>
            </div>

            <Transition name="ag-cal" mode="out-in">
                <div :key="desde + vista" class="ag-cal" :class="vista">
                    <div v-for="(d, i) in DIAS" :key="d" class="ag-wd" :class="{ finde: i > 4 }">{{ d }}</div>
                    <div
                        v-for="d in dias"
                        :key="d"
                        class="ag-cd"
                        :class="{
                            out: vista === 'mes' && dayjs(d).month() !== mesActual,
                            today: d === hoy, sel: d === seleccion, drop: sobre === d, pasado: d < hoy,
                            finde: [0, 6].includes(dayjs(d).day()),
                        }"
                        @click="seleccion = d"
                        @dblclick="can.programar && programarEn(d)"
                        @dragover.prevent="sobre = d"
                        @dragleave="sobre === d && (sobre = null)"
                        @drop.prevent="soltar(d)"
                    >
                        <div class="ag-cd-h">
                            <span class="ag-dn">{{ dayjs(d).date() }}</span>
                            <span v-if="vista === 'semana'" class="ag-cd-dia">{{ dayjs(d).format('dddd') }}</span>
                            <span v-if="(porDia[d] || []).length" class="ag-cd-n">{{ porDia[d].length }}</span>
                            <button v-if="can.programar && d >= hoy" class="ag-add" title="Programar en este día" @click.stop="programarEn(d)"><PlusOutlined /></button>
                        </div>
                        <template v-for="v in (porDia[d] || []).slice(0, vista === 'semana' ? 20 : 3)" :key="v.id">
                            <!-- Semana: tarjeta completa -->
                            <div v-if="vista === 'semana'" class="ag-ev-card" :class="{ late: vencida(v) }" :style="{ '--s': colorEv(v) }"
                                :draggable="arrastrable(v)" @dragstart="arrastrando = v.id" @click.stop="abierta = v.id">
                                <div class="ag-ev-card-h">
                                    <span class="ag-ev-hora"><ClockCircleOutlined /> {{ v.hora ? hora12(v.hora) : 'Sin hora' }}</span>
                                    <a-tooltip :title="vencida(v) ? 'Vencida' : ESTATUS_INFO[v.estatus].label"><span class="ag-ev-st"><WarningOutlined v-if="vencida(v)" /><component v-else :is="ICONOS[v.estatus]" /></span></a-tooltip>
                                </div>
                                <b class="ag-ev-nombre">{{ v.prospecto.nombre }}</b>
                                <span class="ag-ev-meta"><UserOutlined /> {{ v.promotor.nombre }}</span>
                                <span class="ag-ev-meta"><ShopOutlined /> {{ v.sucursal.nombre }}</span>
                                <div class="ag-ev-pie">
                                    <span class="ag-ev-estatus">{{ ESTATUS_INFO[v.estatus].label }}</span>
                                    <span v-if="v.resultado === 'positiva'" class="ag-ev-res pos"><CheckOutlined /> Venta</span>
                                    <span v-else-if="v.resultado === 'negativa'" class="ag-ev-res neg"><CloseOutlined /> Sin venta</span>
                                    <span v-else-if="vencida(v)" class="ag-ev-res neg"><WarningOutlined /> Vencida</span>
                                </div>
                            </div>
                            <!-- Mes: evento compacto -->
                            <a-tooltip v-else placement="top" :mouse-enter-delay="0.3">
                                <template #title>
                                    <b>{{ v.prospecto.nombre }}</b><br />
                                    {{ v.hora ? hora12(v.hora) : 'Sin hora' }} · {{ v.promotor.nombre }}<br />
                                    {{ ESTATUS_INFO[v.estatus].label }}<template v-if="vencida(v)"> · Vencida</template><template v-if="v.resultado"> · {{ v.resultado === 'positiva' ? 'Con venta' : 'Sin venta' }}</template>
                                </template>
                                <div class="ag-ev" :class="{ late: vencida(v) }" :style="{ '--s': colorEv(v) }"
                                    :draggable="arrastrable(v)" @dragstart="arrastrando = v.id" @click.stop="abierta = v.id">
                                    <span class="ag-ev-ic"><WarningOutlined v-if="vencida(v)" /><component v-else :is="ICONOS[v.estatus]" /></span>
                                    <span v-if="v.hora" class="ag-ev-t">{{ horaCorta(v.hora) }}</span>
                                    <span class="ag-ev-n">{{ v.prospecto.nombre }}</span>
                                    <span v-if="v.resultado" class="ag-ev-res-b" :title="v.resultado === 'positiva' ? 'Con venta' : 'Sin venta'">
                                        <CheckOutlined v-if="v.resultado === 'positiva'" /><CloseOutlined v-else />
                                    </span>
                                </div>
                            </a-tooltip>
                        </template>
                        <a-popover v-if="vista === 'mes' && (porDia[d] || []).length > 3" trigger="click" placement="right" overlay-class-name="ag-mas-pop">
                            <template #title><span class="capitalize">{{ dayjs(d).format('dddd D [de] MMMM') }}</span> · {{ porDia[d].length }} visitas</template>
                            <template #content>
                                <div class="ag-mas-lista">
                                    <div v-for="v in porDia[d]" :key="v.id" class="ag-ev" :class="{ late: vencida(v) }" :style="{ '--s': ESTATUS_DOT[v.estatus] }" @click="abierta = v.id">
                                        <span class="ag-ev-ic"><WarningOutlined v-if="vencida(v)" /><component v-else :is="ICONOS[v.estatus]" /></span>
                                        <span v-if="v.hora" class="ag-ev-t">{{ horaCorta(v.hora) }}</span>
                                        <span class="ag-ev-n">{{ v.prospecto.nombre }}</span>
                                    </div>
                                </div>
                            </template>
                            <button class="ag-more" @click.stop>+{{ porDia[d].length - 3 }} más</button>
                        </a-popover>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Simbología -->
        <aside class="ag-simb">
            <div class="ag-simb-t"><InfoCircleOutlined /> Simbología</div>

            <div class="ag-simb-sec">Estatus de la visita</div>
            <div v-for="st in ESTATUS" :key="st" class="ag-simb-r" :style="{ '--s': EV_COLOR[st] }">
                <span class="ag-ev-ic"><component :is="ICONOS[st]" /></span>
                <span>{{ ESTATUS_INFO[st].label }}</span>
                <b>{{ conteoEstatus[st] || 0 }}</b>
            </div>

            <div class="ag-simb-sec">Indicadores</div>
            <div class="ag-simb-r" :style="{ '--s': EV_VENCIDA }"><span class="ag-ev-ic"><WarningOutlined /></span><span>Vencida (sin realizar)</span></div>
            <div class="ag-simb-r"><span class="ag-simb-res"><CheckOutlined /></span><span>Cerrada con venta</span></div>
            <div class="ag-simb-r"><span class="ag-simb-res"><CloseOutlined /></span><span>Cerrada sin venta</span></div>
            <div class="ag-simb-r"><span class="ag-simb-hoy">{{ dayjs(hoy).date() }}</span><span>Día de hoy</span></div>
            <div class="ag-simb-r"><span class="ag-simb-finde"></span><span>Fin de semana</span></div>

            <template v-if="can.programar">
                <div class="ag-simb-sec">Acciones</div>
                <div class="ag-simb-tip"><DragOutlined /><span>Arrastra una visita programada a otro día para reprogramarla.</span></div>
                <div class="ag-simb-tip"><PlusOutlined /><span>Usa el botón <b>+</b> de un día o haz doble clic para programar.</span></div>
            </template>
            <div class="ag-simb-tip"><EyeOutlined /><span>Haz clic en una visita para ver su detalle.</span></div>
        </aside>
    </div>

    <!-- Lista de pendientes -->
    <div v-else class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" class="vt-table" :data-source="listaFiltrada" row-key="id" :columns="columnasLista"
            :pagination="{ pageSize: 25, hideOnSinglePage: true }" :custom-row="(r: VisitaFila) => ({ onClick: () => (abierta = r.id) })"
            :locale="{ emptyText: 'No hay visitas pendientes con estos filtros.' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'fecha'">
                    <span class="vt-nowrap"><CalendarOutlined style="color: #7c4ddb" /> {{ fechaCorta(record.fecha) }}<template v-if="record.hora"> · {{ hora12(record.hora) }}</template></span>
                </template>
                <template v-else-if="column.key === 'prospecto'"><b class="truncate block">{{ record.prospecto.nombre }}</b><div v-if="record.objetivo" class="vt-small vt-muted truncate">{{ record.objetivo }}</div></template>
                <template v-else-if="column.key === 'sucursal'"><span class="vt-chip-teal"><ShopOutlined /> {{ record.sucursal.nombre }}</span></template>
                <template v-else-if="column.key === 'promotor'"><span class="vt-nowrap"><UserOutlined style="color: #1faf5c" /> {{ record.promotor.nombre }}</span></template>
                <template v-else-if="column.key === 'estatus'"><EstatusTag :estatus="record.estatus" /></template>
                <template v-else-if="column.key === 'alerta'">
                    <span v-if="vencida(record)" class="vt-tag solido" style="background: #e5484d"><WarningOutlined /> Vencida</span>
                    <span v-else class="vt-tag solido" style="background: #1faf5c"><CheckOutlined /> En tiempo</span>
                </template>
                <template v-else-if="column.key === 'acciones'">
                    <a-tooltip title="Ver visita"><a-button class="vt-ab azul" @click.stop="abierta = record.id"><EyeOutlined /></a-button></a-tooltip>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0"></a-table-summary-cell>
                        <a-table-summary-cell :index="1"><a-input v-model:value="fl.q" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell :index="2"><a-select v-model:value="fl.sucursal" allow-clear placeholder="Todas" :options="sucursalesLista.map((x) => ({ value: x, label: x }))" /></a-table-summary-cell>
                        <a-table-summary-cell :index="3"><a-select v-model:value="fl.promotor" allow-clear show-search placeholder="Todos" :options="promotoresLista.map((x) => ({ value: x, label: x }))" /></a-table-summary-cell>
                        <a-table-summary-cell :index="4"><a-select v-model:value="fl.estatus" allow-clear placeholder="Todos" :options="opcionesEstatus" /></a-table-summary-cell>
                        <a-table-summary-cell :index="5"><a-select v-model:value="fl.alerta" allow-clear placeholder="Todas" :options="[{ value: 'vencidas', label: 'Vencidas' }, { value: 'tiempo', label: 'En tiempo' }]" /></a-table-summary-cell>
                        <a-table-summary-cell :index="6" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltrosLista" @click="Object.assign(fl, { q: '', sucursal: null, promotor: null, estatus: null, alerta: null })"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
        <div class="vt-total-pie">Mostrando {{ listaFiltrada.length }} de {{ visitas.length }} visitas pendientes</div>
    </div>

    <VisitaModal :visita-id="abierta" :opciones="opciones" @close="abierta = null" />
    <ProgramarVisitaModal v-if="can.programar" v-model:open="programar" :opciones="opciones" :fecha="seleccion" />
</template>

<style>
.vt-cal-title {
    min-width: 180px;
    text-align: center;
    font: 700 18px 'Bricolage Grotesque', sans-serif;
    text-transform: capitalize;
}
.vt-calw { overflow-x: auto; padding: 2px; }
.vt-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; min-width: 720px; animation: vt-rise 0.3s; }
.vt-wd { text-align: center; font-weight: 600; color: var(--vt-muted); font-size: 13px; padding: 4px; }
.vt-cd {
    min-height: 110px;
    border: 1px solid var(--vt-line);
    border-radius: 12px;
    padding: 6px;
    background: #fff;
    cursor: pointer;
    overflow: hidden;
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
}
.vt-cd:hover { border-color: var(--vt-blue); transform: translateY(-2px); }
.vt-cd.big { min-height: 300px; }
.vt-cd.out { background: var(--vt-bg); opacity: 0.55; }
.vt-cd.sel { border-color: var(--vt-blue); box-shadow: 0 0 0 2px var(--vt-blue-soft); }
.vt-cd.drop { background: var(--vt-blue-soft); border-style: dashed; border-color: var(--vt-blue); }
.vt-dn { display: inline-grid; place-items: center; min-width: 24px; height: 24px; border-radius: 99px; font-size: 12.5px; font-weight: 600; }
.vt-cd.today .vt-dn { background: var(--vt-gradient); color: #fff; }
.vt-pill {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    border-radius: 7px;
    padding: 3px 6px;
    margin-top: 4px;
    font-weight: 600;
    transition: transform 0.12s, box-shadow 0.12s;
}
.vt-pill > span { flex: 1; min-width: 0; }
.vt-pill:hover { transform: scale(1.03); box-shadow: 0 3px 8px rgba(10, 31, 68, 0.15); }
.vt-pill[draggable='true'] { cursor: grab; }
.vt-pill.late { box-shadow: inset 3px 0 0 #d9363e; }
.vt-more { font-size: 11.5px; color: var(--vt-muted); margin-top: 4px; }

/* ===================== Agenda mejorada ===================== */
.ag-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 14px;
    padding: 10px 14px;
    margin-bottom: 14px;
}
.ag-vistas {
    display: flex;
    gap: 4px;
    padding: 4px;
    border-radius: 12px;
    background: #f1f5f9;
}
.ag-vistas button {
    display: flex;
    align-items: center;
    gap: 7px;
    border: 0;
    background: none;
    border-radius: 9px;
    padding: 7px 14px;
    font: 600 13.5px Figtree, sans-serif;
    color: #5d7085;
    cursor: pointer;
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
}
.ag-vistas button.on {
    background: #fff;
    color: var(--vt-blue);
    box-shadow: 0 3px 10px rgba(10, 31, 68, 0.1);
}
.ag-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 auto;
}
.ag-flecha {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid var(--vt-line);
    background: #fff;
    display: grid;
    place-items: center;
    cursor: pointer;
    color: var(--vt-navy);
    transition: background 0.15s, transform 0.15s, border-color 0.15s;
}
.ag-flecha:hover {
    background: var(--vt-blue-soft);
    border-color: var(--vt-blue);
    transform: scale(1.08);
}
.ag-titulo {
    min-width: 210px;
    text-align: center;
    font: 800 20px 'Bricolage Grotesque', sans-serif;
    text-transform: capitalize;
    color: var(--vt-navy);
}
.ag-titulo-enter-active,
.ag-titulo-leave-active {
    transition: opacity 0.2s, transform 0.2s;
}
.ag-titulo-enter-from { opacity: 0; transform: translateY(6px); }
.ag-titulo-leave-to { opacity: 0; transform: translateY(-6px); }

.ag-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 16px;
    align-items: start;
}
.ag-resumen {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}
.ag-res {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-left: 4px solid var(--c);
    border-radius: 12px;
    animation: vt-rise 0.4s both;
}
.ag-res-ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--c);
    flex: none;
}
.ag-res b { display: block; font: 800 18px 'Bricolage Grotesque', sans-serif; line-height: 1.1; }
.ag-res small { font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--vt-muted); }

.ag-cal {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 16px;
    padding: 10px;
}
.ag-cal-enter-active,
.ag-cal-leave-active { transition: opacity 0.22s, transform 0.22s; }
.ag-cal-enter-from { opacity: 0; transform: translateX(14px); }
.ag-cal-leave-to { opacity: 0; transform: translateX(-14px); }
.ag-wd {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #5d7085;
    padding: 6px 0;
}
.ag-wd.finde { color: #7c4ddb; }
.ag-cd {
    position: relative;
    min-height: 104px;
    border: 1px solid #edf1f6;
    border-radius: 12px;
    padding: 6px;
    background: #fff;
    cursor: pointer;
    overflow: hidden;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s, background 0.15s;
}
.ag-cal.semana .ag-cd { min-height: 420px; }
.ag-cd:hover { border-color: #bcd3f2; box-shadow: 0 6px 16px rgba(4, 97, 216, 0.08); }
.ag-cd.finde { background: #fbf9ff; }
.ag-cd.pasado:not(.today) .ag-dn { color: #94a3b8; }
.ag-cd.out { opacity: 0.45; background: #f8fafc; }
.ag-cd.sel { border-color: var(--vt-blue); box-shadow: 0 0 0 3px var(--vt-blue-soft); }
.ag-cd.today { background: linear-gradient(180deg, #eef5ff, #fff 60%); border-color: #9cc2f5; }
.ag-cd.drop { background: var(--vt-blue-soft); border-style: dashed; border-color: var(--vt-blue); transform: scale(1.02); }
.ag-cd-h {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}
.ag-dn {
    min-width: 26px;
    height: 26px;
    border-radius: 99px;
    display: inline-grid;
    place-items: center;
    font: 700 13px Figtree, sans-serif;
    color: var(--vt-navy);
}
.ag-cd.today .ag-dn {
    color: #fff;
    background: linear-gradient(135deg, #0461d8, #1597a5);
    box-shadow: 0 4px 10px rgba(4, 97, 216, 0.35);
    animation: vt-pulse 2.4s ease-in-out infinite;
}
.ag-cd-dia { font-size: 12px; color: var(--vt-muted); text-transform: capitalize; }
.ag-cd-n {
    margin-left: auto;
    min-width: 20px;
    padding: 0 6px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    color: var(--vt-blue);
    background: var(--vt-blue-soft);
    text-align: center;
}
.ag-add {
    width: 22px;
    height: 22px;
    border: 0;
    border-radius: 7px;
    display: grid;
    place-items: center;
    font-size: 11px;
    color: #fff;
    background: var(--vt-blue);
    cursor: pointer;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.15s, transform 0.15s;
}
.ag-cd-h .ag-add { margin-left: auto; }
.ag-cd-h .ag-cd-n + .ag-add { margin-left: 0; }
.ag-cd:hover .ag-add { opacity: 1; transform: scale(1); }
.ag-pill {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    padding: 3px 7px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--vt-navy);
    background: color-mix(in srgb, var(--s) 10%, #fff);
    border-left: 3px solid var(--s);
    transition: transform 0.12s, box-shadow 0.12s;
    animation: vt-rise 0.3s both;
}
.ag-pill:hover { transform: translateX(2px); box-shadow: 0 4px 10px color-mix(in srgb, var(--s) 25%, transparent); }
.ag-pill[draggable='true'] { cursor: grab; }
.ag-pill > span.truncate { flex: 1; min-width: 0; }
.ag-pill-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--s); flex: none; }
.ag-pill.late { background: #fdeced; border-left-color: #e5484d; }
.ag-warn { color: #e5484d; }
.ag-cal.semana .ag-pill {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding: 8px 9px;
    margin-top: 8px;
    border-radius: 10px;
}
.ag-cal.semana .ag-pill b { font-size: 12.5px; width: 100%; }
.ag-pill-h { font-size: 11px; color: var(--s); font-weight: 700; }
.ag-pill-p { font-size: 11px; color: var(--vt-muted); font-weight: 500; width: 100%; }
.ag-pill-e { font-size: 10.5px; color: var(--s); text-transform: capitalize; }
.ag-more { font-size: 11.5px; color: var(--vt-blue); font-weight: 600; margin-top: 4px; }
.ag-leyenda {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    margin-top: 10px;
    font-size: 12.5px;
    color: #44566c;
}
.ag-leyenda span { display: inline-flex; align-items: center; gap: 6px; }
.ag-leyenda i { width: 10px; height: 10px; border-radius: 3px; background: var(--s); }
.ag-leyenda-v { color: #e5484d; }
.ag-tip { margin-left: auto; color: var(--vt-muted); }

/* Panel lateral del día */
.ag-side {
    position: sticky;
    top: 92px;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 16px;
    padding: 14px;
}
.ag-side-h {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: -14px -14px 12px;
    padding: 14px;
    border-radius: 16px 16px 0 0;
    color: #fff;
    background: linear-gradient(120deg, #0a1f44, #0d3a8a);
}
.ag-side-h.hoy { background: linear-gradient(120deg, #0461d8, #1597a5); }
.ag-side-num {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font: 800 24px 'Bricolage Grotesque', sans-serif;
    background: rgba(255, 255, 255, 0.16);
    flex: none;
}
.ag-side-h b { display: block; font-size: 16px; }
.ag-side-h small { color: #cfe0f3; }
.ag-side-n {
    margin-left: auto;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.18);
    white-space: nowrap;
}
.ag-side-body { max-height: calc(100vh - 360px); overflow: auto; display: grid; gap: 8px; }
.ag-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid var(--vt-line);
    border-left: 4px solid var(--s);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.ag-item:hover { transform: translateX(3px); box-shadow: 0 6px 14px color-mix(in srgb, var(--s) 20%, transparent); }
.ag-item-hora {
    font-size: 12px;
    font-weight: 700;
    color: var(--s);
    white-space: nowrap;
    min-width: 62px;
    padding-top: 2px;
}
.ag-item-go { color: #94a3b8; margin-top: 4px; }
.ag-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 26px 10px;
    text-align: center;
    color: var(--vt-muted);
    border: 1.5px dashed var(--vt-line);
    border-radius: 12px;
}
.ag-vacio span {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 22px;
    color: #1faf5c;
    background: var(--vt-green-soft);
}
.ag-vacio b { color: var(--vt-navy); }

@media (max-width: 1100px) {
    .ag-layout { grid-template-columns: 1fr; }
    .ag-side { position: static; }
}
@media (max-width: 760px) {
    .ag-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ag-cal { min-width: 640px; }
    .ag-main { overflow-x: auto; }
    .ag-nav { margin: 0; }
}

/* ===================== Agenda v3: eventos y simbología ===================== */
.ag-layout {
    grid-template-columns: minmax(0, 1fr) 250px !important;
}
.ag-cd { display: flex; flex-direction: column; gap: 3px; }
/* Evento compacto (mes) */
.ag-ev {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 6px 3px 3px;
    border-radius: 7px;
    background: #fff;
    border: 1px solid color-mix(in srgb, var(--s) 30%, #e6ecf3);
    box-shadow: inset 3px 0 0 var(--s);
    font-size: 11.5px;
    color: var(--vt-navy);
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, transform 0.12s;
    animation: vt-rise 0.3s both;
}
.ag-ev:hover {
    background: color-mix(in srgb, var(--s) 8%, #fff);
    box-shadow: inset 3px 0 0 var(--s), 0 3px 8px rgba(10, 31, 68, 0.1);
    transform: translateY(-1px);
}
.ag-ev[draggable='true'] { cursor: grab; }
.ag-ev.late { border-color: #f3c2c4; background: #fff7f7; }
.ag-ev-ic {
    width: 18px;
    height: 18px;
    border-radius: 5px;
    display: grid;
    place-items: center;
    font-size: 10.5px;
    color: #fff;
    background: var(--s);
    flex: none;
}
.ag-ev-t { font-weight: 700; color: #44566c; flex: none; font-size: 11px; }
.ag-ev-n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.ag-ev-ok { color: #1faf5c; flex: none; }
.ag-ev-ko { color: #e5484d; flex: none; }
.ag-more {
    align-self: flex-start;
    border: 0;
    background: #eef3fb;
    color: var(--vt-blue);
    font: 700 11px Figtree, sans-serif;
    padding: 2px 8px;
    border-radius: 99px;
    cursor: pointer;
    margin-top: 2px;
}
.ag-more:hover { background: var(--vt-blue); color: #fff; }
.ag-mas-pop .ant-popover-title { text-transform: none; }
.ag-mas-lista { display: grid; gap: 5px; width: 260px; }

/* Tarjeta de evento (semana) */
.ag-ev-card {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 6px;
    padding: 8px 9px 8px 11px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e6ecf3;
    box-shadow: inset 4px 0 0 var(--s), 0 2px 6px rgba(10, 31, 68, 0.05);
    cursor: pointer;
    transition: box-shadow 0.15s, transform 0.12s;
    animation: vt-rise 0.3s both;
}
.ag-ev-card:hover { transform: translateY(-2px); box-shadow: inset 4px 0 0 var(--s), 0 8px 16px rgba(10, 31, 68, 0.12); }
.ag-ev-card[draggable='true'] { cursor: grab; }
.ag-ev-card.late { background: #fff7f7; border-color: #f3c2c4; }
.ag-ev-card-h { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.ag-ev-hora { font-size: 11.5px; font-weight: 700; color: #44566c; white-space: nowrap; }
.ag-ev-st {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    display: grid;
    place-items: center;
    font-size: 11px;
    color: #fff;
    background: var(--s);
}
.ag-ev-nombre {
    font-size: 12.5px;
    line-height: 1.3;
    color: var(--vt-navy);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ag-ev-meta { font-size: 11px; color: var(--vt-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ag-ev-pie { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 3px; }
.ag-ev-estatus {
    font-size: 10.5px;
    font-weight: 700;
    color: var(--s);
    background: color-mix(in srgb, var(--s) 10%, #fff);
    padding: 1px 7px;
    border-radius: 99px;
}
.ag-ev-res { font-size: 10.5px; font-weight: 700; }
.ag-ev-res.pos { color: #16804b; }
.ag-ev-res.neg { color: #c0262e; }

/* Simbología (derecha) */
.ag-simb {
    position: sticky;
    top: 92px;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 16px;
    padding: 14px 14px 16px;
}
.ag-simb-t {
    display: flex;
    align-items: center;
    gap: 8px;
    font: 700 15px 'Bricolage Grotesque', sans-serif;
    color: var(--vt-navy);
    padding-bottom: 10px;
    border-bottom: 1px solid var(--vt-line);
}
.ag-simb-t .anticon { color: var(--vt-blue); }
.ag-simb-sec {
    margin: 14px 0 6px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--vt-muted);
}
.ag-simb-r {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 2px;
    font-size: 13px;
    color: var(--vt-navy);
}
.ag-simb-r b {
    margin-left: auto;
    min-width: 24px;
    text-align: center;
    font-size: 12px;
    padding: 0 7px;
    border-radius: 99px;
    color: var(--s);
    background: color-mix(in srgb, var(--s) 10%, #fff);
}
.ag-simb-r > .anticon { width: 18px; text-align: center; }
.ag-simb-hoy {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #0461d8, #1597a5);
}
.ag-simb-finde { width: 20px; height: 14px; border-radius: 4px; background: #f5f1ff; border: 1px solid #e3d9fb; }
.ag-simb-tip {
    display: flex;
    gap: 8px;
    font-size: 12px;
    line-height: 1.45;
    color: #5d7085;
    padding: 4px 0;
}
.ag-simb-tip .anticon { color: var(--vt-blue); margin-top: 3px; }
@media (max-width: 1100px) {
    .ag-layout { grid-template-columns: 1fr !important; }
    .ag-simb { position: static; }
}

/* ===================== Eventos v4: bloques sólidos ===================== */
.ag-ev {
    gap: 6px;
    padding: 0 6px 0 3px;
    height: 24px;
    border: 0;
    border-radius: 6px;
    background: color-mix(in srgb, var(--s) 88%, #000);
    box-shadow: 0 1px 2px rgba(10, 31, 68, 0.18);
    color: #fff;
    font-size: 12px;
}
.ag-ev:hover {
    background: color-mix(in srgb, var(--s) 78%, #000);
    box-shadow: 0 4px 10px color-mix(in srgb, var(--s) 40%, transparent);
    transform: translateY(-1px);
}
.ag-ev.late { background: color-mix(in srgb, var(--s) 88%, #000); border: 0; }
.ag-ev .ag-ev-ic {
    width: 18px;
    height: 18px;
    font-size: 11px;
    background: rgba(255, 255, 255, 0.22);
    border-radius: 5px;
}
.ag-ev .ag-ev-t {
    color: #fff;
    font-weight: 800;
    font-size: 11.5px;
    opacity: 0.95;
}
.ag-ev .ag-ev-n {
    color: #fff;
    font-weight: 600;
}
.ag-ev-res-b {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 9px;
    color: var(--s);
    background: #fff;
    flex: none;
}
/* Semana: tarjeta con encabezado de color */
.ag-ev-card {
    padding: 0;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--s) 35%, #e6ecf3);
    box-shadow: 0 2px 6px rgba(10, 31, 68, 0.06);
    gap: 2px;
}
.ag-ev-card:hover { box-shadow: 0 8px 18px color-mix(in srgb, var(--s) 25%, transparent); }
.ag-ev-card.late { background: #fff; border-color: color-mix(in srgb, var(--s) 35%, #e6ecf3); }
.ag-ev-card-h {
    padding: 5px 8px;
    background: color-mix(in srgb, var(--s) 88%, #000);
    color: #fff;
}
.ag-ev-card .ag-ev-hora { color: #fff; }
.ag-ev-card .ag-ev-st { background: rgba(255, 255, 255, 0.22); }
.ag-ev-card .ag-ev-nombre { padding: 4px 9px 0; }
.ag-ev-card .ag-ev-meta { padding: 0 9px; }
.ag-ev-card .ag-ev-pie { padding: 0 9px 8px; }
.ag-ev-card .ag-ev-estatus { color: color-mix(in srgb, var(--s) 85%, #000); }
/* Simbología con los mismos colores */
.ag-simb-r .ag-ev-ic {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--s) 88%, #000);
    color: #fff;
}
.ag-simb-res {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 10px;
    color: #44566c;
    background: #fff;
    border: 1.5px solid #94a3b8;
}
</style>
