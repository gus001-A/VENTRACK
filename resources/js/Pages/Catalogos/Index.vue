<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    UnorderedListOutlined, PlusOutlined, EditOutlined, FormOutlined, StopOutlined, CheckCircleOutlined, ShopOutlined, FileTextOutlined,
    SearchOutlined, LinkOutlined, InfoCircleOutlined, ClearOutlined, HistoryOutlined, SettingOutlined, TagOutlined, FlagOutlined, ThunderboltOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import PieModal from '@/Components/PieModal.vue';
import { confirmarEstado } from '@/confirmar';
import { useAlturaTabla } from '@/composables/useAlturaTabla';
import { ESTATUS, ESTATUS_INFO, ESTATUS_DOT, type Estatus } from '@/utils';

const props = defineProps<{
    tipo: string;
    tipos: Record<string, string>;
    conteos: Record<string, number>;
    registros: any[];
}>();

const INFO: Record<string, { icon: any; color: string; singular: string }> = {
    tipo_negocio: { icon: ShopOutlined, color: '#7c4ddb', singular: 'tipo de negocio' },
    tipo_documento: { icon: FileTextOutlined, color: '#f08c00', singular: 'tipo de documento' },
    formato_observacion: { icon: FormOutlined, color: '#1faf5c', singular: 'formato de observación' },
};
const esFormato = computed(() => props.tipo === 'formato_observacion');
const cambiar = (t: string) => router.get('/catalogos', { tipo: t }, { preserveState: false, preserveScroll: true });
const altoTabla = useAlturaTabla(430);

const f = reactive({ q: '', nota: '', uso: null as string | null, ver: 'activos' });
const lista = computed(() => props.registros.filter((r) =>
    (f.ver === 'activos' ? r.activo : !r.activo)
    && (!f.q || r.nombre.toLowerCase().includes(f.q.toLowerCase()))
    && (!f.nota || (r.nota || '').toLowerCase().includes(f.nota.toLowerCase()))
    && (!f.uso || (f.uso === 'si' ? r.usos > 0 : !r.usos))));
const hayFiltros = computed(() => !!(f.q || f.nota || f.uso));

const modal = ref(false);
const editando = ref<any>(null);
const form = useForm({ tipo: props.tipo, nombre: '', nota: '', plantilla: '', etapa: null as string | null });
function abrir(r: any = null) {
    editando.value = r;
    form.clearErrors();
    form.defaults({ tipo: props.tipo, nombre: r?.nombre ?? '', nota: r?.nota ?? '', plantilla: r?.plantilla ?? '', etapa: r?.etapa ?? null });
    form.reset();
    modal.value = true;
}
function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => (modal.value = false) };
    if (editando.value) form.put(`/catalogos/${editando.value.id}`, opts);
    else form.post('/catalogos', opts);
}
async function alternar(r: any) {
    if (await confirmarEstado(r.activo, 'este registro', r.nombre)) router.delete(`/catalogos/${r.id}`, { preserveScroll: true });
}

const ETAPAS = ESTATUS.filter((e) => e !== 'supervisada').map((e) => ({ value: e, label: ESTATUS_INFO[e].label }));
const usadaPor = (etapa: string | null) => props.registros.find((r) => r.etapa === etapa && r.id !== editando.value?.id && r.activo);

const columnas = computed(() => [
    { title: 'Nombre', key: 'nombre', icon: TagOutlined, width: 240 },
    { title: 'Nota descriptiva', key: 'nota', icon: InfoCircleOutlined, ellipsis: true },
    ...(esFormato.value ? [{ title: 'Plantilla', key: 'plantilla', icon: FormOutlined, width: 140 }, { title: 'Se carga en', key: 'etapa', icon: ThunderboltOutlined, width: 160 }] : []),
    { title: 'En uso', key: 'usos', icon: LinkOutlined, width: 120, align: 'center' },
    { title: 'Estado', key: 'estado', icon: FlagOutlined, width: 120, align: 'center' },
    { title: 'Registrado por', key: 'traza', icon: HistoryOutlined, width: 200 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 120, align: 'center' },
]);
</script>

<template>
    <PageHeader title="Catálogos" subtitle="Tipos de negocio, tipos de documento y formatos de observación, con nota descriptiva por registro" :icon="UnorderedListOutlined">
        <template #extra>
            <a-button type="primary" size="large" @click="abrir()"><PlusOutlined /> Agregar {{ INFO[tipo].singular }}</a-button>
        </template>
    </PageHeader>

    <div class="vt-toolbar">
        <div class="vt-cat-tabs">
            <button v-for="(label, key) in tipos" :key="key" class="vt-cat-tab" :class="{ on: tipo === key }" :style="{ '--c': INFO[key].color }" @click="cambiar(key as string)">
                <span class="vt-cat-tab-ic"><component :is="INFO[key].icon" /></span>
                <span>{{ label }}</span>
                <b>{{ conteos[key] || 0 }}</b>
            </button>
        </div>
        <a-segmented v-model:value="f.ver" :options="[{ label: 'Activos', value: 'activos' }, { label: 'Inactivos', value: 'inactivos' }]" />
    </div>

    <div class="vt-card" style="padding: 0; overflow: hidden">
        <a-table table-layout="fixed" :scroll="{ y: altoTabla }" :columns="columnas" :data-source="lista" row-key="id" :pagination="false"
            :row-class-name="(r: any) => (r.activo ? '' : 'vt-inactivo')" :locale="{ emptyText: 'Sin registros' }">
            <template #headerCell="{ column }">
                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
            </template>
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'nombre'">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="vt-cat-ic" :style="{ background: INFO[tipo].color + '1a', color: INFO[tipo].color }"><component :is="INFO[tipo].icon" /></span>
                        <b class="truncate">{{ record.nombre }}</b>
                    </div>
                </template>
                <template v-else-if="column.key === 'nota'"><span :class="record.nota ? '' : 'vt-muted'">{{ record.nota || 'Sin nota' }}</span></template>
                <template v-else-if="column.key === 'plantilla'">
                    <a-tooltip v-if="record.plantilla" :title="record.plantilla" overlay-class-name="vt-pre-tip"><a-tag color="green"><FormOutlined /> Prediseñado</a-tag></a-tooltip>
                    <span v-else class="vt-muted">—</span>
                </template>
                <template v-else-if="column.key === 'etapa'">
                    <span v-if="record.etapa" class="vt-tag solido" :style="{ background: ESTATUS_DOT[record.etapa as Estatus] }">{{ ESTATUS_INFO[record.etapa as Estatus].label }}</span>
                    <span v-else class="vt-muted">Manual</span>
                </template>
                <template v-else-if="column.key === 'usos'">
                    <a-tag v-if="record.usos" color="blue"><LinkOutlined /> {{ record.usos }}</a-tag><span v-else class="vt-muted">0</span>
                </template>
                <template v-else-if="column.key === 'estado'">
                    <span class="vt-tag solido" :style="{ background: record.activo ? '#1faf5c' : '#94a3b8' }">{{ record.activo ? 'Activo' : 'Inactivo' }}</span>
                </template>
                <template v-else-if="column.key === 'traza'"><Registro :traza="record.traza" /></template>
                <template v-else-if="column.key === 'acciones'">
                    <div class="vt-acciones-fila">
                        <a-tooltip title="Editar"><a-button class="vt-ab azul" @click="abrir(record)"><EditOutlined /></a-button></a-tooltip>
                        <a-tooltip :title="record.activo ? 'Desactivar' : 'Activar'">
                            <a-button class="vt-ab" :class="record.activo ? 'rojo' : 'verde'" @click="alternar(record)"><StopOutlined v-if="record.activo" /><CheckCircleOutlined v-else /></a-button>
                        </a-tooltip>
                    </div>
                </template>
            </template>
            <template #summary>
                <a-table-summary fixed="bottom">
                    <a-table-summary-row class="vt-fsum">
                        <a-table-summary-cell :index="0"><a-input v-model:value="f.q" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell :index="1"><a-input v-model:value="f.nota" placeholder="Filtrar" allow-clear><template #prefix><SearchOutlined class="vt-muted" /></template></a-input></a-table-summary-cell>
                        <a-table-summary-cell v-if="esFormato" :index="2"></a-table-summary-cell>
                        <a-table-summary-cell v-if="esFormato" :index="3"></a-table-summary-cell>
                        <a-table-summary-cell :index="esFormato ? 4 : 2">
                            <a-select v-model:value="f.uso" allow-clear placeholder="Todos" :options="[{ value: 'si', label: 'En uso' }, { value: 'no', label: 'Sin uso' }]" />
                        </a-table-summary-cell>
                        <a-table-summary-cell :index="esFormato ? 5 : 3"></a-table-summary-cell>
                        <a-table-summary-cell :index="esFormato ? 6 : 4"></a-table-summary-cell>
                        <a-table-summary-cell :index="esFormato ? 7 : 5" align="center">
                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="Object.assign(f, { q: '', nota: '', uso: null })"><ClearOutlined /></a-button></a-tooltip>
                        </a-table-summary-cell>
                    </a-table-summary-row>
                </a-table-summary>
            </template>
        </a-table>
        <div class="vt-total-pie">Mostrando {{ lista.length }} de {{ registros.length }} registros</div>
    </div>

    <a-modal v-model:open="modal" centered destroy-on-close :width="esFormato ? 'min(760px, 96vw)' : 'min(600px, 96vw)'">
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" :style="{ background: INFO[tipo].color }"><component :is="INFO[tipo].icon" /></span>
                <div>{{ editando ? 'Editar registro' : 'Nuevo registro' }}<small>{{ tipos[tipo] }}</small></div>
            </div>
        </template>
        <a-form layout="vertical" class="mt-4">
            <a-form-item label="Nombre" required :validate-status="form.errors.nombre ? 'error' : ''" :help="form.errors.nombre">
                <a-input v-model:value="form.nombre"><template #prefix><TagOutlined :style="{ color: INFO[tipo].color }" /></template></a-input>
            </a-form-item>
            <a-form-item label="Nota descriptiva"><a-textarea v-model:value="form.nota" :auto-size="{ minRows: 2, maxRows: 5 }" /></a-form-item>
            <a-form-item v-if="esFormato" label="Se carga automáticamente en la etapa"
                :validate-status="form.errors.etapa ? 'error' : ''"
                :help="form.errors.etapa || (form.etapa && usadaPor(form.etapa) ? `Reemplazará a “${usadaPor(form.etapa).nombre}” en esta etapa.` : 'Al abrir una visita en esta etapa, Observaciones se precarga con este formato.')">
                <a-select v-model:value="form.etapa" allow-clear placeholder="Manual (no se precarga)" :options="ETAPAS">
                    <template #suffixIcon><ThunderboltOutlined /></template>
                </a-select>
            </a-form-item>
            <a-form-item v-if="esFormato" label="Plantilla del formato" extra="Texto prediseñado que se precarga al escribir una observación con este formato.">
                <a-textarea v-model:value="form.plantilla" :auto-size="{ minRows: 7, maxRows: 14 }" placeholder="Necesidades detectadas:&#10;Productos presentados:&#10;Siguiente paso:" />
            </a-form-item>
        </a-form>
        <template #footer>
            <PieModal tabla="catalogos" :registro="editando" :guardando="form.processing" @cancelar="modal = false" @guardar="guardar" />
        </template>
    </a-modal>
</template>

<style>
.vt-cat-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.vt-cat-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1.5px solid var(--vt-line);
    background: #fff;
    border-radius: 12px;
    padding: 7px 12px;
    cursor: pointer;
    font: inherit;
    color: var(--vt-navy);
    font-weight: 600;
    transition: border-color 0.15s, background 0.15s, transform 0.15s;
}
.vt-cat-tab:hover {
    transform: translateY(-2px);
}
.vt-cat-tab-ic {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--c);
}
.vt-cat-tab b {
    background: color-mix(in srgb, var(--c) 14%, transparent);
    color: var(--c);
    border-radius: 99px;
    padding: 0 8px;
    font-size: 12px;
}
.vt-cat-tab:hover,
.vt-cat-tab.on {
    border-color: var(--c);
}
.vt-cat-tab.on {
    background: color-mix(in srgb, var(--c) 8%, #fff);
}
.vt-cat-ic {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    font-size: 15px;
    flex: none;
}
.vt-pre-tip .ant-tooltip-inner {
    white-space: pre-line;
}
.vt-total-pie {
    padding: 12px 16px;
    font-size: 13px;
    color: var(--vt-muted);
    text-align: right;
}
</style>
