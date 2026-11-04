<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    TeamOutlined, PlusOutlined, SearchOutlined, EnvironmentOutlined, FolderOpenOutlined, EditOutlined, AimOutlined,
    StopOutlined, CheckCircleOutlined, KeyOutlined, PhoneOutlined, LinkOutlined, IdcardOutlined, ShopOutlined, TagsOutlined,
    UserOutlined, MailOutlined, HistoryOutlined, BankOutlined, LikeOutlined, CalendarOutlined, FileTextOutlined, ClearOutlined,
    SettingOutlined, TrophyOutlined, StarOutlined, ExclamationCircleFilled,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import PieModal from '@/Components/PieModal.vue';
import KpiBar from '@/Components/KpiBar.vue';
import SucursalSelect from '@/Components/SucursalSelect.vue';
import { useShared } from '@/composables/useShared';
import DocumentosModal from '@/Components/DocumentosModal.vue';
import DireccionCampos from '@/Components/DireccionCampos.vue';
import MapaModal from '@/Components/MapaModal.vue';
import TelefonoInput from '@/Components/TelefonoInput.vue';
import { limpiar, type Paginado, type Opcion } from '@/utils';
import { confirmarEstado } from '@/confirmar';
import { useAlturaTabla } from '@/composables/useAlturaTabla';

const props = defineProps<{
    prospectos: Paginado<any>;
    kpis: { total: number; activos: number; inactivos: number; portal: number; visitados: number; clientes: number };
    filtros: Record<string, any>;
    sucursales: Opcion[];
    tiposNegocio: Opcion[];
    puedeEditar: boolean;
}>();

const altoTabla = useAlturaTabla(500);
const { props: compartidos } = useShared();
const f = reactive({
    q: props.filtros.q ?? '',
    tipo_negocio: props.filtros.tipo_negocio ? Number(props.filtros.tipo_negocio) : null,
    tipo_persona: props.filtros.tipo_persona ?? null,
    ver: props.filtros.ver ?? null,
    ubicacion: props.filtros.ubicacion ?? '',
});
function buscar(page = 1) {
    router.get('/prospectos', limpiar({ ...f, page: page > 1 ? page : null }), { preserveState: true, preserveScroll: true, replace: true });
}
const hayFiltros = computed(() => !!(f.q || f.tipo_negocio || f.tipo_persona || f.ubicacion));
function limpiarFiltros() {
    Object.assign(f, { q: '', tipo_negocio: null, tipo_persona: null, ubicacion: '' });
    buscar();
}

const kpiItems = computed(() => [
    { key: 'total', icon: TeamOutlined, valor: props.kpis.total, label: 'Prospectos totales', color: '#0461d8' },
    { key: 'activos', icon: CheckCircleOutlined, valor: props.kpis.activos, label: 'Activos', color: '#1faf5c' },
    { key: 'visitados', icon: CalendarOutlined, valor: props.kpis.visitados, label: 'Con visitas', color: '#7c4ddb' },
    { key: 'clientes', icon: TrophyOutlined, valor: props.kpis.clientes, label: 'Con venta', color: '#f08c00' },
    { key: 'portal', icon: KeyOutlined, valor: props.kpis.portal, label: 'Acceso al portal', color: '#1597a5' },
    { key: 'inactivos', icon: StopOutlined, valor: props.kpis.inactivos, label: 'Inactivos', color: '#e5484d' },
]);

// ---------- Formulario en pestañas ----------
const modal = ref(false);
const pestana = ref('generales');
const editando = ref<any>(null);
const form = useForm({
    tipo_persona: 'Moral', nombre: '', nombre_comercial: '', rfc: '', contacto: '', telefono: '', email: '',
    direccion: '', colonia: '', ciudad: '', estado: '', codigo_postal: '', latitud: null as number | null, longitud: null as number | null,
    tipo_negocio_id: null as number | null, sucursal_id: null as number | null, notas: '',
    portal: false, portal_email: '', portal_password: '',
});
const CAMPOS: Record<string, string[]> = {
    generales: ['tipo_persona', 'nombre', 'nombre_comercial', 'rfc', 'contacto', 'telefono', 'email', 'tipo_negocio_id', 'sucursal_id'],
    ubicacion: ['direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal', 'latitud', 'longitud'],
    portal: ['portal_email', 'portal_password', 'notas'],
};
const conError = (tab: string) => CAMPOS[tab].some((k) => (form.errors as any)[k]);

function abrir(p: any = null) {
    editando.value = p;
    pestana.value = 'generales';
    form.clearErrors();
    const t = (k: string) => p?.[k] ?? '';
    form.defaults({
        tipo_persona: p?.tipo_persona ?? 'Moral', nombre: t('nombre'), nombre_comercial: t('nombre_comercial'), rfc: t('rfc'),
        contacto: t('contacto'), telefono: t('telefono'), email: t('email'), direccion: t('direccion'), colonia: t('colonia'),
        ciudad: t('ciudad'), estado: t('estado'), codigo_postal: t('codigo_postal'),
        latitud: p?.latitud ?? null, longitud: p?.longitud ?? null, tipo_negocio_id: p?.tipo_negocio_id ?? null,
        sucursal_id: p?.sucursal_id ?? compartidos.value.sucursalActual ?? null, notas: t('notas'),
        portal: !!p?.portal?.activo, portal_email: p?.portal?.email ?? p?.email ?? '', portal_password: '',
    });
    form.reset();
    enlace.value = '';
    modal.value = true;
}

function guardar() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (modal.value = false),
        onError: () => {
            const tab = Object.keys(CAMPOS).find(conError);
            if (tab) pestana.value = tab;
        },
    };
    if (editando.value) form.put(`/prospectos/${editando.value.id}`, opts);
    else form.post('/prospectos', opts);
}

async function alternar(p: any) {
    if (await confirmarEstado(p.activo, 'este prospecto', p.nombre)) router.delete(`/prospectos/${p.id}`, { preserveScroll: true });
}

// ---------- Ubicación (Google Maps) ----------
const ubicando = ref(false);
function miUbicacion() {
    if (!navigator.geolocation) return message.error('Tu navegador no permite obtener la ubicación');
    ubicando.value = true;
    navigator.geolocation.getCurrentPosition(
        (p) => {
            form.latitud = +p.coords.latitude.toFixed(7);
            form.longitud = +p.coords.longitude.toFixed(7);
            ubicando.value = false;
        },
        () => {
            ubicando.value = false;
            message.error('No se pudo obtener la ubicación');
        },
        { enableHighAccuracy: true, timeout: 15000 },
    );
}
const enlace = ref('');
/** Acepta un enlace de Google Maps (…/@lat,lng… o ?q=lat,lng) o coordenadas "lat, lng". */
function desdeEnlace() {
    const m = enlace.value.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/) || enlace.value.match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);
    if (!m) return message.warning('No se encontraron coordenadas en el enlace');
    form.latitud = +m[1];
    form.longitud = +m[2];
    message.success('Ubicación tomada del enlace');
}
const mapa = computed(() => {
    const q = form.latitud && form.longitud
        ? `${form.latitud},${form.longitud}`
        : [form.direccion, form.colonia, form.ciudad, form.estado, form.codigo_postal].filter(Boolean).join(', ');
    return q ? `https://maps.google.com/maps?q=${encodeURIComponent(q)}&z=16&output=embed` : null;
});

// ---------- Documentos y mapa ----------
const docs = reactive({ open: false, id: null as number | null, titulo: '' });
const verDocs = (p: any) => Object.assign(docs, { open: true, id: p.id, titulo: p.nombre });
const mapaVer = reactive({ open: false, p: null as any });
const direccionDe = (p: any) => [p.direccion, p.colonia, p.ciudad, p.estado].filter(Boolean).join(', ');

const columnas = [
    { title: 'Prospecto', key: 'nombre', icon: IdcardOutlined, ellipsis: true },
    { title: 'Persona', key: 'persona', icon: BankOutlined, width: 120 },
    { title: 'Giro', key: 'giro', icon: TagsOutlined, width: 140 },
    { title: 'Ubicación', key: 'ubicacion', icon: EnvironmentOutlined, ellipsis: true },
    { title: 'Visitas', key: 'visitas', icon: CalendarOutlined, width: 95, align: 'center' },
    { title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 200 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 150, align: 'center' },
];
const err = (k: keyof typeof form.errors) => ({ 'validate-status': form.errors[k] ? 'error' : '', help: form.errors[k] });
const TOTAL = (t: number, r: number[]) => `Mostrando ${r[0]} a ${r[1]} de ${t} resultados`;
</script>

<template>
    <PageHeader title="Prospectos" subtitle="Clientes a visitar: personas físicas o morales, su ubicación y documentos" :icon="TeamOutlined">
        <template #extra>
            <a-button v-if="puedeEditar" type="primary" size="large" @click="abrir()"><PlusOutlined /> Nuevo prospecto</a-button>
        </template>
    </PageHeader>

    <div class="vt-toolbar">
        <SucursalSelect />
        <a-segmented :value="f.ver || 'activos'" @change="(v: any) => { f.ver = v === 'activos' ? null : v; buscar(); }"
            :options="[{ label: 'Activos', value: 'activos' }, { label: 'Inactivos', value: 'inactivos' }]" />
    </div>

    <KpiBar :items="kpiItems" />

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" :columns="columnas" :data-source="prospectos.data" row-key="id"
            :row-class-name="(r: any) => (r.activo ? '' : 'vt-inactivo')"
            :pagination="{ current: prospectos.current_page, pageSize: prospectos.per_page, total: prospectos.total, onChange: buscar, showSizeChanger: false, showTotal: TOTAL }"
            :locale="{ emptyText: 'No hay prospectos con estos filtros.' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'nombre'">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="vt-av" :style="{ background: record.tipo_persona === 'Moral' ? '#e6f0fd' : '#fff1e0', color: record.tipo_persona === 'Moral' ? '#0461d8' : '#f08c00' }">
                            <BankOutlined v-if="record.tipo_persona === 'Moral'" /><UserOutlined v-else />
                        </span>
                        <div class="min-w-0">
                            <b class="block truncate">{{ record.nombre }}
                                <a-tooltip v-if="record.portal?.activo" title="Tiene acceso al portal de prospectos"><KeyOutlined style="color: #1faf5c" /></a-tooltip>
                            </b>
                            <div class="vt-small vt-muted truncate">
                                <template v-if="record.contacto">{{ record.contacto }}</template>
                                <template v-if="record.telefono"> · <PhoneOutlined style="color: #1faf5c" /> {{ record.telefono }}</template>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else-if="column.key === 'persona'">
                    <a-tag :color="record.tipo_persona === 'Moral' ? 'blue' : 'orange'"><BankOutlined v-if="record.tipo_persona === 'Moral'" /><UserOutlined v-else /> {{ record.tipo_persona }}</a-tag>
                </template>
                <template v-else-if="column.key === 'giro'"><a-tag v-if="record.giro" color="purple"><TagsOutlined /> {{ record.giro }}</a-tag></template>
                <template v-else-if="column.key === 'ubicacion'">
                    <a class="block truncate" @click="Object.assign(mapaVer, { open: true, p: record })"><EnvironmentOutlined style="color: #f08c00" /> {{ direccionDe(record) }}</a>
                    <div v-if="record.latitud" class="vt-small" style="color: #1faf5c"><AimOutlined /> Ubicación exacta</div>
                </template>
                <template v-else-if="column.key === 'visitas'">
                    <b>{{ record.visitas_count }}</b>
                    <div class="vt-small" style="color: #1faf5c" v-if="record.positivas_count"><LikeOutlined /> {{ record.positivas_count }}</div>
                </template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <div class="vt-acciones-fila">
                        <a-tooltip title="Documentos"><a-badge :count="record.documentos_count" :offset="[-2, 2]" size="small" color="#f08c00">
                            <a-button class="vt-ab naranja" @click="verDocs(record)"><FolderOpenOutlined /></a-button>
                        </a-badge></a-tooltip>
                        <a-tooltip v-if="puedeEditar" title="Editar"><a-button class="vt-ab azul" @click="abrir(record)"><EditOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="puedeEditar" :title="record.activo ? 'Desactivar' : 'Activar'">
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
                            <a-select v-model:value="f.tipo_persona" allow-clear placeholder="Todas" @change="buscar()" :options="[{ value: 'Física', label: 'Física' }, { value: 'Moral', label: 'Moral' }]" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="2">
                            <a-select v-model:value="f.tipo_negocio" allow-clear placeholder="Todos" @change="buscar()" :options="tiposNegocio.map((s) => ({ value: s.id, label: s.nombre }))" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="3">
                            <a-input v-model:value="f.ubicacion" placeholder="Filtrar" allow-clear @press-enter="buscar()" @change="(e: any) => !e.target.value && buscar()"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="4"></a-table-summary-cell>
                        <a-table-summary-cell :index="5"></a-table-summary-cell>
                        <a-table-summary-cell :index="6" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
    </div>

    <!-- Alta / edición en pestañas -->
    <a-modal v-model:open="modal" width="min(900px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #f08c00, #e0457b)"><TeamOutlined /></span>
                <div>{{ editando ? 'Editar prospecto' : 'Nuevo prospecto' }}<small>{{ editando ? editando.nombre : 'Captura los datos por secciones' }}</small></div>
            </div>
        </template>
        <a-form layout="vertical">
            <a-tabs v-model:active-key="pestana" class="vt-tabs-form">
                <a-tab-pane key="generales">
                    <template #tab><span class="vt-tab"><IdcardOutlined /> Datos generales <ExclamationCircleFilled v-if="conError('generales')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid vt-g3">
                        <a-form-item label="Tipo de persona" required>
                            <a-radio-group v-model:value="form.tipo_persona" button-style="solid">
                                <a-radio-button value="Moral"><BankOutlined /> Moral</a-radio-button><a-radio-button value="Física"><UserOutlined /> Física</a-radio-button>
                            </a-radio-group>
                        </a-form-item>
                        <a-form-item :label="form.tipo_persona === 'Moral' ? 'Razón social' : 'Nombre completo'" required class="span2" v-bind="err('nombre')">
                            <a-input v-model:value="form.nombre"><template #prefix><IdcardOutlined style="color: #0461d8" /></template></a-input>
                        </a-form-item>
                        <a-form-item label="Nombre comercial"><a-input v-model:value="form.nombre_comercial"><template #prefix><ShopOutlined style="color: #1597a5" /></template></a-input></a-form-item>
                        <a-form-item label="RFC" v-bind="err('rfc')"><a-input v-model:value="form.rfc" :maxlength="13" class="uppercase"><template #prefix><FileTextOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                        <a-form-item label="Tipo de negocio" v-bind="err('tipo_negocio_id')">
                            <a-select v-model:value="form.tipo_negocio_id" allow-clear placeholder="Selecciona" :options="tiposNegocio.map((s) => ({ value: s.id, label: s.nombre }))" />
                        </a-form-item>
                        <a-form-item :label="form.tipo_persona === 'Moral' ? 'Representante / contacto' : 'Persona de contacto'"><a-input v-model:value="form.contacto"><template #prefix><UserOutlined style="color: #0461d8" /></template></a-input></a-form-item>
                        <a-form-item label="Teléfono" v-bind="err('telefono')"><TelefonoInput v-model:value="form.telefono" /></a-form-item>
                        <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email" type="email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item label="Sucursal" required v-bind="err('sucursal_id')">
                            <a-select v-model:value="form.sucursal_id" placeholder="Selecciona" :options="sucursales.map((s) => ({ value: s.id, label: s.nombre }))" />
                        </a-form-item>
                    </div>
                </a-tab-pane>

                <a-tab-pane key="ubicacion">
                    <template #tab><span class="vt-tab"><EnvironmentOutlined /> Dirección y ubicación <ExclamationCircleFilled v-if="conError('ubicacion')" class="vt-tab-err" /></span></template>
                    <DireccionCampos :model-value="form" :errors="form.errors" requerida />
                    <div class="vt-ubic">
                        <div>
                            <a-form-item label="Coordenadas (Google Maps)" class="mb-2">
                                <div class="flex gap-2">
                                    <a-input-number v-model:value="form.latitud" placeholder="Latitud" class="flex-1" :step="0.0001" />
                                    <a-input-number v-model:value="form.longitud" placeholder="Longitud" class="flex-1" :step="0.0001" />
                                </div>
                            </a-form-item>
                            <a-button block class="mb-2" :loading="ubicando" @click="miUbicacion"><AimOutlined style="color: #1faf5c" /> Usar mi ubicación actual</a-button>
                            <a-input-search v-model:value="enlace" placeholder="Pega un enlace de Google Maps o coordenadas" @search="desdeEnlace">
                                <template #enterButton><a-button><LinkOutlined /></a-button></template>
                            </a-input-search>
                        </div>
                        <iframe v-if="mapa" :src="mapa" class="vt-mapa-form" loading="lazy" title="Vista previa de la ubicación"></iframe>
                        <div v-else class="vt-mapa-form vt-empty grid place-items-center">Captura la dirección para ver el mapa</div>
                    </div>
                </a-tab-pane>

                <a-tab-pane key="portal">
                    <template #tab><span class="vt-tab"><KeyOutlined /> Portal y notas <ExclamationCircleFilled v-if="conError('portal')" class="vt-tab-err" /></span></template>
                    <div class="vt-portal">
                        <a-checkbox v-model:checked="form.portal"><b><KeyOutlined style="color: #1faf5c" /> Dar acceso al portal de prospectos</b></a-checkbox>
                        <div class="vt-small vt-muted mb-3">Permite que el prospecto contacte a sus promotores a través de la plataforma.</div>
                        <div v-if="form.portal" class="vt-form-grid">
                            <a-form-item label="Correo de acceso" required v-bind="err('portal_email')"><a-input v-model:value="form.portal_email" type="email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                            <a-form-item :label="editando?.portal ? 'Nueva contraseña (opcional)' : 'Contraseña'" :required="!editando?.portal" v-bind="err('portal_password')">
                                <a-input-password v-model:value="form.portal_password" autocomplete="new-password" />
                            </a-form-item>
                        </div>
                    </div>
                    <a-form-item label="Notas" class="mt-4"><a-textarea v-model:value="form.notas" :auto-size="{ minRows: 4, maxRows: 8 }" /></a-form-item>
                    <p class="vt-small vt-muted"><StarOutlined style="color: #f0a202" /> Los convenios y comprobantes se cargan desde el botón de documentos de la lista.</p>
                </a-tab-pane>
            </a-tabs>
        </a-form>
        <template #footer>
            <PieModal tabla="prospectos" :registro="editando" :guardando="form.processing" @cancelar="modal = false" @guardar="guardar" />
        </template>
    </a-modal>

    <DocumentosModal v-model:open="docs.open" tipo="prospecto" :id="docs.id" :titulo="docs.titulo" @cambio="router.reload({ only: ['prospectos'] })" />
    <MapaModal v-model:open="mapaVer.open" :titulo="mapaVer.p?.nombre || ''" :direccion="mapaVer.p ? direccionDe(mapaVer.p) : ''" :latitud="mapaVer.p?.latitud" :longitud="mapaVer.p?.longitud" />
</template>

<style>
.vt-ubic {
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 14px;
    align-items: start;
}
.vt-mapa-form {
    width: 100%;
    height: 220px;
    border: 1px solid var(--vt-line);
    border-radius: 12px;
}
.vt-portal {
    background: #f3fbf6;
    border: 1px solid #cdeed9;
    border-radius: 12px;
    padding: 14px 16px 4px;
}
@media (max-width: 760px) {
    .vt-ubic {
        grid-template-columns: 1fr;
    }
}
</style>
