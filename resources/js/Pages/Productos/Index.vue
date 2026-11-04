<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    GiftOutlined, PlusOutlined, SearchOutlined, WhatsAppOutlined, MailOutlined, EditOutlined, FilePptOutlined, LinkOutlined, StopOutlined, CheckCircleOutlined, SendOutlined, CopyOutlined, MinusCircleOutlined, FilterOutlined, ShopOutlined, DollarOutlined, InfoCircleOutlined, AppstoreOutlined, ToolOutlined, InboxOutlined, TagsOutlined, HistoryOutlined, SettingOutlined, ClearOutlined, FileTextOutlined, ExclamationCircleFilled,
} from '@ant-design/icons-vue';
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import PieModal from '@/Components/PieModal.vue';
import KpiBar from '@/Components/KpiBar.vue';
import SucursalSelect from '@/Components/SucursalSelect.vue';
import { confirmarEstado } from '@/confirmar';
import DocumentosModal from '@/Components/DocumentosModal.vue';
import { dinero, limpiar, type Opcion } from '@/utils';

const props = defineProps<{
    productos: any[];
    filtros: Record<string, any>;
    areas: string[];
    sucursales: Opcion[];
    tiposDocumento: Opcion[];
    puedeEditar: boolean;
    puedeCompartir: boolean;
}>();

// Filtros (alineados a cada columna de la tabla)
const f = reactive({ q: '', tipo: null as string | null, area: null as string | null, presentaciones: null as string | null });
const ver = ref<'activos' | 'inactivos'>('activos');
const lista = computed(() => props.productos.filter((p) =>
    (ver.value === 'activos' ? p.activo : !p.activo)
    && (!f.q || (p.nombre + ' ' + (p.descripcion || '')).toLowerCase().includes(f.q.toLowerCase()))
    && (!f.tipo || p.tipo === f.tipo)
    && (!f.area || p.area === f.area)
    && (!f.presentaciones || (f.presentaciones === 'con' ? p.presentaciones.length : !p.presentaciones.length))));
const hayFiltros = computed(() => !!(f.q || f.tipo || f.area || f.presentaciones));
const altoTabla = useAlturaTabla(490);
const columnas = [
    { title: 'Producto / servicio', key: 'nombre', icon: GiftOutlined, ellipsis: true },
    { title: 'Tipo', key: 'tipo', icon: TagsOutlined, width: 125 },
    { title: 'Área', key: 'area', icon: AppstoreOutlined, width: 140 },
    { title: 'Costo', key: 'costo', icon: DollarOutlined, width: 150, align: 'right' },
    { title: 'Presentaciones', key: 'presentaciones', icon: FilePptOutlined, width: 150, align: 'center' },
    { title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 190 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 230, align: 'center' },
];
const kpiItems = computed(() => [
    { key: 't', icon: GiftOutlined, valor: props.productos.filter((p) => p.activo).length, label: 'Activos', color: '#e0457b' },
    { key: 'p', icon: InboxOutlined, valor: props.productos.filter((p) => p.tipo === 'Producto').length, label: 'Productos', color: '#f08c00' },
    { key: 's', icon: ToolOutlined, valor: props.productos.filter((p) => p.tipo === 'Servicio').length, label: 'Servicios', color: '#1597a5' },
    { key: 'a', icon: AppstoreOutlined, valor: props.areas.length, label: 'Áreas', color: '#7c4ddb' },
    { key: 'f', icon: FilePptOutlined, valor: props.productos.reduce((t, p) => t + p.presentaciones.length, 0), label: 'Presentaciones', color: '#0461d8' },
]);

// ---------- Alta / edición ----------
const modal = ref(false);
const editando = ref<any>(null);
const form = useForm({
    nombre: '', tipo: 'Servicio', area: '', costo: 0 as number | null, unidad: '', descripcion: '', informacion: '',
    links: [] as { titulo: string; url: string }[], sucursal_ids: [] as number[],
});
function abrir(p: any = null) {
    editando.value = p;
    pestana.value = 'general';
    form.clearErrors();
    form.defaults({
        nombre: p?.nombre ?? '', tipo: p?.tipo ?? 'Servicio', area: p?.area ?? '', costo: p?.costo ?? 0, unidad: p?.unidad ?? '',
        descripcion: p?.descripcion ?? '', informacion: p?.informacion ?? '', links: (p?.links ?? []).map((l: any) => ({ ...l })),
        sucursal_ids: p?.sucursal_ids ?? [],
    });
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
    if (editando.value) form.put(`/productos/${editando.value.id}`, opts);
    else form.post('/productos', opts);
}
async function alternar(p: any) {
    if (await confirmarEstado(p.activo, 'este producto o servicio', p.nombre)) router.delete(`/productos/${p.id}`, { preserveScroll: true });
}

// ---------- Difusión: WhatsApp / correo ----------
const envio = reactive({ open: false, canal: 'WhatsApp' as 'WhatsApp' | 'Correo', destino: '', producto: null as any, incluir: [] as number[] });
function compartir(p: any, canal: typeof envio.canal) {
    Object.assign(envio, { open: true, canal, destino: '', producto: p, incluir: p.presentaciones.map((d: any) => d.id) });
}
const textoFicha = computed(() => {
    const p = envio.producto;
    if (!p) return '';
    const docs = p.presentaciones.filter((d: any) => envio.incluir.includes(d.id));
    return [
        `*${p.nombre}*`,
        `${p.tipo} · ${p.area}`,
        `Costo: ${dinero(p.costo)}${p.unidad ? ' ' + p.unidad : ''}`,
        p.descripcion,
        p.informacion,
        ...docs.map((d: any) => `📎 ${d.tipo || 'Presentación'}: ${d.publico}`),
        ...(p.links || []).map((l: any) => `🔗 ${l.titulo || 'Más información'}: ${l.url}`),
    ].filter(Boolean).join('\n');
});
function enviar() {
    const p = envio.producto;
    const txt = textoFicha.value;
    let url = '';
    if (envio.canal === 'WhatsApp') {
        const tel = envio.destino.replace(/\D/g, '');
        if (tel && tel.length < 10) return message.warning('Escribe el número con lada (10 dígitos o más)');
        url = (tel ? `https://wa.me/${tel.length === 10 ? '52' + tel : tel}` : 'https://wa.me/') + '?text=' + encodeURIComponent(txt);
    } else {
        url = `mailto:${envio.destino}?subject=${encodeURIComponent(p.nombre)}&body=${encodeURIComponent(txt.replace(/\*/g, ''))}`;
    }
    router.post(`/productos/${p.id}/compartir`, { canal: envio.canal, destino: envio.destino }, { preserveScroll: true, preserveState: true });
    window.open(url, '_blank', 'noopener');
    envio.open = false;
}
async function copiar() {
    await navigator.clipboard.writeText(textoFicha.value);
    message.success('Ficha copiada');
}

const docs = reactive({ open: false, id: null as number | null, titulo: '' });
const miles = (v: any) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const sinComas = (v: any) => String(v ?? '').replace(/,/g, '');
const pestana = ref('general');
const CAMPOS: Record<string, string[]> = { general: ['nombre', 'tipo', 'area', 'costo', 'unidad', 'sucursal_ids'], detalle: ['descripcion', 'informacion'], enlaces: ['links'] };
const conError = (tab: string) => Object.keys(form.errors).some((k) => CAMPOS[tab].some((c) => k === c || k.startsWith(c + '.')));
const linkErr = (i: number) => ((form.errors as any)['links.' + i + '.url'] ? 'error' : '');
const err = (k: string) => ({ 'validate-status': (form.errors as any)[k] ? 'error' : '', help: (form.errors as any)[k] });
</script>

<template>
    <PageHeader title="Productos y servicios" subtitle="Envía fichas y presentaciones por WhatsApp o correo" :icon="GiftOutlined">
        <template #extra>
            <a-button v-if="puedeEditar" type="primary" size="large" @click="abrir()"><PlusOutlined /> Nuevo producto</a-button>
        </template>
    </PageHeader>

    <div class="vt-toolbar">
        <SucursalSelect />
        <a-segmented v-if="puedeEditar" v-model:value="ver" :options="[{ label: 'Activos', value: 'activos' }, { label: 'Inactivos', value: 'inactivos' }]" />
    </div>

    <KpiBar :items="kpiItems" />

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" :columns="columnas" :data-source="lista" row-key="id" :pagination="false"
            :row-class-name="(r: any) => (r.activo ? '' : 'vt-inactivo')" :locale="{ emptyText: 'No hay productos o servicios con estos filtros.' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'nombre'">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="vt-prod-ic grande" :class="record.tipo"><InboxOutlined v-if="record.tipo === 'Producto'" /><ToolOutlined v-else /></span>
                        <div class="min-w-0">
                            <b class="block truncate">{{ record.nombre }}</b>
                            <div class="vt-small vt-muted truncate">{{ record.descripcion || 'Sin descripción' }}</div>
                            <div v-if="record.links.length" class="vt-small truncate">
                                <a v-for="(l, i) in record.links" :key="i" :href="l.url" target="_blank" rel="noopener" class="mr-2"><LinkOutlined /> {{ l.titulo || 'Enlace' }}</a>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else-if="column.key === 'tipo'">
                    <span class="vt-tag solido" :style="{ background: record.tipo === 'Producto' ? '#f08c00' : '#1597a5' }">
                        <InboxOutlined v-if="record.tipo === 'Producto'" /><ToolOutlined v-else /> {{ record.tipo }}
                    </span>
                </template>
                <template v-else-if="column.key === 'area'"><a-tag color="purple"><AppstoreOutlined /> {{ record.area }}</a-tag></template>
                <template v-else-if="column.key === 'costo'">
                    <b class="vt-costo">{{ dinero(record.costo) }}</b>
                    <div v-if="record.unidad" class="vt-small vt-muted">{{ record.unidad }}</div>
                </template>
                <template v-else-if="column.key === 'presentaciones'">
                    <a-button class="vt-btn-pres" @click="Object.assign(docs, { open: true, id: record.id, titulo: record.nombre })">
                        <FilePptOutlined /> {{ record.presentaciones.length }}
                    </a-button>
                </template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <div class="vt-acciones-fila">
                        <template v-if="puedeCompartir">
                            <a-tooltip title="Enviar por WhatsApp"><a-button class="vt-ab verde" @click="compartir(record, 'WhatsApp')"><WhatsAppOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Enviar por correo"><a-button class="vt-ab naranja" @click="compartir(record, 'Correo')"><MailOutlined /></a-button></a-tooltip>
                        </template>
                        <template v-if="puedeEditar">
                            <a-tooltip title="Editar"><a-button class="vt-ab teal" @click="abrir(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip :title="record.activo ? 'Desactivar' : 'Activar'">
                                <a-button class="vt-ab" :class="record.activo ? 'rojo' : 'verde'" @click="alternar(record)"><StopOutlined v-if="record.activo" /><CheckCircleOutlined v-else /></a-button>
                            </a-tooltip>
                        </template>
                    </div>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0"><a-input v-model:value="f.q" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell :index="1"><a-select v-model:value="f.tipo" allow-clear placeholder="Todos" :options="[{ value: 'Producto', label: 'Producto' }, { value: 'Servicio', label: 'Servicio' }]" /></a-table-summary-cell>
                        <a-table-summary-cell :index="2"><a-select v-model:value="f.area" allow-clear placeholder="Todas" :options="areas.map((a) => ({ value: a, label: a }))" /></a-table-summary-cell>
                        <a-table-summary-cell :index="3"></a-table-summary-cell>
                        <a-table-summary-cell :index="4"><a-select v-model:value="f.presentaciones" allow-clear placeholder="Todos" :options="[{ value: 'con', label: 'Con archivos' }, { value: 'sin', label: 'Sin archivos' }]" /></a-table-summary-cell>
                        <a-table-summary-cell :index="5"></a-table-summary-cell>
                        <a-table-summary-cell :index="6" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="Object.assign(f, { q: '', tipo: null, area: null, presentaciones: null })"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
        <div class="vt-total-pie">Mostrando {{ lista.length }} de {{ productos.length }} productos y servicios</div>
    </div>

    <!-- Envío de ficha -->
    <a-modal v-model:open="envio.open" width="min(640px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" :style="{ background: envio.canal === 'WhatsApp' ? '#25d366' : '#e0457b' }">
                    <WhatsAppOutlined v-if="envio.canal === 'WhatsApp'" /><MailOutlined v-else />
                </span>
                <div>Enviar por {{ envio.canal }}<small>{{ envio.producto?.nombre }}</small></div>
            </div>
        </template>
        <a-form layout="vertical" class="mt-3">
            <a-form-item :label="envio.canal === 'Correo' ? 'Correo del cliente' : 'WhatsApp del cliente (con lada)'"
                :extra="envio.canal === 'WhatsApp' ? 'Déjalo vacío para elegir el contacto en WhatsApp.' : ''">
                <a-input v-model:value="envio.destino" :placeholder="envio.canal === 'Correo' ? 'cliente@correo.com' : '777 123 4567'" />
            </a-form-item>
            <a-form-item v-if="envio.producto?.presentaciones.length" label="Adjuntar presentaciones (enlace)">
                <a-checkbox-group v-model:value="envio.incluir" class="flex! flex-col gap-1"
                    :options="envio.producto.presentaciones.map((d: any) => ({ value: d.id, label: `${d.tipo || 'Presentación'} · ${d.nombre}` }))" />
            </a-form-item>
            <a-form-item label="Vista previa del mensaje">
                <pre class="vt-preview">{{ textoFicha }}</pre>
            </a-form-item>
        </a-form>
        <template #footer>
            <a-button @click="copiar"><CopyOutlined /> Copiar texto</a-button>
            <a-button type="primary" @click="enviar"><SendOutlined /> Enviar</a-button>
        </template>
    </a-modal>

    <!-- Alta / edición -->
    <a-modal v-model:open="modal" width="min(780px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #e0457b, #7c4ddb)"><GiftOutlined /></span>
                <div>{{ editando ? 'Editar producto o servicio' : 'Nuevo producto o servicio' }}<small>Descripción, costos, información y enlaces</small></div>
            </div>
        </template>
        <a-form layout="vertical">
            <a-tabs v-model:active-key="pestana" class="vt-tabs-form">
                <a-tab-pane key="general">
                    <template #tab><span class="vt-tab"><InfoCircleOutlined /> Datos comerciales <ExclamationCircleFilled v-if="conError('general')" class="vt-tab-err" /></span></template>
                    <div class="vt-form-grid">
                        <a-form-item label="Nombre" required class="full" v-bind="err('nombre')"><a-input v-model:value="form.nombre" /></a-form-item>
                        <a-form-item label="Tipo" required>
                            <a-radio-group v-model:value="form.tipo" button-style="solid" class="vt-radio-full">
                                <a-radio-button value="Servicio">Servicio</a-radio-button><a-radio-button value="Producto">Producto</a-radio-button>
                            </a-radio-group>
                        </a-form-item>
                        <a-form-item label="Área o departamento" required v-bind="err('area')">
                            <a-auto-complete v-model:value="form.area" :options="areas.map((a) => ({ value: a }))" placeholder="Ej. Servicios, Seguridad…" />
                        </a-form-item>
                        <a-form-item label="Costo" required v-bind="err('costo')">
                            <a-input-number v-model:value="form.costo" :min="0" :step="100" style="width: 100%" :formatter="miles" :parser="sinComas">
                                <template #prefix><DollarOutlined style="color: #1faf5c" /></template>
                            </a-input-number>
                        </a-form-item>
                        <a-form-item label="Unidad"><a-input v-model:value="form.unidad" placeholder="por mes, por visita, pieza…" /></a-form-item>
                        <a-form-item label="Sucursales que lo ofrecen" class="full" extra="Déjalo vacío para que esté disponible en todas.">
                            <a-select v-model:value="form.sucursal_ids" mode="multiple" allow-clear placeholder="Todas las sucursales" :options="sucursales.map((s) => ({ value: s.id, label: s.nombre }))">
                                <template #suffixIcon><ShopOutlined /></template>
                            </a-select>
                        </a-form-item>
                    </div>
                </a-tab-pane>
                <a-tab-pane key="detalle">
                    <template #tab><span class="vt-tab"><FileTextOutlined /> Descripción <ExclamationCircleFilled v-if="conError('detalle')" class="vt-tab-err" /></span></template>
                    <a-form-item label="Descripción breve" extra="Se muestra en la tabla y en la ficha que se comparte.">
                        <a-textarea v-model:value="form.descripcion" :auto-size="{ minRows: 2, maxRows: 4 }" :maxlength="500" show-count />
                    </a-form-item>
                    <a-form-item label="Información detallada"><a-textarea v-model:value="form.informacion" :auto-size="{ minRows: 4, maxRows: 8 }" /></a-form-item>
                </a-tab-pane>
                <a-tab-pane key="enlaces">
                    <template #tab><span class="vt-tab"><LinkOutlined /> Enlaces <ExclamationCircleFilled v-if="conError('enlaces')" class="vt-tab-err" /></span></template>
                    <p class="vt-small vt-muted mb-3">Páginas de apoyo que se envían junto con la ficha (catálogos en línea, videos, sitio web…).</p>
                    <div v-for="(l, i) in form.links" :key="i" class="vt-link-fila">
                        <a-input v-model:value="l.titulo" placeholder="Título" />
                        <a-input v-model:value="l.url" :status="linkErr(i)" placeholder="Dirección del enlace (https)" />
                        <a-tooltip title="Quitar"><a-button class="vt-ab rojo" @click="form.links.splice(i, 1)"><MinusCircleOutlined /></a-button></a-tooltip>
                    </div>
                    <a-button type="dashed" block @click="form.links.push({ titulo: '', url: '' })"><PlusOutlined /> Agregar enlace</a-button>
                    <p class="vt-small vt-muted mt-4 mb-0"><FilePptOutlined style="color: #d35230" /> Volantes, fichas técnicas y brochures se cargan desde “Presentaciones” en la tabla.</p>
                </a-tab-pane>
            </a-tabs>
        </a-form>
        <template #footer>
            <PieModal tabla="productos" :registro="editando" :guardando="form.processing" @cancelar="modal = false" @guardar="guardar" />
        </template>
    </a-modal>

    <DocumentosModal v-model:open="docs.open" tipo="producto" :id="docs.id" :titulo="docs.titulo" @cambio="router.reload({ only: ['productos'] })" />
</template>

<style>
.vt-prod-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 14px;
}
.vt-prod {
    display: flex;
    flex-direction: column;
}
.vt-prod-ic {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: inline-grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(135deg, #1597a5, #2dcb6a);
}
.vt-prod-ic.grande {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    font-size: 18px;
    flex: none;
}
.vt-costo {
    font: 800 16px 'Bricolage Grotesque', sans-serif;
    color: var(--vt-green-dark);
}
.ant-btn.vt-btn-pres {
    border-color: #f3c7b8;
    color: #d35230;
    background: #fff4f0;
    font-weight: 700;
}
.vt-prod-ic.Producto {
    background: linear-gradient(135deg, #e0457b, #f08c00);
}
.vt-prod.inactivo {
    opacity: 0.6;
}
.vt-precio {
    font: 700 22px 'Bricolage Grotesque', sans-serif;
    background: var(--vt-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-top: auto;
}
.vt-precio small {
    font: 500 13px Figtree, sans-serif;
    color: var(--vt-muted);
    -webkit-text-fill-color: var(--vt-muted);
}
.vt-wa {
    color: #fff !important;
    background: #25d366 !important;
    border-color: #25d366 !important;
}
.vt-preview {
    white-space: pre-wrap;
    font: 13px/1.5 Figtree, sans-serif;
    background: #f0faf3;
    border: 1px solid #cdeed9;
    border-radius: 10px;
    padding: 10px 12px;
    margin: 0;
    max-height: 240px;
    overflow: auto;
}
</style>
