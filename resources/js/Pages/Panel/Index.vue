<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import {
    CalendarOutlined, PlayCircleOutlined, RiseOutlined, CheckCircleOutlined, EnvironmentOutlined, WarningOutlined, EyeOutlined,
    UnorderedListOutlined, ShopOutlined, TeamOutlined, PlusCircleOutlined, LikeOutlined, DislikeOutlined, FieldTimeOutlined,
    TrophyOutlined, FundOutlined, ScheduleOutlined, UserOutlined, ArrowRightOutlined, ClockCircleOutlined, SmileOutlined,
    ThunderboltOutlined, BarChartOutlined, FlagOutlined, LineChartOutlined, FallOutlined, PieChartOutlined, TagsOutlined,
} from '@ant-design/icons-vue';
import EstatusTag from '@/Components/EstatusTag.vue';
import VisitaModal from '@/Components/VisitaModal.vue';
import ProgramarVisitaModal from '@/Components/ProgramarVisitaModal.vue';
import { ESTATUS, ESTATUS_INFO, ESTATUS_DOT, diaMes, iniciales, fechaLarga, hora12, type VisitaFila, type Estatus, type Opcion } from '@/utils';
import { useShared } from '@/composables/useShared';

const props = defineProps<{
    sucursales: Opcion[];
    sucursal: number | null;
    kpis: { hoy: number; hoyPorIniciar: number; proceso: number; porRevisar: number; positivas: number; negativas: number; total: number };
    porEstatus: Record<Estatus, number>;
    deHoy: VisitaFila[];
    vencidasTotal: number;
    agenda: VisitaFila[];
    giros: { nombre: string; total: number; positivas: number }[];
    opciones: { prospectos: Opcion[]; promotores: Opcion[] };
}>();

const { user, can, hoy } = useShared();
const abierta = ref<number | null>(null);
const programar = ref(false);

const cerradas = computed(() => props.kpis.positivas + props.kpis.negativas);
const enCurso = computed(() => props.kpis.total - cerradas.value);
const efectividad = computed(() => (cerradas.value ? Math.round((props.kpis.positivas / cerradas.value) * 100) : 0));
const pct = (n: number, t: number) => (t ? Math.round((n / t) * 100) : 0);

// Dona de efectividad (positivas / negativas / en curso)
const R = 54;
const C = 2 * Math.PI * R;
const arcos = computed(() => {
    const total = props.kpis.total || 0;
    if (!total) return [];
    let off = 0;
    return [
        { n: props.kpis.positivas, c: 'url(#g-pos)' },
        { n: props.kpis.negativas, c: 'url(#g-neg)' },
        { n: enCurso.value, c: '#e3e9f1' },
    ]
        .filter((a) => a.n > 0)
        .map((a) => {
            const L = (a.n / total) * C;
            const largo = Math.max(L - 1.5, 1);
            const r = { color: a.c, dash: `${largo} ${C - largo}`, offset: -off };
            off += L;
            return r;
        });
});

const kpiCards = computed(() => [
    { k: 'hoy', icon: CalendarOutlined, valor: props.kpis.hoy, t: 'Visitas de hoy', s: `${props.kpis.hoyPorIniciar} por iniciar`, c1: '#0461d8', c2: '#3b8bff', ir: () => router.visit('/agenda?vista=lista') },
    { k: 'proceso', icon: PlayCircleOutlined, valor: props.kpis.proceso, t: 'En proceso', s: 'en campo ahora', c1: '#f08c00', c2: '#ffb547', ir: () => irLista('proceso') },
    { k: 'revisar', icon: EyeOutlined, valor: props.kpis.porRevisar, t: 'Por revisar', s: 'esperan auditoría', c1: '#1597a5', c2: '#3cc6c9', ir: () => irLista('realizada') },
    { k: 'vencidas', icon: WarningOutlined, valor: props.vencidasTotal, t: 'Vencidas', s: 'fuera de fecha', c1: '#e5484d', c2: '#ff7b7f', ir: () => router.visit('/visitas?vencidas=1') },
    { k: 'positivas', icon: TrophyOutlined, valor: props.kpis.positivas, t: 'Ventas cerradas', s: `${efectividad.value}% de efectividad`, c1: '#1faf5c', c2: '#47d987', ir: () => router.visit('/visitas?resultado=positiva') },
    { k: 'total', icon: FundOutlined, valor: props.kpis.total, t: 'Visitas totales', s: `${cerradas.value} cerradas`, c1: '#7c4ddb', c2: '#a27cf0', ir: () => irLista() },
]);

const ICONOS: Record<string, any> = {
    programada: CalendarOutlined, proceso: PlayCircleOutlined, realizada: CheckCircleOutlined, revisada: EyeOutlined, supervisada: FlagOutlined,
};
const maxEstatus = computed(() => Math.max(1, ...ESTATUS.map((s) => props.porEstatus[s] || 0)));
// Agenda unificada: hoy, mañana y los próximos 7 días
const manana = dayjs().add(1, 'day').format('YYYY-MM-DD');
const vista = ref<'hoy' | 'manana' | 'semana'>('hoy');
const deDia = (d: string) => props.agenda.filter((v) => v.fecha === d);
const conteos = computed(() => ({ hoy: deDia(hoy.value).length, manana: deDia(manana).length, semana: props.agenda.length }));
const grupos = computed(() => {
    const lista = vista.value === 'hoy' ? deDia(hoy.value) : vista.value === 'manana' ? deDia(manana) : props.agenda;
    const m = new Map<string, VisitaFila[]>();
    for (const v of lista) m.set(v.fecha, [...(m.get(v.fecha) || []), v]);
    return [...m.entries()].map(([fecha, visitas]) => ({ fecha, visitas }));
});
const etiquetaDia = (d: string) => (d === hoy.value ? 'Hoy' : d === manana ? 'Mañana' : dayjs(d).format('dddd'));
const VACIO = { hoy: 'Sin visitas para hoy', manana: 'Sin visitas para mañana', semana: 'Sin visitas en los próximos 7 días' };

// Visitas por tipo de negocio
const COLORES_GIRO = ['#0461d8', '#7c4ddb', '#1597a5', '#f08c00', '#e0457b', '#1faf5c'];
const maxGiro = computed(() => Math.max(1, ...props.giros.map((g) => g.total)));

// Gráfica de pastel por estatus
const rebanadaActiva = ref<string | null>(null);
const rebanadas = computed(() => {
    const total = props.kpis.total;
    if (!total) return [];
    const C = 100, R = 88;
    let ang = -Math.PI / 2;
    return ESTATUS.map((st) => ({ st, n: props.porEstatus[st] || 0 })).filter((x) => x.n > 0).map(({ st, n }) => {
        const a0 = ang;
        const delta = (n / total) * Math.PI * 2;
        ang += delta;
        const a1 = ang;
        const medio = a0 + delta / 2;
        const p = (a: number, r: number) => [C + r * Math.cos(a), C + r * Math.sin(a)];
        const [x0, y0] = p(a0, R);
        const [x1, y1] = p(a1, R);
        const d = n === total
            ? `M ${C} ${C - R} A ${R} ${R} 0 1 1 ${C - 0.01} ${C - R} Z`
            : `M ${C} ${C} L ${x0} ${y0} A ${R} ${R} 0 ${delta > Math.PI ? 1 : 0} 1 ${x1} ${y1} Z`;
        const [tx, ty] = p(medio, R * 0.62);
        return { s: st, n, d, tx, ty };
    });
});

// Barras horizontales por tipo de negocio (de mayor a menor) con eje de valores "redondos"
const girosOrdenados = computed(() => [...props.giros].sort((a, b) => b.total - a.total || b.positivas - a.positivas));
const ejeGiros = computed(() => {
    const max = Math.max(1, ...props.giros.map((g) => g.total));
    const crudo = max / 4;
    const mag = Math.pow(10, Math.floor(Math.log10(crudo)));
    const paso = Math.max(1, Math.ceil(crudo / mag) * mag);
    const tope = Math.ceil(max / paso) * paso;
    const ticks = Array.from({ length: tope / paso + 1 }, (_, i) => i * paso);
    return { max: tope, ticks };
});

function filtrar(id: number | null) {
    router.get('/', id ? { sucursal: id } : {}, { preserveState: true, preserveScroll: true });
}
const irLista = (estatus?: string) => router.visit('/visitas' + (estatus ? `?estatus=${estatus}` : ''));

const saludo = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
});
const ahora = dayjs().format('YYYY-MM-DD');
</script>

<template>
    <Head title="Panel" />

    <!-- Banner de bienvenida -->
    <section class="pn-hero">
        <div class="pn-hero-deco d1"></div><div class="pn-hero-deco d2"></div><div class="pn-hero-dots"></div>
        <div class="pn-hero-in">
            <div class="min-w-0">
                <span class="pn-hero-chip"><SmileOutlined /> {{ user.perfil }}</span>
                <h1>{{ saludo }}, {{ user.nombre }}</h1>
                <p><CalendarOutlined /> {{ fechaLarga(hoy) }} · <ShopOutlined /> {{ user.sucursales.length > 1 ? 'todas tus sucursales' : 'sucursal ' + (user.sucursales[0] || '') }}</p>
                <div v-if="sucursales.length > 1" class="pn-hero-sucs">
                    <button :class="{ on: !sucursal }" @click="filtrar(null)">Todas</button>
                    <button v-for="s in sucursales" :key="s.id" :class="{ on: sucursal === s.id }" @click="filtrar(s.id)"><ShopOutlined /> {{ s.nombre }}</button>
                </div>
            </div>
            <div class="pn-hero-side">
                <div class="pn-hero-ring">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="10" />
                        <circle class="pn-ring-val" cx="60" cy="60" r="50" fill="none" stroke="#2dcb6a" stroke-width="10" stroke-linecap="round"
                            :stroke-dasharray="`${(efectividad / 100) * 314} 314`" transform="rotate(-90 60 60)" />
                    </svg>
                    <div><b>{{ efectividad }}%</b><small>efectividad</small></div>
                </div>
                <div class="pn-hero-btns">
                    <a-button v-if="can.programar" size="large" class="pn-btn-blanco" @click="programar = true"><PlusCircleOutlined /> Programar visita</a-button>
                    <a-button size="large" class="pn-btn-glass" @click="router.visit('/agenda')"><ScheduleOutlined /> Abrir agenda</a-button>
                    <a-button size="large" class="pn-btn-glass" @click="irLista()"><UnorderedListOutlined /> Lista de trabajo</a-button>
                </div>
            </div>
        </div>
    </section>

    <!-- Indicadores -->
    <div class="pn-kpis">
        <button v-for="(k, i) in kpiCards" :key="k.k" class="pn-kpi" :style="{ '--c1': k.c1, '--c2': k.c2, animationDelay: i * 0.05 + 's' }" @click="k.ir()">
            <span class="pn-kpi-ic"><component :is="k.icon" /></span>
            <span class="pn-kpi-txt">
                <small>{{ k.t }}</small>
                <b>{{ k.valor }}</b>
                <em>{{ k.s }}</em>
            </span>
            <ArrowRightOutlined class="pn-kpi-go" />
        </button>
    </div>

    <div class="vt-g12 pn-grid">
        <!-- Efectividad -->
        <section class="pn-card s4">
            <header class="pn-card-h">
                <span class="pn-card-ic" style="--c: #1faf5c"><RiseOutlined /></span>
                <div><h3>Efectividad</h3><small>Resultado de las visitas cerradas</small></div>
            </header>
            <div class="pn-donut">
                <svg viewBox="0 0 140 140" width="190" height="190" role="img" :aria-label="`Efectividad ${efectividad}%`">
                    <defs>
                        <linearGradient id="g-pos" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2dcb6a" /><stop offset="1" stop-color="#16804b" /></linearGradient>
                        <linearGradient id="g-neg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff7b7f" /><stop offset="1" stop-color="#d9363e" /></linearGradient>
                    </defs>
                    <circle cx="70" cy="70" :r="R" fill="none" stroke="#f1f4f8" stroke-width="16" />
                    <circle v-for="(a, i) in arcos" :key="i" class="pn-seg" cx="70" cy="70" :r="R" fill="none" :stroke="a.color" stroke-width="16"
                        :stroke-dasharray="a.dash" :stroke-dashoffset="a.offset" transform="rotate(-90 70 70)" />
                    <text x="70" y="68" text-anchor="middle" class="pn-donut-n">{{ efectividad }}%</text>
                    <text x="70" y="86" text-anchor="middle" class="pn-donut-s">positivas</text>
                </svg>
                <div class="pn-leg">
                    <div style="--c: #1faf5c"><LikeOutlined /><span>Positivas</span><b>{{ kpis.positivas }}</b></div>
                    <div style="--c: #e5484d"><DislikeOutlined /><span>Negativas</span><b>{{ kpis.negativas }}</b></div>
                    <div style="--c: #94a3b8"><ClockCircleOutlined /><span>En curso</span><b>{{ enCurso }}</b></div>
                </div>
            </div>
        </section>

        <!-- Visitas por estatus (gráfica de pastel) -->
        <section class="pn-card s4">
            <header class="pn-card-h">
                <span class="pn-card-ic" style="--c: #7c4ddb"><PieChartOutlined /></span>
                <div><h3>Visitas por estatus</h3><small>Clic en un estatus para ver la lista</small></div>
            </header>
            <div class="pn-pie">
                <svg viewBox="0 0 200 200" class="pn-pie-svg" role="img" aria-label="Visitas por estatus">
                    <circle v-if="!kpis.total" cx="100" cy="100" r="88" fill="#eef2f7" />
                    <path v-for="(r, i) in rebanadas" :key="r.s" :d="r.d" :fill="ESTATUS_DOT[r.s]" class="pn-slice"
                        :class="{ activa: rebanadaActiva === r.s }" :style="{ animationDelay: i * 0.08 + 's' }"
                        @mouseenter="rebanadaActiva = r.s" @mouseleave="rebanadaActiva = null" @click="irLista(r.s)">
                        <title>{{ ESTATUS_INFO[r.s].label }}: {{ r.n }} ({{ pct(r.n, kpis.total) }}%)</title>
                    </path>
                    <text v-for="r in rebanadas.filter((x) => x.n / kpis.total >= 0.08)" :key="'t' + r.s" :x="r.tx" :y="r.ty" text-anchor="middle" dominant-baseline="middle" class="pn-pie-pct">{{ pct(r.n, kpis.total) }}%</text>
                </svg>
                <div class="pn-pie-leg">
                    <button v-for="st in ESTATUS" :key="st" :style="{ '--s': ESTATUS_DOT[st] }" :class="{ activa: rebanadaActiva === st }"
                        @mouseenter="rebanadaActiva = st" @mouseleave="rebanadaActiva = null" @click="irLista(st)">
                        <i></i><span>{{ ESTATUS_INFO[st].label }}</span><b>{{ porEstatus[st] || 0 }}</b>
                    </button>
                </div>
            </div>
        </section>

        <!-- Visitas por tipo de negocio (barras horizontales apiladas: ventas + sin venta) -->
        <section class="pn-card s4">
            <header class="pn-card-h">
                <span class="pn-card-ic" style="--c: #e0457b"><BarChartOutlined /></span>
                <div><h3>Por tipo de negocio</h3><small>Visitas por giro del prospecto y cuántas terminaron en venta</small></div>
            </header>
            <template v-if="giros.length">
                <div class="pn-hb-leg">
                    <span><i style="background: #1faf5c"></i> Con venta</span>
                    <span><i style="background: #6c9ff0"></i> Sin venta</span>
                    <span class="pn-hb-leg-r">Total · conversión</span>
                </div>
                <div class="pn-hb">
                    <div class="pn-hb-grid" aria-hidden="true">
                        <i v-for="t in ejeGiros.ticks" :key="t" :style="{ left: (t / ejeGiros.max) * 100 + '%' }"></i>
                    </div>
                    <a-tooltip v-for="(g, i) in girosOrdenados" :key="g.nombre" placement="top">
                        <template #title>
                            <b>{{ g.nombre }}</b><br />
                            {{ g.total }} visita(s) · {{ g.positivas }} con venta · {{ g.total - g.positivas }} sin venta<br />
                            Conversión: {{ pct(g.positivas, g.total) }}%
                        </template>
                        <div class="pn-hb-r" :style="{ animationDelay: i * 0.06 + 's' }">
                            <span class="pn-hb-l" :title="g.nombre">{{ g.nombre }}</span>
                            <div class="pn-hb-track">
                                <div class="pn-hb-bar" :style="{ width: (g.total / ejeGiros.max) * 100 + '%', animationDelay: 0.1 + i * 0.06 + 's' }">
                                    <span v-if="g.positivas" class="pn-hb-pos" :style="{ flexGrow: g.positivas }"></span>
                                    <span v-if="g.total - g.positivas" class="pn-hb-neg" :style="{ flexGrow: g.total - g.positivas }"></span>
                                </div>
                            </div>
                            <span class="pn-hb-v"><b>{{ g.total }}</b> · {{ pct(g.positivas, g.total) }}%</span>
                        </div>
                    </a-tooltip>
                    <div class="pn-hb-eje" aria-hidden="true">
                        <span></span>
                        <div class="pn-hb-ticks"><small v-for="t in ejeGiros.ticks" :key="t" :style="{ left: (t / ejeGiros.max) * 100 + '%' }">{{ t }}</small></div>
                        <span></span>
                    </div>
                </div>
            </template>
            <div v-else class="pn-vacio"><span class="pn-vacio-ic"><BarChartOutlined /></span><b>Sin visitas todavía</b></div>
        </section>

        <!-- Agenda unificada: hoy, mañana y la semana -->
        <section class="pn-card s12 pn-agenda">
            <header class="pn-card-h">
                <span class="pn-card-ic" style="--c: #0461d8"><FieldTimeOutlined /></span>
                <div><h3>Agenda</h3><small>{{ fechaLarga(hoy) }}</small></div>
                <a-button @click="router.visit('/agenda')"><ScheduleOutlined /> Abrir agenda</a-button>
            </header>
            <div class="pn-tabs">
                <button :class="{ on: vista === 'hoy' }" @click="vista = 'hoy'"><CalendarOutlined /> Hoy <b>{{ conteos.hoy }}</b></button>
                <button :class="{ on: vista === 'manana' }" @click="vista = 'manana'"><ThunderboltOutlined /> Mañana <b>{{ conteos.manana }}</b></button>
                <button :class="{ on: vista === 'semana' }" @click="vista = 'semana'"><ScheduleOutlined /> Próximos 7 días <b>{{ conteos.semana }}</b></button>
            </div>
            <Transition name="pn-fade" mode="out-in">
                <div :key="vista" class="pn-agenda-body">
                    <template v-if="grupos.length">
                        <div v-for="g in grupos" :key="g.fecha" class="pn-dia-g">
                            <div v-if="vista === 'semana'" class="pn-dia-h" :class="{ hoy: g.fecha === hoy }">
                                <span class="pn-dia-chip"><b>{{ dayjs(g.fecha).date() }}</b><small>{{ dayjs(g.fecha).format('MMM') }}</small></span>
                                <span class="capitalize">{{ etiquetaDia(g.fecha) }}</span>
                                <em>{{ g.visitas.length }} visita(s)</em>
                            </div>
                            <div class="pn-timeline">
                                <div v-for="v in g.visitas" :key="v.id" class="pn-tl" :style="{ '--s': ESTATUS_DOT[v.estatus] }" @click="abierta = v.id">
                                    <div class="pn-tl-hora">{{ v.hora ? hora12(v.hora) : '—' }}</div>
                                    <div class="pn-tl-dot"><component :is="ICONOS[v.estatus]" /></div>
                                    <div class="pn-tl-card">
                                        <div class="min-w-0">
                                            <b class="block truncate">{{ v.prospecto.nombre }}</b>
                                            <span class="vt-small vt-muted"><ShopOutlined /> {{ v.sucursal.nombre }} · <UserOutlined /> {{ v.promotor.nombre }}</span>
                                        </div>
                                        <EstatusTag :estatus="v.estatus" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="pn-vacio">
                        <span class="pn-vacio-ic"><CheckCircleOutlined /></span>
                        <b>{{ VACIO[vista] }}</b>
                        <small>Aprovecha para planear tus visitas en la agenda.</small>
                    </div>
                </div>
            </Transition>
        </section>
    </div>

    <VisitaModal :visita-id="abierta" :opciones="opciones" @close="abierta = null" />
    <ProgramarVisitaModal v-if="can.programar" v-model:open="programar" :opciones="opciones" />
</template>

<style>
/* ---------- Banner ---------- */
.pn-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 26px 30px;
    color: #fff;
    background: linear-gradient(115deg, #0a1f44 0%, #0d3a8a 45%, #1597a5 80%, #2dcb6a 120%);
    box-shadow: 0 18px 40px rgba(10, 31, 68, 0.22);
    animation: vt-rise 0.45s both;
}
.pn-hero-deco {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}
.pn-hero-deco.d1 { width: 340px; height: 340px; right: -60px; top: -140px; background: radial-gradient(circle, rgba(45, 203, 106, 0.35), transparent 70%); }
.pn-hero-deco.d2 { width: 260px; height: 260px; left: 32%; bottom: -170px; background: radial-gradient(circle, rgba(59, 139, 255, 0.45), transparent 70%); }
.pn-hero-dots {
    position: absolute;
    right: 40%;
    top: 18px;
    width: 120px;
    height: 70px;
    background-image: radial-gradient(rgba(255, 255, 255, 0.3) 1.4px, transparent 1.4px);
    background-size: 14px 14px;
}
.pn-hero-in {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.pn-hero-chip {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 11px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.16);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
}
.pn-hero h1 {
    color: #fff;
    font-size: 32px;
    margin: 10px 0 4px;
}
.pn-hero p {
    margin: 0;
    color: #d3e2f6;
}
.pn-hero-sucs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 14px;
}
.pn-hero-sucs button {
    border: 0;
    border-radius: 99px;
    padding: 6px 14px;
    font: 600 13px Figtree, sans-serif;
    color: #fff;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.22);
    cursor: pointer;
    transition: background 0.15s, transform 0.15s;
}
.pn-hero-sucs button:hover { transform: translateY(-2px); background: rgba(255, 255, 255, 0.22); }
.pn-hero-sucs button.on { background: #fff; color: #0a1f44; }
.pn-hero-side {
    display: flex;
    align-items: center;
    gap: 22px;
}
.pn-hero-ring {
    position: relative;
    width: 120px;
    height: 120px;
    flex: none;
}
.pn-hero-ring svg { width: 100%; height: 100%; }
.pn-ring-val { animation: pn-ring 1.1s 0.2s both; }
@keyframes pn-ring { from { stroke-dasharray: 0 314; } }
.pn-hero-ring > div {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    text-align: center;
}
.pn-hero-ring b { font: 800 26px 'Bricolage Grotesque', sans-serif; line-height: 1; }
.pn-hero-ring small { font-size: 11px; color: #cfe0f3; }
.pn-hero-btns {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pn-btn-blanco {
    background: #fff !important;
    color: #0a1f44 !important;
    border: 0 !important;
    font-weight: 700 !important;
}
.pn-btn-blanco .anticon { color: #1faf5c; }
.pn-btn-glass {
    background: rgba(255, 255, 255, 0.12) !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}
.pn-btn-glass:hover { background: rgba(255, 255, 255, 0.22) !important; }

/* ---------- Indicadores ---------- */
.pn-kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin: 16px 0;
}
.pn-kpi {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 16px;
    padding: 16px 14px;
    cursor: pointer;
    font: inherit;
    color: inherit;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    animation: vt-rise 0.45s both;
}
.pn-kpi::after {
    content: '';
    position: absolute;
    right: -26px;
    bottom: -26px;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--c1), var(--c2));
    opacity: 0.1;
    transition: transform 0.3s;
}
.pn-kpi:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--c1) 40%, var(--vt-line));
    box-shadow: 0 14px 28px color-mix(in srgb, var(--c1) 22%, transparent);
}
.pn-kpi:hover::after { transform: scale(1.4); }
.pn-kpi-ic {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 21px;
    color: #fff;
    background: linear-gradient(135deg, var(--c1), var(--c2));
    box-shadow: 0 8px 18px color-mix(in srgb, var(--c1) 35%, transparent);
    flex: none;
}
.pn-kpi-txt { min-width: 0; }
.pn-kpi-txt small {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--vt-muted);
    white-space: nowrap;
}
.pn-kpi-txt b {
    display: block;
    font: 800 28px 'Bricolage Grotesque', sans-serif;
    color: var(--vt-navy);
    line-height: 1.1;
}
.pn-kpi-txt em {
    display: block;
    font-style: normal;
    font-size: 12px;
    color: var(--c1);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pn-kpi-go {
    position: absolute;
    right: 12px;
    top: 12px;
    font-size: 12px;
    color: var(--c1);
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity 0.2s, transform 0.2s;
}
.pn-kpi:hover .pn-kpi-go { opacity: 1; transform: none; }

/* ---------- Tarjetas ---------- */
.pn-grid { margin-top: 0; }
.pn-grid .s6 { grid-column: span 6; }
.pn-card {
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 18px;
    padding: 18px;
    min-width: 0;
    animation: vt-rise 0.45s 0.1s both;
    transition: box-shadow 0.2s;
}
.pn-card:hover { box-shadow: 0 12px 30px rgba(10, 31, 68, 0.07); }
.pn-card-h {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.pn-card-h > div { flex: 1; min-width: 0; }
.pn-card-h h3 { margin: 0; font-size: 17px; font-weight: 700; }
.pn-card-h small { color: var(--vt-muted); font-size: 12.5px; }
.pn-card-ic {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 18px;
    color: #fff;
    background: var(--c);
    box-shadow: 0 6px 14px color-mix(in srgb, var(--c) 35%, transparent);
    flex: none;
}

/* Línea de tiempo */
.pn-timeline { position: relative; }
.pn-tl {
    display: grid;
    grid-template-columns: 82px 34px 1fr;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    position: relative;
}
.pn-tl + .pn-tl { margin-top: 10px; }
.pn-tl::before {
    content: '';
    position: absolute;
    left: 108px;
    top: -10px;
    bottom: -10px;
    width: 2px;
    background: #e6ecf3;
}
.pn-tl:first-child::before { top: 50%; }
.pn-tl:last-child::before { bottom: 50%; }
.pn-tl-hora {
    text-align: right;
    font-weight: 700;
    font-size: 13px;
    color: var(--vt-navy);
    white-space: nowrap;
}
.pn-tl-dot {
    position: relative;
    z-index: 1;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--s);
    box-shadow: 0 0 0 4px #fff, 0 0 0 6px color-mix(in srgb, var(--s) 25%, transparent);
}
.pn-tl-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--s) 6%, #fff);
    border: 1px solid color-mix(in srgb, var(--s) 22%, var(--vt-line));
    transition: transform 0.15s, box-shadow 0.15s;
    min-width: 0;
}
.pn-tl:hover .pn-tl-card { transform: translateX(4px); box-shadow: 0 8px 18px color-mix(in srgb, var(--s) 18%, transparent); }

/* Vacío */
.pn-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 4px;
    padding: 26px 10px;
    color: var(--vt-muted);
    border: 1.5px dashed var(--vt-line);
    border-radius: 14px;
}
.pn-vacio b { color: var(--vt-navy); }
.pn-vacio-ic {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 24px;
    color: #1faf5c;
    background: var(--vt-green-soft);
    margin-bottom: 4px;
}

/* Dona */
.pn-donut {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 22px;
    flex-wrap: wrap;
}
.pn-donut-n { font: 800 28px 'Bricolage Grotesque', sans-serif; fill: var(--vt-navy); }
.pn-donut-s { font-size: 11px; fill: var(--vt-muted); }
.pn-seg { animation: pn-seg 1s 0.2s both; }
@keyframes pn-seg { from { stroke-dasharray: 0 400; } }
.pn-leg { display: grid; gap: 10px; min-width: 190px; }
.pn-leg > div {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--c) 8%, #fff);
    border: 1px solid color-mix(in srgb, var(--c) 20%, transparent);
}
.pn-leg .anticon { color: var(--c); font-size: 16px; }
.pn-leg b { margin-left: auto; font: 800 18px 'Bricolage Grotesque', sans-serif; color: var(--c); }

/* Estatus */
.pn-stack {
    display: flex;
    height: 14px;
    border-radius: 99px;
    overflow: hidden;
    background: #eef2f7;
    margin-bottom: 14px;
}
.pn-stack i { display: block; height: 100%; transform-origin: left; animation: vt-grow 0.9s both; }
.pn-est { display: grid; gap: 6px; }
.pn-est-r {
    display: grid;
    grid-template-columns: 30px 110px 1fr 34px 42px;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border: 0;
    border-radius: 10px;
    background: none;
    cursor: pointer;
    font: inherit;
    color: inherit;
    text-align: left;
    transition: background 0.15s;
}
.pn-est-r:hover { background: color-mix(in srgb, var(--s) 8%, #fff); }
.pn-est-ic {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--s);
}
.pn-est-l { font-weight: 600; font-size: 13.5px; }
.pn-est-bar { height: 8px; border-radius: 8px; background: #eef2f7; overflow: hidden; }
.pn-est-bar i { display: block; height: 100%; border-radius: 8px; background: var(--s); transform-origin: left; animation: vt-grow 0.9s 0.2s both; }
.pn-est-r b { text-align: right; font: 800 16px 'Bricolage Grotesque', sans-serif; }
.pn-est-r small { color: var(--vt-muted); font-size: 12px; text-align: right; }

/* Ranking */
.pn-rank { display: grid; gap: 12px; }
.pn-rank-r { display: flex; align-items: center; gap: 10px; }
.pn-medal {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font: 800 12px Figtree, sans-serif;
    flex: none;
    box-shadow: 0 3px 8px rgba(10, 31, 68, 0.15);
}
.vt-av.pn-av-suc { background: linear-gradient(135deg, #1597a5, #2dcb6a); color: #fff; }
.vt-av.pn-av-pro { background: linear-gradient(135deg, #0461d8, #7c4ddb); color: #fff; }
.pn-rank-bar { height: 8px; border-radius: 8px; background: #eef2f7; overflow: hidden; margin: 5px 0 3px; }
.pn-rank-bar i { display: block; height: 100%; border-radius: 8px; background: linear-gradient(90deg, #2dcb6a, #16804b); transform-origin: left; animation: vt-grow 0.9s 0.2s both; }

/* Próxima visita (promotor) */
.pn-prox {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 14px;
    background: linear-gradient(135deg, #fff7e8, #fff);
    border: 1px solid #fde3b4;
}
.pn-prox-av {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font: 800 18px 'Bricolage Grotesque', sans-serif;
    color: #fff;
    background: linear-gradient(135deg, #f08c00, #e0457b);
    flex: none;
}

/* Semana */
.pn-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
.pn-day {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 12px 4px;
    border-radius: 14px;
    border: 1px solid var(--vt-line);
    background: #fff;
    cursor: pointer;
    font: inherit;
    color: var(--vt-navy);
    transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}
.pn-day:hover { transform: translateY(-4px); border-color: var(--vt-blue); box-shadow: 0 10px 20px rgba(4, 97, 216, 0.12); }
.pn-day small { font-size: 12px; text-transform: capitalize; color: var(--vt-muted); }
.pn-day b { font: 800 22px 'Bricolage Grotesque', sans-serif; }
.pn-day-n {
    min-width: 26px;
    padding: 1px 8px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 700;
    color: var(--vt-blue);
    background: var(--vt-blue-soft);
}
.pn-day.libre .pn-day-n { color: #94a3b8; background: #f1f4f8; }
.pn-day-dots { display: flex; gap: 3px; height: 8px; }
.pn-day-dots i { width: 7px; height: 7px; border-radius: 50%; }
.pn-day em { font-style: normal; font-size: 11px; color: var(--vt-muted); }
.pn-day.today { background: linear-gradient(160deg, #0461d8, #1597a5); border-color: transparent; color: #fff; box-shadow: 0 10px 22px rgba(4, 97, 216, 0.3); }
.pn-day.today small, .pn-day.today em { color: #d6e6fb; }
.pn-day.today .pn-day-n { background: rgba(255, 255, 255, 0.22); color: #fff; }
.pn-day.today .pn-day-dots i { box-shadow: 0 0 0 1.5px #fff; }

/* Próximas visitas */
.pn-prox-list { display: grid; gap: 8px; }
.pn-prox-r {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 12px;
    border: 1px solid var(--vt-line);
    cursor: pointer;
    transition: transform 0.15s, border-color 0.15s, background 0.15s;
}
.pn-prox-r:hover { transform: translateX(4px); border-color: #e0457b; background: #fff6f9; }
.pn-fecha {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(135deg, #e0457b, #7c4ddb);
    flex: none;
    line-height: 1.05;
}
.pn-fecha b { font: 800 18px 'Bricolage Grotesque', sans-serif; }
.pn-fecha small { font-size: 10.5px; text-transform: uppercase; opacity: 0.9; }
.pn-prox-go { color: #e0457b; opacity: 0; transition: opacity 0.15s; }
.pn-prox-r:hover .pn-prox-go { opacity: 1; }

/* Agenda unificada */
.pn-agenda {
    display: flex;
    flex-direction: column;
}
.pn-tabs {
    display: flex;
    gap: 6px;
    padding: 4px;
    background: #f1f5f9;
    border-radius: 12px;
    margin-bottom: 14px;
}
.pn-tabs button {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 0;
    background: none;
    border-radius: 9px;
    padding: 8px 10px;
    font: 600 13.5px Figtree, sans-serif;
    color: #5d7085;
    cursor: pointer;
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
}
.pn-tabs button b {
    min-width: 22px;
    padding: 0 7px;
    border-radius: 99px;
    font-size: 12px;
    background: #e2e8f0;
    color: #44566c;
}
.pn-tabs button:hover {
    color: var(--vt-navy);
}
.pn-tabs button.on {
    background: #fff;
    color: var(--vt-blue);
    box-shadow: 0 3px 10px rgba(10, 31, 68, 0.1);
}
.pn-tabs button.on b {
    background: var(--vt-blue);
    color: #fff;
}
.pn-agenda-body {
    max-height: 340px;
    overflow: auto;
    padding-right: 4px;
}
.pn-dia-g + .pn-dia-g {
    margin-top: 16px;
}
.pn-dia-h {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-weight: 700;
    color: var(--vt-navy);
}
.pn-dia-h em {
    margin-left: auto;
    font-style: normal;
    font-size: 12px;
    font-weight: 600;
    color: var(--vt-muted);
}
.pn-dia-chip {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1.05;
    color: #0461d8;
    background: var(--vt-blue-soft);
}
.pn-dia-chip b {
    font: 800 16px 'Bricolage Grotesque', sans-serif;
}
.pn-dia-chip small {
    font-size: 10px;
    text-transform: uppercase;
}
.pn-dia-h.hoy .pn-dia-chip {
    color: #fff;
    background: linear-gradient(135deg, #0461d8, #1597a5);
}
.pn-fade-enter-active,
.pn-fade-leave-active {
    transition: opacity 0.2s, transform 0.2s;
}
.pn-fade-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.pn-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

/* Tendencia */
.pn-var {
    margin-left: auto;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 99px;
    white-space: nowrap;
}
.pn-var.up { color: #16804b; background: var(--vt-green-soft); }
.pn-var.down { color: #c0262e; background: #fdeced; }
.pn-chart {
    position: relative;
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    height: 220px;
    padding: 8px 4px 0;
}
.pn-chart-grid {
    position: absolute;
    inset: 30px 0 26px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
}
.pn-chart-grid i { display: block; border-top: 1px dashed #e6ecf3; }
.pn-mes {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 0;
}
.pn-mes-n { font: 800 14px 'Bricolage Grotesque', sans-serif; color: var(--vt-navy); }
.pn-mes-bars { flex: 1; width: 100%; display: flex; justify-content: center; align-items: flex-end; }
.pn-bar.total {
    position: relative;
    width: min(46px, 70%);
    min-height: 4px;
    border-radius: 10px 10px 4px 4px;
    background: linear-gradient(180deg, #3b8bff, #0461d8);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    transform-origin: bottom;
    animation: pn-sube 0.8s both;
    transition: filter 0.15s;
    cursor: pointer;
}
.pn-bar.total:hover { filter: brightness(1.12); }
.pn-bar.pos { width: 100%; background: linear-gradient(180deg, #47d987, #16804b); }
.pn-mes small { font-size: 12px; color: var(--vt-muted); text-transform: capitalize; }
.pn-mes.actual small { color: var(--vt-blue); font-weight: 700; }
.pn-mes.actual .pn-bar.total { box-shadow: 0 8px 18px rgba(4, 97, 216, 0.35); }
@keyframes pn-sube { from { transform: scaleY(0); } }
.pn-chart-leg { display: flex; gap: 16px; margin-top: 10px; font-size: 12.5px; color: var(--vt-muted); }
.pn-chart-leg span { display: flex; align-items: center; gap: 6px; }
.pn-chart-leg i { width: 12px; height: 12px; border-radius: 4px; }

/* Tipos de negocio */
.pn-giros { display: grid; gap: 12px; }
.pn-giro { display: flex; align-items: center; gap: 12px; }
.pn-giro-ic {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--g);
    box-shadow: 0 6px 12px color-mix(in srgb, var(--g) 30%, transparent);
    flex: none;
}
.pn-giro-bar { height: 9px; border-radius: 9px; background: #eef2f7; overflow: hidden; margin: 5px 0 3px; }
.pn-giro-bar i { display: block; height: 100%; border-radius: 9px; background: var(--g); transform-origin: left; animation: vt-grow 0.9s 0.2s both; }

@media (max-width: 1280px) {
    .pn-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991px) {
    .pn-grid > * { grid-column: 1 / -1 !important; }
    .pn-week { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .pn-hero-side { width: 100%; justify-content: space-between; }
}
@media (max-width: 640px) {
    .pn-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pn-hero { padding: 20px; }
    .pn-hero h1 { font-size: 24px; }
    .pn-hero-btns { flex-direction: row; flex-wrap: wrap; }
    .pn-est-r { grid-template-columns: 30px 1fr 34px; }
    .pn-est-bar, .pn-est-r small { display: none; }
}

/* ----- Distribución: 3 tarjetas en una línea y agenda abajo ----- */
.pn-grid .s4 { grid-column: span 4; }
.pn-grid .s12 { grid-column: 1 / -1; }
.pn-grid .s4 .pn-donut { gap: 14px; }
.pn-grid .s4 .pn-donut > svg { width: 160px; height: 160px; }
.pn-grid .s4 .pn-leg { min-width: 150px; }
.pn-grid .s4 .pn-est-r { grid-template-columns: 30px minmax(0, 1fr) 70px 26px 38px; }
.pn-agenda .pn-agenda-body { max-height: 420px; }
.pn-agenda .pn-tabs { max-width: 620px; }

/* Gráfica de barras por tipo de negocio */
.pn-gchart {
    position: relative;
    display: flex;
    align-items: stretch;
    gap: 10px;
    height: 230px;
    padding-top: 4px;
}
.pn-gchart-grid {
    position: absolute;
    inset: 26px 0 34px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
}
.pn-gchart-grid i { display: block; border-top: 1px dashed #e6ecf3; }
.pn-gcol {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.pn-gcol-n { font: 800 14px 'Bricolage Grotesque', sans-serif; color: var(--vt-navy); }
.pn-gcol-area { flex: 1; width: 100%; display: flex; justify-content: center; align-items: flex-end; }
.pn-gbar {
    position: relative;
    width: min(40px, 80%);
    min-height: 6px;
    border-radius: 10px 10px 4px 4px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--g) 55%, #fff), var(--g));
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    transform-origin: bottom;
    animation: pn-sube 0.8s both;
    box-shadow: 0 8px 16px color-mix(in srgb, var(--g) 25%, transparent);
    cursor: pointer;
    transition: filter 0.15s, transform 0.15s;
}
.pn-gbar:hover { filter: brightness(1.1); transform: scaleX(1.08); }
.pn-gbar-pos { width: 100%; background: linear-gradient(180deg, #47d987, #16804b); }
.pn-gcol-l {
    max-width: 100%;
    font-size: 11.5px;
    font-weight: 600;
    color: #44566c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@keyframes pn-sube { from { transform: scaleY(0); } }
@media (max-width: 1280px) {
    .pn-grid .s4 { grid-column: span 6; }
}

/* Pastel por estatus */
.pn-pie {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    justify-content: center;
}
.pn-pie-svg {
    width: 190px;
    height: 190px;
    flex: none;
    overflow: visible;
    filter: drop-shadow(0 8px 16px rgba(10, 31, 68, 0.12));
}
.pn-slice {
    stroke: #fff;
    stroke-width: 2.5;
    cursor: pointer;
    transform-origin: 100px 100px;
    transition: transform 0.2s, opacity 0.2s;
    animation: pn-slice-in 0.5s cubic-bezier(0.2, 0.9, 0.3, 1.2) both;
}
.pn-slice.activa {
    transform: scale(1.06);
}
.pn-pie-svg:hover .pn-slice:not(.activa) {
    opacity: 0.55;
}
@keyframes pn-slice-in {
    from { transform: scale(0.3) rotate(-30deg); opacity: 0; }
}
.pn-pie-pct {
    font: 800 12px Figtree, sans-serif;
    fill: #fff;
    pointer-events: none;
}
.pn-pie-leg {
    display: grid;
    gap: 6px;
    min-width: 150px;
    flex: 1;
}
.pn-pie-leg button {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--vt-line);
    border-radius: 10px;
    background: #fff;
    padding: 6px 10px;
    font: 600 13px Figtree, sans-serif;
    color: var(--vt-navy);
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
}
.pn-pie-leg button:hover,
.pn-pie-leg button.activa {
    border-color: var(--s);
    background: color-mix(in srgb, var(--s) 8%, #fff);
    transform: translateX(3px);
}
.pn-pie-leg i {
    width: 11px;
    height: 11px;
    border-radius: 4px;
    background: var(--s);
    flex: none;
}
.pn-pie-leg b {
    margin-left: auto;
    font: 800 15px 'Bricolage Grotesque', sans-serif;
    color: var(--s);
}

/* Líneas por tipo de negocio */
.pn-linea-svg {
    width: 100%;
    height: auto;
    max-height: 230px;
    overflow: visible;
}
.pn-linea-l {
    font: 600 10.5px Figtree, sans-serif;
    fill: #44566c;
}
.pn-linea-grid {
    stroke: #e6ecf3;
    stroke-dasharray: 4 4;
    vector-effect: non-scaling-stroke;
}
.pn-linea-t,
.pn-linea-p {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    vector-effect: non-scaling-stroke;
    stroke-dasharray: 1200;
    stroke-dashoffset: 1200;
    animation: pn-traza 1.2s 0.2s ease-out forwards;
}
.pn-linea-t { stroke: #7c4ddb; }
.pn-linea-p { stroke: #1faf5c; animation-delay: 0.4s; }
@keyframes pn-traza { to { stroke-dashoffset: 0; } }
.pn-pt {
    stroke: #fff;
    stroke-width: 2.5;
    cursor: pointer;
    transition: r 0.15s;
    animation: vt-rise 0.4s 0.8s both;
}
.pn-pt-t { fill: #7c4ddb; }
.pn-pt-p { fill: #1faf5c; }
.pn-pt:hover { r: 7; }
.pn-linea-n {
    font: 800 11px Figtree, sans-serif;
    fill: #5b3aa8;
}
.pn-linea-x {
    display: flex;
    justify-content: space-between;
    gap: 4px;
    margin-top: 2px;
}
.pn-linea-x small {
    flex: 1;
    min-width: 0;
    text-align: center;
    font-size: 11.5px;
    font-weight: 600;
    color: #44566c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Barras horizontales por tipo de negocio */
.pn-hb-leg {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 12px;
    color: #44566c;
    margin: -4px 0 10px;
}
.pn-hb-leg span { display: inline-flex; align-items: center; gap: 6px; }
.pn-hb-leg i { width: 11px; height: 11px; border-radius: 3px; }
.pn-hb-leg-r { margin-left: auto; color: var(--vt-muted); font-size: 11.5px; }
.pn-hb { position: relative; }
.pn-hb-grid {
    position: absolute;
    top: 0;
    bottom: 22px;
    left: 98px;
    right: 64px;
    pointer-events: none;
}
.pn-hb-grid i {
    position: absolute;
    top: 0;
    bottom: 0;
    border-left: 1px dashed #e3e9f1;
}
.pn-hb-r {
    position: relative;
    display: grid;
    grid-template-columns: 90px minmax(0, 1fr) 56px;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-radius: 8px;
    cursor: default;
    animation: vt-rise 0.35s both;
    transition: background 0.15s;
}
.pn-hb-r:hover { background: #f6f9fd; }
.pn-hb-l {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--vt-navy);
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pn-hb-track { position: relative; height: 22px; }
.pn-hb-bar {
    height: 100%;
    min-width: 6px;
    display: flex;
    gap: 2px;
    transform-origin: left;
    animation: vt-grow 0.8s both;
}
.pn-hb-bar > span { min-width: 3px; height: 100%; }
.pn-hb-bar > span:first-child { border-radius: 4px 0 0 4px; }
.pn-hb-bar > span:last-child { border-radius: 0 4px 4px 0; }
.pn-hb-bar > span:only-child { border-radius: 4px; }
.pn-hb-pos { background: #1faf5c; }
.pn-hb-neg { background: #6c9ff0; }
.pn-hb-r:hover .pn-hb-bar > span { filter: brightness(1.06); }
.pn-hb-v {
    font-size: 12px;
    color: #5d7085;
    white-space: nowrap;
}
.pn-hb-v b { font-size: 13.5px; color: var(--vt-navy); }
.pn-hb-eje {
    display: grid;
    grid-template-columns: 90px minmax(0, 1fr) 56px;
    gap: 8px;
    height: 22px;
}
.pn-hb-ticks { position: relative; border-top: 1px solid #dfe6ee; }
.pn-hb-ticks small {
    position: absolute;
    top: 3px;
    transform: translateX(-50%);
    font-size: 11px;
    color: var(--vt-muted);
}
</style>
