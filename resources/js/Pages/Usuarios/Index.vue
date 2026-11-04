<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import dayjs from 'dayjs';
import {
    UserOutlined, PlusOutlined, SearchOutlined, EditOutlined, FolderOpenOutlined, StopOutlined, CheckCircleOutlined, MailOutlined,
    PhoneOutlined, IdcardOutlined, SafetyOutlined, ShopOutlined, ReadOutlined, HistoryOutlined, EnvironmentOutlined,
    LockOutlined, FileTextOutlined, CrownOutlined, EyeOutlined, AuditOutlined, RocketOutlined, ShoppingOutlined, ClearOutlined,
    SettingOutlined, ThunderboltOutlined, CopyOutlined, ExclamationCircleFilled, TeamOutlined, CalendarOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import PieModal from '@/Components/PieModal.vue';
import KpiBar from '@/Components/KpiBar.vue';
import SucursalSelect from '@/Components/SucursalSelect.vue';
import DocumentosModal from '@/Components/DocumentosModal.vue';
import DireccionCampos from '@/Components/DireccionCampos.vue';
import TelefonoInput from '@/Components/TelefonoInput.vue';
import { iniciales, limpiar, generarContrasena, fechaMayorDeEdad, type Paginado, type Opcion } from '@/utils';
import { useShared } from '@/composables/useShared';
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import { confirmarEstado } from '@/confirmar';

const props = defineProps<{
    usuarios: Paginado<any>;
    kpis: { total: number; activos: number; inactivos: number; perfiles: Record<string, number> };
    filtros: Record<string, any>;
    perfiles: string[];
    niveles: string[];
    sucursales: Opcion[];
}>();
const { user } = useShared();
const altoTabla = useAlturaTabla(500);

const f = reactive({
    q: props.filtros.q ?? '',
    perfil: props.filtros.perfil ?? null,
    estado: props.filtros.estado ?? null,
    nivel: props.filtros.nivel ?? null,
});
function buscar(page = 1) {
    router.get('/usuarios', limpiar({ ...f, page: page > 1 ? page : null }), { preserveState: true, preserveScroll: true, replace: true });
}
const hayFiltros = computed(() => !!(f.q || f.perfil || f.nivel));
function limpiarFiltros() {
    Object.assign(f, { q: '', perfil: null, nivel: null });
    buscar();
}

const PERFIL: Record<string, { color: string; hex: string; icon: any; desc: string; plural: string }> = {
    Administrador: { color: 'blue', hex: '#0461d8', icon: CrownOutlined, plural: 'Administradores', desc: 'Control total de sucursales, catálogos y asignaciones.' },
    Supervisor: { color: 'purple', hex: '#7c4ddb', icon: EyeOutlined, plural: 'Supervisores', desc: 'Visibilidad global, revisión y supervisión de visitas; programa visitas.' },
    Auditor: { color: 'cyan', hex: '#1597a5', icon: AuditOutlined, plural: 'Auditores', desc: 'Visibilidad global de todas las sucursales y revisión de visitas.' },
    Promotor: { color: 'green', hex: '#1faf5c', icon: RocketOutlined, plural: 'Promotores', desc: 'Ejecución de visitas, registro de evidencias y envío de portafolios.' },
    Prospecto: { color: 'orange', hex: '#f08c00', icon: ShoppingOutlined, plural: 'Prospectos', desc: 'Acceso limitado para contactar a sus promotores asignados.' },
};
const kpiItems = computed(() => [
    { key: 'todos', icon: TeamOutlined, valor: props.kpis.activos, label: 'Usuarios activos', color: '#0a1f44', activo: !f.perfil },
    ...props.perfiles.map((p) => ({ key: p, icon: PERFIL[p].icon, valor: props.kpis.perfiles[p] || 0, label: PERFIL[p].plural, color: PERFIL[p].hex, activo: f.perfil === p })),
]);
function elegirKpi(k: string) {
    f.perfil = k === 'todos' ? null : k;
    buscar();
}

// ---------- Formulario en pestañas ----------
const modal = ref(false);
const pestana = ref('generales');
const editando = ref<any>(null);
const vacio = {
    name: '', apellido_paterno: '', apellido_materno: '', email: '', password: '', password_confirmation: '', perfil: 'Promotor', telefono: '',
    fecha_nacimiento: null as string | null, curp: '', rfc: '', direccion: '', colonia: '', ciudad: '', estado: '', codigo_postal: '',
    nivel_estudios: null as string | null, estudios_detalle: '', todas: false, sucursal_ids: [] as number[],
};
const form = useForm({ ...vacio });
const CAMPOS: Record<string, string[]> = {
    generales: ['name', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'curp', 'rfc', 'telefono', 'nivel_estudios', 'estudios_detalle'],
    direccion: ['direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal'],
    acceso: ['email', 'password', 'password_confirmation', 'perfil', 'sucursal_ids'],
};
const conError = (tab: string) => CAMPOS[tab].some((k) => (form.errors as any)[k]);

function abrir(u: any = null) {
    editando.value = u;
    pestana.value = 'generales';
    form.clearErrors();
    const datos: any = { ...vacio };
    if (u) for (const k of Object.keys(vacio)) if (k in u && u[k] !== null) datos[k] = u[k];
    datos.password = '';
    datos.password_confirmation = '';
    datos.todas = u ? u.sucursal_ids.length === props.sucursales.length && props.sucursales.length > 1 : false;
    form.defaults(datos);
    form.reset();
    modal.value = true;
}
function guardar() {
    if (form.password && form.password !== form.password_confirmation) {
        pestana.value = 'acceso';
        form.setError('password_confirmation', 'Las contraseñas no coinciden.');
        return;
    }
    const opts = {
        preserveScroll: true,
        onSuccess: () => (modal.value = false),
        onError: () => {
            const tab = Object.keys(CAMPOS).find(conError);
            if (tab) pestana.value = tab;
        },
    };
    if (editando.value) form.put(`/usuarios/${editando.value.id}`, opts);
    else form.post('/usuarios', opts);
}
async function alternar(u: any) {
    if (await confirmarEstado(u.activo, 'este usuario', u.nombre_completo)) router.delete(`/usuarios/${u.id}`, { preserveScroll: true });
}

// Contraseña aleatoria
const verPass = ref(false);
function generar() {
    const p = generarContrasena(12);
    form.password = p;
    form.password_confirmation = p;
    verPass.value = true;
    navigator.clipboard?.writeText(p).then(() => message.success('Contraseña generada y copiada al portapapeles')).catch(() => message.success('Contraseña generada'));
}
async function copiarPass() {
    await navigator.clipboard?.writeText(form.password);
    message.success('Contraseña copiada');
}
const fuerza = computed(() => {
    const p = form.password || '';
    let n = 0;
    if (p.length >= 8) n++;
    if (p.length >= 12) n++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) n++;
    if (/\d/.test(p)) n++;
    if (/[^A-Za-z0-9]/.test(p)) n++;
    return n;
});
const FUERZA = ['Muy débil', 'Débil', 'Regular', 'Buena', 'Fuerte', 'Muy fuerte'];
const COLOR_FUERZA = ['#e5484d', '#e5484d', '#f0a202', '#f0a202', '#1faf5c', '#1faf5c'];

// Fecha de nacimiento: solo mayores de 18 años
const fechaNoValida = (d: dayjs.Dayjs) => d.isAfter(fechaMayorDeEdad(), 'day');
const edad = computed(() => (form.fecha_nacimiento ? dayjs().diff(dayjs(form.fecha_nacimiento), 'year') : null));

const docs = reactive({ open: false, id: null as number | null, titulo: '' });
const err = (k: keyof typeof form.errors) => ({ 'validate-status': form.errors[k] ? 'error' : '', help: form.errors[k] });

const columnas = [
    { title: 'Usuario', key: 'nombre', icon: UserOutlined, ellipsis: true },
    { title: 'Perfil', key: 'perfil', icon: SafetyOutlined, width: 160 },
    { title: 'Estudios', key: 'estudios', icon: ReadOutlined, width: 170 },
    { title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 200 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 150, align: 'center' },
];
const TOTAL = (t: number, r: number[]) => `Mostrando ${r[0]} a ${r[1]} de ${t} resultados`;
</script>

<template>
    <PageHeader title="Usuarios y accesos" subtitle="Expediente, perfil y sucursales asignadas de cada usuario" :icon="UserOutlined">
        <template #extra><a-button type="primary" size="large" @click="abrir()"><PlusOutlined /> Nuevo usuario</a-button></template>
    </PageHeader>

    <div class="vt-toolbar">
        <SucursalSelect />
        <a-segmented :value="f.estado || 'activos'" @change="(v: any) => { f.estado = v === 'activos' ? null : v; buscar(); }"
            :options="[{ label: 'Activos', value: 'activos' }, { label: `Inactivos (${kpis.inactivos})`, value: 'inactivos' }]" />
    </div>

    <KpiBar :items="kpiItems" clicables @elegir="elegirKpi" />

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" :columns="columnas" :data-source="usuarios.data" row-key="id" :row-class-name="(r: any) => (r.activo ? '' : 'vt-inactivo')"
            :pagination="{ current: usuarios.current_page, pageSize: usuarios.per_page, total: usuarios.total, onChange: buscar, showSizeChanger: false, showTotal: TOTAL }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'nombre'">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="vt-av" :style="{ background: PERFIL[record.perfil]?.hex, color: '#fff' }">{{ iniciales(record.nombre_completo) }}</span>
                        <div class="min-w-0">
                            <b class="block truncate">{{ record.nombre_completo }}</b>
                            <div class="vt-small vt-muted truncate"><MailOutlined style="color: #e0457b" /> {{ record.email }}<template v-if="record.telefono"> · <PhoneOutlined style="color: #1faf5c" /> {{ record.telefono }}</template></div>
                        </div>
                    </div>
                </template>
                <template v-else-if="column.key === 'perfil'">
                    <span class="vt-tag solido" :style="{ background: PERFIL[record.perfil]?.hex }"><component :is="PERFIL[record.perfil]?.icon" /> {{ record.perfil }}</span>
                </template>
                <template v-else-if="column.key === 'estudios'">
                    <span v-if="record.nivel_estudios" class="vt-nowrap"><ReadOutlined style="color: #7c4ddb" /> {{ record.nivel_estudios }}</span><span v-else class="vt-muted">—</span>
                    <div class="vt-small vt-muted truncate">{{ record.estudios_detalle }}</div>
                </template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <div class="vt-acciones-fila">
                        <a-tooltip title="Expediente (INE, CURP, acta, comprobante…)">
                            <a-badge :count="record.documentos_count" size="small" color="#f08c00" :offset="[-2, 2]">
                                <a-button class="vt-ab naranja" @click="Object.assign(docs, { open: true, id: record.id, titulo: record.nombre_completo })"><FolderOpenOutlined /></a-button>
                            </a-badge>
                        </a-tooltip>
                        <a-tooltip title="Editar"><a-button class="vt-ab azul" @click="abrir(record)"><EditOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.id !== user.id" :title="record.activo ? 'Desactivar' : 'Activar'">
                            <a-button class="vt-ab" :class="record.activo ? 'rojo' : 'verde'" @click="alternar(record)"><StopOutlined v-if="record.activo" /><CheckCircleOutlined v-else /></a-button>
                        </a-tooltip>
                    </div>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0">
                            <a-input v-model:value="f.q" placeholder="Filtrar" allow-clear @press-enter="buscar()" @change="(e: any) => !e.target.value && buscar()"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="1">
                            <a-select v-model:value="f.perfil" allow-clear placeholder="Todos" @change="buscar()" :options="perfiles.map((p) => ({ value: p, label: p }))" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="2">
                            <a-select v-model:value="f.nivel" allow-clear placeholder="Todos" @change="buscar()" :options="niveles.map((n) => ({ value: n, label: n }))" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="3"></a-table-summary-cell>
                        <a-table-summary-cell :index="4" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
    </div>

    <a-modal v-model:open="modal" width="min(900px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #3f51b5, #0461d8)"><UserOutlined /></span>
                <div>{{ editando ? 'Editar usuario' : 'Nuevo usuario' }}<small>{{ editando ? editando.nombre_completo : 'Captura el expediente por secciones' }}</small></div>
            </div>
        </template>
        <a-form layout="vertical">
            <a-tabs v-model:active-key="pestana" class="vt-tabs-form">
                <a-tab-pane key="generales">
                    <template #tab><span class="vt-tab"><IdcardOutlined /> Datos generales <ExclamationCircleFilled v-if="conError('generales')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid vt-g3">
                        <a-form-item label="Nombre(s)" required v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><UserOutlined style="color: #0461d8" /></template></a-input></a-form-item>
                        <a-form-item label="Apellido paterno"><a-input v-model:value="form.apellido_paterno" /></a-form-item>
                        <a-form-item label="Apellido materno"><a-input v-model:value="form.apellido_materno" /></a-form-item>
                        <a-form-item label="Fecha de nacimiento" v-bind="err('fecha_nacimiento')" :extra="edad !== null ? `${edad} años` : 'Debe ser mayor de 18 años'">
                            <a-date-picker v-model:value="form.fecha_nacimiento" value-format="YYYY-MM-DD" format="DD/MM/YYYY" class="w-full"
                                :disabled-date="fechaNoValida" :default-picker-value="fechaMayorDeEdad()" placeholder="DD/MM/AAAA" />
                        </a-form-item>
                        <a-form-item label="CURP" v-bind="err('curp')"><a-input v-model:value="form.curp" :maxlength="18" class="uppercase"><template #prefix><FileTextOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                        <a-form-item label="RFC" v-bind="err('rfc')"><a-input v-model:value="form.rfc" :maxlength="13" class="uppercase"><template #prefix><FileTextOutlined style="color: #1597a5" /></template></a-input></a-form-item>
                        <a-form-item label="Teléfono" v-bind="err('telefono')"><TelefonoInput v-model:value="form.telefono" /></a-form-item>
                        <a-form-item label="Nivel de estudios"><a-select v-model:value="form.nivel_estudios" allow-clear placeholder="Selecciona" :options="niveles.map((n) => ({ value: n, label: n }))" /></a-form-item>
                        <a-form-item label="Carrera / especialidad"><a-input v-model:value="form.estudios_detalle"><template #prefix><ReadOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                    </div>
                    <p class="vt-small vt-muted"><FolderOpenOutlined style="color: #f08c00" /> Los documentos del expediente (acta, INE, CURP, comprobante de domicilio…) se cargan desde el botón de expediente de la lista.</p>
                </a-tab-pane>

                <a-tab-pane key="direccion">
                    <template #tab><span class="vt-tab"><EnvironmentOutlined /> Dirección <ExclamationCircleFilled v-if="conError('direccion')" class="vt-tab-err" /></span></template>
                    <DireccionCampos :model-value="form" :errors="form.errors" />
                </a-tab-pane>

                <a-tab-pane key="acceso">
                    <template #tab><span class="vt-tab"><LockOutlined /> Acceso y perfil <ExclamationCircleFilled v-if="conError('acceso')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid vt-g3">
                        <a-form-item label="Correo (usuario)" required v-bind="err('email')"><a-input v-model:value="form.email" type="email" autocomplete="off"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item :label="editando ? 'Nueva contraseña (opcional)' : 'Contraseña'" :required="!editando" v-bind="err('password')">
                            <a-input-password v-model:value="form.password" v-model:visible="verPass" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                                <template #prefix><LockOutlined style="color: #0461d8" /></template>
                            </a-input-password>
                        </a-form-item>
                        <a-form-item label="Confirmar contraseña" :required="!editando || !!form.password" v-bind="err('password_confirmation')">
                            <a-input-password v-model:value="form.password_confirmation" v-model:visible="verPass" autocomplete="new-password" placeholder="Repite la contraseña"
                                :status="form.password_confirmation && form.password_confirmation !== form.password ? 'error' : ''">
                                <template #prefix><LockOutlined style="color: #1faf5c" /></template>
                            </a-input-password>
                        </a-form-item>
                    </div>
                    <div class="vt-pass-tools">
                        <a-button @click="generar"><ThunderboltOutlined style="color: #f0a202" /> Generar contraseña segura</a-button>
                        <a-button v-if="form.password" @click="copiarPass"><CopyOutlined style="color: #0461d8" /> Copiar</a-button>
                        <div v-if="form.password" class="vt-fuerza">
                            <div class="vt-fuerza-bar"><i v-for="n in 5" :key="n" :style="{ background: n <= fuerza ? COLOR_FUERZA[fuerza] : '#e3e8ee' }"></i></div>
                            <span :style="{ color: COLOR_FUERZA[fuerza] }">{{ FUERZA[fuerza] }}</span>
                        </div>
                        <span v-if="form.password_confirmation" class="vt-small" :style="{ color: form.password === form.password_confirmation ? '#1faf5c' : '#e5484d' }">
                            <CheckCircleOutlined v-if="form.password === form.password_confirmation" /><ExclamationCircleFilled v-else />
                            {{ form.password === form.password_confirmation ? 'Las contraseñas coinciden' : 'Las contraseñas no coinciden' }}
                        </span>
                    </div>

                    <a-form-item label="Perfil" required class="mt-4">
                        <div class="vt-perfiles">
                            <button v-for="p in perfiles" :key="p" type="button" class="vt-perfil" :class="{ on: form.perfil === p }" :style="{ '--c': PERFIL[p].hex }" @click="form.perfil = p">
                                <span class="vt-perfil-ic"><component :is="PERFIL[p].icon" /></span>
                                <b>{{ p }}</b>
                                <small>{{ PERFIL[p].desc }}</small>
                            </button>
                        </div>
                    </a-form-item>
                    <a-form-item label="Sucursales asignadas" v-bind="err('sucursal_ids')"
                        :extra="['Auditor', 'Supervisor'].includes(form.perfil) ? 'Este perfil ve todas las sucursales automáticamente.' : 'Solo verá la información de las sucursales asignadas.'">
                        <a-checkbox v-model:checked="form.todas" class="mb-2">Todas las sucursales</a-checkbox>
                        <a-select v-if="!form.todas" v-model:value="form.sucursal_ids" mode="multiple" placeholder="Selecciona una o varias" :options="sucursales.map((s) => ({ value: s.id, label: s.nombre }))" />
                    </a-form-item>
                </a-tab-pane>
            </a-tabs>
        </a-form>
        <template #footer>
            <PieModal tabla="users" :registro="editando" :guardando="form.processing" @cancelar="modal = false" @guardar="guardar" />
        </template>
    </a-modal>

    <DocumentosModal v-model:open="docs.open" tipo="usuario" :id="docs.id" :titulo="docs.titulo" @cambio="router.reload({ only: ['usuarios'] })" />
</template>

<style>
.vt-perfiles {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
}
.vt-perfil {
    text-align: left;
    border: 1.5px solid var(--vt-line);
    background: #fff;
    border-radius: 12px;
    padding: 10px;
    cursor: pointer;
    font: inherit;
    color: inherit;
    transition: border-color 0.15s, background 0.15s, transform 0.15s;
}
.vt-perfil:hover {
    border-color: var(--c);
    transform: translateY(-2px);
}
.vt-perfil.on {
    border-color: var(--c);
    background: color-mix(in srgb, var(--c) 8%, #fff);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 15%, transparent);
}
.vt-perfil-ic {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--c);
    margin-bottom: 6px;
}
.vt-perfil b {
    display: block;
    font-size: 13px;
}
.vt-perfil small {
    display: block;
    font-size: 11px;
    color: var(--vt-muted);
    line-height: 1.35;
    margin-top: 2px;
}
@media (max-width: 860px) {
    .vt-perfiles {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
