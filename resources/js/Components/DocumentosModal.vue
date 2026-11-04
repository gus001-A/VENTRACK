<script setup lang="ts">
/** Repositorio de documentos de una sucursal, usuario, prospecto o producto, con vista previa. */
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    FolderOpenOutlined, DeleteOutlined, DownloadOutlined, UploadOutlined, EyeOutlined, InboxOutlined, UserOutlined,
    ClockCircleOutlined, CloseOutlined, TagOutlined, CameraOutlined,
} from '@ant-design/icons-vue';
import VisorArchivo from './VisorArchivo.vue';
import CamaraModal from './CamaraModal.vue';
import { tamano, fechaHora, primerError } from '@/utils';
import { confirmar } from '@/confirmar';
import { ACEPTADOS, MAX_MB, archivoValido, tipoArchivo, ICONO_TIPO, COLOR_TIPO } from '@/archivos';

const props = defineProps<{ tipo: 'sucursal' | 'usuario' | 'prospecto' | 'producto'; id: number | null; titulo: string }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ cambio: [] }>();

const cargando = ref(false);
const data = ref<{ documentos: any[]; puedeEditar: boolean; puedeBorrar: boolean; tipos: any[] }>({ documentos: [], puedeEditar: false, puedeBorrar: false, tipos: [] });
const form = reactive<{ tipo_documento_id: number | null; descripcion: string; archivo: File | null }>({ tipo_documento_id: null, descripcion: '', archivo: null });
const subiendo = ref(false);
const camara = ref(false);
const preview = ref<string | null>(null);
const visor = reactive({ open: false, url: null as string | null, file: null as File | null, nombre: '', mime: null as string | null, descarga: null as string | null, tamano: null as number | null, detalle: null as string | null });

async function cargar() {
    if (!props.id) return;
    cargando.value = true;
    try {
        data.value = (await axios.get(`/documentos/${props.tipo}/${props.id}`)).data;
    } finally {
        cargando.value = false;
    }
}
watch(open, (o) => (o ? cargar() : limpiar()));

function limpiar() {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    Object.assign(form, { tipo_documento_id: null, descripcion: '', archivo: null });
}
onBeforeUnmount(limpiar);

function elegir(file: File) {
    const err = archivoValido(file);
    if (err) {
        message.error(err);
        return false;
    }
    limpiar();
    form.archivo = file;
    preview.value = URL.createObjectURL(file);
    return false;
}

const tipoNuevo = computed(() => (form.archivo ? tipoArchivo(form.archivo.name, form.archivo.type) : 'otro'));

function subir() {
    if (!form.archivo) return message.warning('Elige un archivo');
    subiendo.value = true;
    router.post(`/documentos/${props.tipo}/${props.id}`, form as any, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            limpiar();
            cargar();
            emit('cambio');
        },
        onError: (e) => message.error(primerError(e)),
        onFinish: () => (subiendo.value = false),
    });
}

async function borrar(d: any) {
    const ok = await confirmar({ tipo: 'danger', titulo: '¿Eliminar documento?', texto: `Se eliminará “${d.nombre}”. Esta acción no se puede deshacer.`, confirmar: 'Sí, eliminar' });
    if (!ok) return;
    router.delete(`/documentos/${d.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cargar();
            emit('cambio');
        },
    });
}

function ver(d: any) {
    Object.assign(visor, { open: true, url: d.url, file: null, nombre: d.nombre, mime: d.mime, descarga: d.url + '?descargar=1',
        tamano: d.tamano, detalle: `${d.tipo || 'Documento'} · subido por ${d.usuario} · ${fechaHora(d.fecha)}` });
}
function verNuevo() {
    if (form.archivo) Object.assign(visor, { open: true, url: null, file: form.archivo, nombre: form.archivo.name, mime: form.archivo.type, descarga: null, tamano: form.archivo.size, detalle: null });
}
const tipoDe = (d: any) => tipoArchivo(d.nombre, d.mime);
</script>

<template>
    <a-modal v-model:open="open" :footer="null" width="min(960px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="flex items-center gap-3">
                <span class="vt-head-icon" style="width: 40px; height: 40px; font-size: 18px"><FolderOpenOutlined /></span>
                <div>
                    <div>Documentos</div>
                    <div class="vt-muted vt-small font-normal">{{ titulo }} · {{ data.documentos.length }} archivo(s)</div>
                </div>
            </div>
        </template>

        <a-spin :spinning="cargando">
            <div class="vt-docs-grid" :class="{ solo: !data.puedeEditar }">
                <!-- Lista -->
                <div>
                    <div v-if="data.documentos.length" class="flex flex-col gap-2">
                        <div v-for="d in data.documentos" :key="d.id" class="vt-doc" @click="ver(d)">
                            <span class="vt-file-ic" :style="{ background: COLOR_TIPO[tipoDe(d)] + '1a', color: COLOR_TIPO[tipoDe(d)] }"><component :is="ICONO_TIPO[tipoDe(d)]" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <b>{{ d.tipo || 'Documento' }}</b>
                                    <span class="vt-small vt-muted">{{ tamano(d.tamano) }}</span>
                                </div>
                                <div class="vt-small truncate">{{ d.nombre }}</div>
                                <div class="vt-small vt-muted truncate" v-if="d.descripcion">{{ d.descripcion }}</div>
                                <div class="vt-small vt-traza-line"><UserOutlined /> {{ d.usuario }} · <ClockCircleOutlined /> {{ fechaHora(d.fecha) }}</div>
                            </div>
                            <div class="flex flex-none gap-1" @click.stop>
                                <a-tooltip title="Ver"><a-button class="vt-ab azul" @click="ver(d)"><EyeOutlined /></a-button></a-tooltip>
                                <a-tooltip title="Descargar"><a-button class="vt-ab verde" :href="d.url + '?descargar=1'"><DownloadOutlined /></a-button></a-tooltip>
                                <a-tooltip v-if="data.puedeBorrar" title="Eliminar"><a-button class="vt-ab rojo" @click="borrar(d)"><DeleteOutlined /></a-button></a-tooltip>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="!cargando" class="vt-empty"><FolderOpenOutlined style="font-size: 30px; color: var(--vt-blue)" /><br />Aún no hay documentos guardados</div>
                </div>

                <!-- Carga con vista previa -->
                <div v-if="data.puedeEditar" class="vt-upload-box">
                    <div class="font-semibold mb-2"><UploadOutlined style="color: var(--vt-blue)" /> Nuevo archivo</div>
                    <a-upload-dragger v-if="!form.archivo" :accept="ACEPTADOS" :show-upload-list="false" :before-upload="elegir" :multiple="false">
                        <p class="ant-upload-drag-icon"><InboxOutlined style="color: var(--vt-blue)" /></p>
                        <p class="ant-upload-text">Arrastra o haz clic para elegir</p>
                        <p class="ant-upload-hint">PDF, Word, Excel, PowerPoint o imágenes · máx. {{ MAX_MB }} MB</p>
                    </a-upload-dragger>
                    <a-button v-if="!form.archivo" block size="large" class="mt-2" @click="camara = true"><CameraOutlined style="color: #7c4ddb" /> Tomar foto con la cámara</a-button>

                    <div v-else class="vt-prev">
                        <div class="vt-prev-media" @click="verNuevo">
                            <img v-if="tipoNuevo === 'imagen'" :src="preview!" alt="Vista previa" />
                            <iframe v-else-if="tipoNuevo === 'pdf'" :src="preview! + '#toolbar=0&view=FitH'" title="Vista previa PDF"></iframe>
                            <div v-else class="vt-prev-ic" :style="{ color: COLOR_TIPO[tipoNuevo] }"><component :is="ICONO_TIPO[tipoNuevo]" /></div>
                            <span class="vt-prev-ver"><EyeOutlined /> Vista previa</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="truncate flex-1 vt-small"><b>{{ form.archivo.name }}</b> · {{ tamano(form.archivo.size) }}</span>
                            <a-button type="text" danger @click="limpiar"><CloseOutlined /></a-button>
                        </div>
                    </div>

                    <a-form layout="vertical" class="mt-3">
                        <a-form-item label="Tipo de documento" class="mb-3">
                            <a-select v-model:value="form.tipo_documento_id" allow-clear placeholder="Selecciona"
                                :options="data.tipos.map((t) => ({ value: t.id, label: t.nombre }))">
                                <template #suffixIcon><TagOutlined /></template>
                            </a-select>
                        </a-form-item>
                        <a-form-item label="Descripción" class="mb-3">
                            <a-input v-model:value="form.descripcion" placeholder="Ej. vigente 2026" />
                        </a-form-item>
                    </a-form>
                    <a-button type="primary" block size="large" :loading="subiendo" :disabled="!form.archivo" @click="subir"><UploadOutlined /> Guardar documento</a-button>
                </div>
            </div>
        </a-spin>
    </a-modal>

    <CamaraModal v-model:open="camara" @foto="elegir" />
    <VisorArchivo v-model:open="visor.open" :url="visor.url" :file="visor.file" :nombre="visor.nombre" :mime="visor.mime" :descarga="visor.descarga" :tamano="visor.tamano" :detalle="visor.detalle" />
</template>

<style>
.vt-docs-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 18px;
    margin-top: 12px;
}
.vt-docs-grid {
    align-items: start;
}
.vt-docs-grid > div:first-child {
    max-height: 62vh;
    overflow: auto;
    padding-right: 4px;
}
.vt-upload-box .ant-upload-wrapper,
.vt-upload-box .ant-upload-drag {
    height: auto !important;
}
.vt-upload-box .ant-upload-drag .ant-upload-btn {
    padding: 18px 12px !important;
}
.vt-upload-box .ant-upload-drag-icon {
    margin-bottom: 8px !important;
}
.vt-upload-box .ant-upload-drag-icon .anticon {
    font-size: 38px !important;
}
.vt-docs-grid.solo {
    grid-template-columns: 1fr;
}
.vt-doc {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--vt-line);
    border-radius: 12px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.vt-doc:hover {
    border-color: var(--vt-blue);
    background: #f8fbff;
}
.vt-traza-line {
    color: var(--vt-muted);
}
.vt-traza-line .anticon {
    color: var(--vt-teal);
}
.vt-upload-box {
    background: #f7fafc;
    border: 1px solid var(--vt-line);
    border-radius: 14px;
    padding: 14px;
}
.vt-prev-media {
    position: relative;
    height: 200px;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--vt-line);
    cursor: pointer;
    display: grid;
    place-items: center;
}
.vt-prev-media img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.vt-prev-media iframe {
    width: 100%;
    height: 100%;
    border: 0;
    pointer-events: none;
}
.vt-prev-ic {
    font-size: 64px;
}
.vt-prev-ver {
    position: absolute;
    right: 8px;
    bottom: 8px;
    background: rgba(10, 31, 68, 0.78);
    color: #fff;
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 99px;
}
@media (max-width: 760px) {
    .vt-docs-grid {
        grid-template-columns: 1fr;
    }
}
</style>
