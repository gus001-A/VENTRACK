<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    ShopOutlined, PlusOutlined, EnvironmentOutlined, PhoneOutlined, GlobalOutlined, MailOutlined, FolderOpenOutlined, EditOutlined,
    FacebookOutlined, InstagramOutlined, WhatsAppOutlined, ShareAltOutlined, UserOutlined, TeamOutlined, StopOutlined,
    CheckCircleOutlined, InfoCircleOutlined, GiftOutlined, CrownOutlined, TagsOutlined, ExclamationCircleFilled,
    SearchOutlined, ClearOutlined, HistoryOutlined, SettingOutlined, FlagOutlined, ContactsOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import PieModal from '@/Components/PieModal.vue';
import KpiBar from '@/Components/KpiBar.vue';
import DocumentosModal from '@/Components/DocumentosModal.vue';
import DireccionCampos from '@/Components/DireccionCampos.vue';
import MapaModal from '@/Components/MapaModal.vue';
import TelefonoInput from '@/Components/TelefonoInput.vue';
import { iniciales, type Opcion } from '@/utils';
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import { confirmarEstado } from '@/confirmar';

const props = defineProps<{
    sucursales: any[];
    administradores: Opcion[];
    tiposNegocio: Opcion[];
    productos: Opcion[];
    puedeEditar: boolean;
}>();

// Tabla con filtros al pie (activas / inactivas)
const altoTabla = useAlturaTabla(450);
const f = reactive({ q: '', direccion: '', responsable: null as number | null, giro: null as number | null, ver: 'activas' });
const lista = computed(() => props.sucursales.filter((s) =>
    (f.ver === 'activas' ? s.activa : !s.activa)
    && (!f.q || s.nombre.toLowerCase().includes(f.q.toLowerCase()))
    && (!f.direccion || direccionDe(s).toLowerCase().includes(f.direccion.toLowerCase()))
    && (!f.responsable || s.responsable?.id === f.responsable)
    && (!f.giro || s.tipo_negocio_id === f.giro)));
const hayFiltros = computed(() => !!(f.q || f.direccion || f.responsable || f.giro));
const limpiarFiltros = () => Object.assign(f, { q: '', direccion: '', responsable: null, giro: null });
const columnas = [
    { title: 'Sucursal', key: 'nombre', icon: ShopOutlined, ellipsis: true },
    { title: 'Dirección', key: 'direccion', icon: EnvironmentOutlined, ellipsis: true },
    { title: 'Contacto', key: 'contacto', icon: ContactsOutlined, width: 190 },
    { title: 'Responsable', key: 'responsable', icon: CrownOutlined, width: 190 },
    { title: 'Equipo', key: 'equipo', icon: TeamOutlined, width: 140 },
    { title: 'Estado', key: 'estado', icon: FlagOutlined, width: 110, align: 'center' },
    { title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 190 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 150, align: 'center' },
];
const kpiItems = computed(() => [
    { key: 't', icon: ShopOutlined, valor: props.sucursales.length, label: 'Sucursales', color: '#1597a5' },
    { key: 'a', icon: CheckCircleOutlined, valor: props.sucursales.filter((s) => s.activa).length, label: 'Activas', color: '#1faf5c' },
    { key: 'u', icon: UserOutlined, valor: props.sucursales.reduce((t, s) => t + s.usuarios_count, 0), label: 'Usuarios asignados', color: '#3f51b5' },
    { key: 'p', icon: TeamOutlined, valor: props.sucursales.reduce((t, s) => t + s.prospectos_count, 0), label: 'Prospectos', color: '#f08c00' },
    { key: 'd', icon: FolderOpenOutlined, valor: props.sucursales.reduce((t, s) => t + s.documentos_count, 0), label: 'Documentos', color: '#7c4ddb' },
    { key: 'i', icon: StopOutlined, valor: props.sucursales.filter((s) => !s.activa).length, label: 'Inactivas', color: '#e5484d' },
]);

const modal = ref(false);
const pestana = ref('general');
const editando = ref<any>(null);
const form = useForm({
    nombre: '', tipo_negocio_id: null as number | null, descripcion: '', direccion: '', colonia: '', ciudad: '', estado: '',
    codigo_postal: '', telefono: '', email: '', sitio_web: '', facebook: '', instagram: '', whatsapp: '', otras_redes: '',
    responsable_id: null as number | null, producto_ids: [] as number[],
});
const CAMPOS: Record<string, string[]> = {
    general: ['nombre', 'tipo_negocio_id', 'responsable_id', 'descripcion', 'producto_ids'],
    direccion: ['direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal'],
    contacto: ['telefono', 'email', 'sitio_web', 'whatsapp', 'facebook', 'instagram', 'otras_redes'],
};
const conError = (tab: string) => CAMPOS[tab].some((k) => (form.errors as any)[k]);

function abrir(s: any = null) {
    editando.value = s;
    pestana.value = 'general';
    form.clearErrors();
    const texto = ['nombre', 'descripcion', 'direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal', 'telefono', 'email',
        'sitio_web', 'facebook', 'instagram', 'whatsapp', 'otras_redes'];
    form.defaults({
        ...Object.fromEntries(texto.map((k) => [k, s?.[k] ?? ''])),
        tipo_negocio_id: s?.tipo_negocio_id ?? null,
        responsable_id: s?.responsable?.id ?? (props.administradores.length === 1 ? props.administradores[0].id : null),
        producto_ids: s?.producto_ids ?? [],
    } as any);
    form.reset();
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
    if (editando.value) form.put(`/sucursales/${editando.value.id}`, opts);
    else form.post('/sucursales', opts);
}
async function alternar(s: any) {
    if (await confirmarEstado(s.activa, 'esta sucursal', s.nombre)) router.delete(`/sucursales/${s.id}`, { preserveScroll: true });
}

const docs = reactive({ open: false, id: null as number | null, titulo: '' });
const mapaVer = reactive({ open: false, s: null as any });
const url = (u: string) => (/^https?:\/\//.test(u) ? u : 'https://' + u);
const sinProtocolo = (u: string) => u.replace(/^https?:\/\//, '');
const direccionDe = (s: any) => [s.direccion, s.colonia, s.ciudad, s.estado, s.codigo_postal].filter(Boolean).join(', ');
const fbLink = (v: string) => url(v.startsWith('http') ? v : 'facebook.com/' + v);
const igLink = (i: string) => 'https://instagram.com/' + i.replace('@', '');
const waLink = (w: string) => 'https://wa.me/' + w.replace(/\D/g, '');
const err = (k: keyof typeof form.errors) => ({ 'validate-status': form.errors[k] ? 'error' : '', help: form.errors[k] });
</script>

<template>
    <PageHeader title="Sucursales" subtitle="Puntos de operación, sus datos de contacto, productos y responsable" :icon="ShopOutlined">
        <template #extra>
            <a-button v-if="puedeEditar" type="primary" size="large" @click="abrir()"><PlusOutlined /> Nueva sucursal</a-button>
        </template>
    </PageHeader>

    <KpiBar :items="kpiItems" />

    <div class="vt-toolbar">
        <a-segmented v-model:value="f.ver" :options="[{ label: 'Activas', value: 'activas' }, { label: 'Inactivas', value: 'inactivas' }]" />
    </div>

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" class="vt-table" :columns="columnas" :data-source="lista" row-key="id" :pagination="false"
            :locale="{ emptyText: 'No hay sucursales con estos filtros.' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'nombre'">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="vt-suc-ic"><ShopOutlined /></span>
                        <div class="min-w-0">
                            <b class="block truncate">{{ record.nombre }}</b>
                            <span v-if="record.giro" class="vt-small vt-muted block truncate"><TagsOutlined style="color: #7c4ddb" /> {{ record.giro }}</span>
                            <a-tooltip v-if="record.productos.length" :title="record.productos.map((p: any) => p.nombre).join(', ')">
                                <span class="vt-small vt-muted block truncate"><GiftOutlined style="color: #e0457b" /> {{ record.productos.length }} producto(s)</span>
                            </a-tooltip>
                        </div>
                    </div>
                </template>
                <template v-else-if="column.key === 'direccion'">
                    <a class="vt-suc-dir" @click="Object.assign(mapaVer, { open: true, s: record })"><EnvironmentOutlined style="color: #f08c00" /> {{ direccionDe(record) }}</a>
                </template>
                <template v-else-if="column.key === 'contacto'">
                    <div class="vt-suc-contacto">
                        <a-tooltip v-if="record.telefono" :title="record.telefono"><a-button class="vt-ab verde" :href="'tel:' + record.telefono"><PhoneOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.whatsapp" title="WhatsApp"><a-button class="vt-ab verde" :href="waLink(record.whatsapp)" target="_blank"><WhatsAppOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.email" :title="record.email"><a-button class="vt-ab naranja" :href="'mailto:' + record.email"><MailOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.sitio_web" :title="sinProtocolo(record.sitio_web)"><a-button class="vt-ab azul" :href="url(record.sitio_web)" target="_blank"><GlobalOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.facebook" title="Facebook"><a-button class="vt-ab azul" :href="fbLink(record.facebook)" target="_blank"><FacebookOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.instagram" title="Instagram"><a-button class="vt-ab rojo" :href="igLink(record.instagram)" target="_blank"><InstagramOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="record.otras_redes" :title="record.otras_redes"><a-button class="vt-ab morado"><ShareAltOutlined /></a-button></a-tooltip>
                        <span v-if="!record.telefono && !record.email && !record.whatsapp && !record.sitio_web" class="vt-muted">—</span>
                    </div>
                </template>
                <template v-else-if="column.key === 'responsable'">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="vt-av vt-av-sm vt-av-grad">{{ iniciales(record.responsable?.nombre || '?') }}</span>
                        <span class="truncate">{{ record.responsable?.nombre || 'Sin responsable' }}</span>
                    </div>
                </template>
                <template v-else-if="column.key === 'equipo'">
                    <div class="vt-small vt-nowrap" style="color: #3f51b5"><UserOutlined /> {{ record.usuarios_count }} usuarios</div>
                    <div class="vt-small vt-nowrap" style="color: #f08c00"><TeamOutlined /> {{ record.prospectos_count }} prospectos</div>
                </template>
                <template v-else-if="column.key === 'estado'">
                    <span class="vt-tag solido" :style="{ background: record.activa ? '#1faf5c' : '#94a3b8' }">{{ record.activa ? 'Activa' : 'Inactiva' }}</span>
                </template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <div class="vt-acciones-fila">
                        <a-tooltip title="Documentos"><a-badge :count="record.documentos_count" size="small" color="#f08c00" :offset="[-2, 2]">
                            <a-button class="vt-ab naranja" @click="Object.assign(docs, { open: true, id: record.id, titulo: record.nombre })"><FolderOpenOutlined /></a-button>
                        </a-badge></a-tooltip>
                        <a-tooltip v-if="puedeEditar" title="Editar"><a-button class="vt-ab azul" @click="abrir(record)"><EditOutlined /></a-button></a-tooltip>
                        <a-tooltip v-if="puedeEditar" :title="record.activa ? 'Desactivar' : 'Activar'">
                            <a-button class="vt-ab" :class="record.activa ? 'rojo' : 'verde'" @click="alternar(record)"><StopOutlined v-if="record.activa" /><CheckCircleOutlined v-else /></a-button>
                        </a-tooltip>
                    </div>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0"><a-input v-model:value="f.q" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell :index="1"><a-input v-model:value="f.direccion" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell :index="2"></a-table-summary-cell>
                        <a-table-summary-cell :index="3">
                            <a-select v-model:value="f.responsable" allow-clear placeholder="Todos" :options="administradores.map((a) => ({ value: a.id, label: a.nombre }))" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="4"></a-table-summary-cell>
                        <a-table-summary-cell :index="5"></a-table-summary-cell>
                        <a-table-summary-cell :index="6"></a-table-summary-cell>
                        <a-table-summary-cell :index="7" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
        <div class="vt-total-pie">Mostrando {{ lista.length }} de {{ sucursales.length }} sucursales</div>
    </div>

    <a-modal v-model:open="modal" width="min(900px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #1597a5, #2dcb6a)"><ShopOutlined /></span>
                <div>{{ editando ? 'Editar sucursal' : 'Nueva sucursal' }}<small>{{ editando ? editando.nombre : 'Captura los datos por secciones' }}</small></div>
            </div>
        </template>
        <a-form layout="vertical">
            <a-tabs v-model:active-key="pestana" class="vt-tabs-form">
                <a-tab-pane key="general">
                    <template #tab><span class="vt-tab"><InfoCircleOutlined /> Información general <ExclamationCircleFilled v-if="conError('general')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid vt-g3">
                        <a-form-item label="Nombre comercial" required v-bind="err('nombre')"><a-input v-model:value="form.nombre"><template #prefix><ShopOutlined style="color: #1597a5" /></template></a-input></a-form-item>
                        <a-form-item label="Tipo de negocio"><a-select v-model:value="form.tipo_negocio_id" allow-clear placeholder="Selecciona" :options="tiposNegocio.map((t) => ({ value: t.id, label: t.nombre }))" /></a-form-item>
                        <a-form-item label="Responsable (administrador)" required v-bind="err('responsable_id')">
                            <a-select v-model:value="form.responsable_id" placeholder="Selecciona" :options="administradores.map((a) => ({ value: a.id, label: a.nombre }))" />
                        </a-form-item>
                        <a-form-item label="Giro, descripción, productos o servicios que vende" class="full"><a-textarea v-model:value="form.descripcion" :auto-size="{ minRows: 3, maxRows: 6 }" /></a-form-item>
                        <a-form-item label="Catálogo de productos/servicios que comercializa" class="full">
                            <a-select v-model:value="form.producto_ids" mode="multiple" allow-clear option-filter-prop="label" placeholder="Selecciona" :options="productos.map((p) => ({ value: p.id, label: p.nombre }))" />
                        </a-form-item>
                    </div>
                </a-tab-pane>
                <a-tab-pane key="direccion">
                    <template #tab><span class="vt-tab"><EnvironmentOutlined /> Dirección <ExclamationCircleFilled v-if="conError('direccion')" class="vt-tab-err" /></span></template>
                    <DireccionCampos :model-value="form" :errors="form.errors" requerida />
                </a-tab-pane>
                <a-tab-pane key="contacto">
                    <template #tab><span class="vt-tab"><PhoneOutlined /> Contacto y redes <ExclamationCircleFilled v-if="conError('contacto')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid vt-g3">
                        <a-form-item label="Teléfono" v-bind="err('telefono')"><TelefonoInput v-model:value="form.telefono" /></a-form-item>
                        <a-form-item label="WhatsApp" v-bind="err('whatsapp')"><TelefonoInput v-model:value="form.whatsapp" whatsapp /></a-form-item>
                        <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item label="Página web"><a-input v-model:value="form.sitio_web"><template #prefix><GlobalOutlined style="color: #0461d8" /></template></a-input></a-form-item>
                        <a-form-item label="Facebook"><a-input v-model:value="form.facebook"><template #prefix><FacebookOutlined style="color: #1877f2" /></template></a-input></a-form-item>
                        <a-form-item label="Instagram"><a-input v-model:value="form.instagram"><template #prefix><InstagramOutlined style="color: #d62976" /></template></a-input></a-form-item>
                        <a-form-item label="Otras redes" class="full"><a-input v-model:value="form.otras_redes" placeholder="TikTok, LinkedIn…"><template #prefix><ShareAltOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                    </div>
                </a-tab-pane>
            </a-tabs>
        </a-form>
        <template #footer>
            <PieModal tabla="sucursales" :registro="editando" :guardando="form.processing" @cancelar="modal = false" @guardar="guardar" />
        </template>
    </a-modal>

    <DocumentosModal v-model:open="docs.open" tipo="sucursal" :id="docs.id" :titulo="docs.titulo" @cambio="router.reload({ only: ['sucursales'] })" />
    <MapaModal v-model:open="mapaVer.open" :titulo="mapaVer.s?.nombre || ''" :direccion="mapaVer.s ? direccionDe(mapaVer.s) : ''" />
</template>

<style>
.vt-suc-ic {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 17px;
    color: #fff;
    background: linear-gradient(135deg, #1597a5, #2dcb6a);
    flex: none;
}
.vt-suc-dir {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-suc-contacto {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
</style>
