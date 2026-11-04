<script setup lang="ts">
/** Modal de una visita: flujo de estatus, evidencia en tiempo real y observaciones. */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    EnvironmentOutlined, CameraOutlined, AimOutlined, PhoneOutlined, UserOutlined, ArrowRightOutlined,
    LikeOutlined, DislikeOutlined, EditOutlined, CloseCircleOutlined, CommentOutlined, ShopOutlined,
    CalendarOutlined, FlagOutlined, PictureOutlined, ThunderboltOutlined, FormOutlined, IdcardOutlined, ClockCircleOutlined, SettingOutlined,
    CloseOutlined, RocketOutlined, TagsOutlined, SendOutlined, InfoCircleOutlined, FileTextOutlined, LockOutlined, PaperClipOutlined, ExclamationCircleOutlined, PlayCircleOutlined, CheckCircleOutlined, EyeOutlined, SafetyCertificateOutlined,
} from '@ant-design/icons-vue';
import EstatusTag from './EstatusTag.vue';
import ResultadoTag from './ResultadoTag.vue';
import Registro from './Registro.vue';
import HistorialBoton from './HistorialBoton.vue';
import { confirmar } from '@/confirmar';
import MapaModal from './MapaModal.vue';
import CamaraModal from './CamaraModal.vue';
import VisorArchivo from './VisorArchivo.vue';
import { ACEPTADOS, archivoValido, tipoArchivo, ICONO_TIPO, COLOR_TIPO } from '@/archivos';
import ProgramarVisitaModal from './ProgramarVisitaModal.vue';
import { ESTATUS, ESTATUS_INFO, ESTATUS_DOT, fechaCorta, fechaLarga, fechaHora, hora12, iniciales, primerError, type Estatus, type Opcion } from '@/utils';
import { useShared } from '@/composables/useShared';

const props = defineProps<{ visitaId: number | null; opciones?: { prospectos: Opcion[]; promotores: Opcion[] } }>();
const emit = defineEmits<{ close: [] }>();
const { user } = useShared();

const v = ref<any>(null);
const cargando = ref(false);
const enviando = ref<string | null>(null);
const nota = ref('');
const formatoId = ref<number | null>(null);
const formatos = ref<any[]>([]);
const plantillaActual = ref(''); // antes del watch inmediato que la reinicia
const camara = ref(false);
const reprogramar = ref(false);
const mapaVer = reactive({ open: false, titulo: '', direccion: null as string | null, latitud: null as number | null, longitud: null as number | null });
const verMapa = (titulo: string, direccion: string | null, latitud: number | null = null, longitud: number | null = null) =>
    Object.assign(mapaVer, { open: true, titulo, direccion, latitud, longitud });

async function cargar() {
    if (!props.visitaId) return;
    cargando.value = true;
    try {
        v.value = (await axios.get(`/visitas/${props.visitaId}/detalle`)).data;
        formatos.value = v.value.formatos || [];
        precargarFormato();
    } catch (e: any) {
        message.error(e?.response?.data?.message || 'No se pudo cargar la visita');
        emit('close');
    } finally {
        cargando.value = false;
    }
}
watch(() => props.visitaId, (id) => {
    nota.value = '';
    plantillaActual.value = '';
    formatoId.value = null;
    if (id) cargar();
    else v.value = null;
}, { immediate: true });

const paso = computed(() => (v.value ? ESTATUS.indexOf(v.value.estatus) : 0));
const pasos = computed(() =>
    ESTATUS.map((s) => {
        const t: string | null = v.value?.tiempos?.[s] ?? null;
        return { estatus: s, title: ESTATUS_INFO[s].label, description: t ? t.slice(8, 10) + '/' + t.slice(5, 7) + ' · ' + hora12(t.slice(11)) : '' };
    }),
);

// Texto de la plantilla precargada: si el usuario no lo modifica, se puede reemplazar y no cuenta como comentario
const sinEditar = () => !nota.value.trim() || nota.value.trim() === plantillaActual.value.trim();
function aplicarFormato(id: number | null) {
    const f = formatos.value.find((x) => x.id === id);
    if (!sinEditar()) return;
    nota.value = f?.plantilla ?? '';
    plantillaActual.value = nota.value;
}
// Formato de la etapa actual (catálogo de formatos → etapa), se carga solo al abrir la visita
const formatoEtapa = computed(() => formatos.value.find((f) => f.etapa === v.value?.estatus) ?? null);
function precargarFormato() {
    if (!v.value?.can?.observar || !sinEditar()) return;
    formatoId.value = formatoEtapa.value?.id ?? null;
    aplicarFormato(formatoId.value);
}

function accion(url: string, data: Record<string, any>, clave: string, opts: Record<string, any> = {}) {
    enviando.value = clave;
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        ...opts,
        onSuccess: () => {
            cargar();
            if (data.texto) {
                nota.value = '';
                plantillaActual.value = '';
                formatoId.value = null;
            }
            opts.onSuccess?.();
        },
        onError: (e) => message.error(primerError(e)),
        onFinish: () => (enviando.value = null),
    });
}

// Comentario (obligatorio para avanzar) + evidencia opcional que lo acompaña
const adjuntosNuevos = ref<{ file: File; url: string | null }[]>([]);
const adjuntoInput = ref<HTMLInputElement | null>(null);
const faltaComentario = ref(false);
const conNota = () => ({ texto: nota.value.trim(), formato_id: formatoId.value, archivos: adjuntosNuevos.value.map((f) => f.file) });
function agregarAdjunto(f: File) {
    const error = archivoValido(f);
    if (error) return message.error(error);
    if (adjuntosNuevos.value.length >= 6) return message.warning('Puedes adjuntar hasta 6 archivos a la vez');
    adjuntosNuevos.value.push({ file: f, url: f.type.startsWith('image/') ? URL.createObjectURL(f) : null });
}
function elegirAdjuntos(e: Event) {
    const t = e.target as HTMLInputElement;
    Array.from(t.files || []).forEach(agregarAdjunto);
    t.value = '';
}
function quitarAdjunto(i: number) {
    const u = adjuntosNuevos.value[i].url;
    if (u) URL.revokeObjectURL(u);
    adjuntosNuevos.value.splice(i, 1);
}
function limpiarComentario() {
    nota.value = '';
    plantillaActual.value = '';
    formatoId.value = null;
    adjuntosNuevos.value.forEach((f) => f.url && URL.revokeObjectURL(f.url));
    adjuntosNuevos.value = [];
}
function requiereComentario(): boolean {
    faltaComentario.value = sinEditar();
    if (faltaComentario.value) message.warning('Escribe un comentario para continuar con el proceso.');
    return !faltaComentario.value;
}
const opcionesForm = { forceFormData: true, onSuccess: limpiarComentario };

// Acción del proceso según el estatus actual (requiere comentario; evidencia opcional)
const accionProceso = computed(() => {
    const sig = v.value?.can?.siguiente;
    if (sig === 'proceso') return { clave: 'avanzar', texto: 'Iniciar visita', icono: PlayCircleOutlined };
    if (sig === 'revisada') return { clave: 'avanzar', texto: 'Marcar como revisada', icono: EyeOutlined };
    if (sig === 'supervisada') return { clave: 'avanzar', texto: 'Marcar como supervisada', icono: SafetyCertificateOutlined };
    return null;
});
async function avanzar() {
    if (!requiereComentario()) return;
    const sig = v.value.can.siguiente;
    if (sig === 'supervisada') {
        const ok = await confirmar({
            tipo: 'warning', titulo: '¿Marcar como supervisada?', confirmar: 'Sí, supervisar', cancelar: 'No',
            texto: 'Al supervisarla, la visita queda cerrada: ya no se podrán agregar comentarios, evidencia ni cambios.',
        });
        if (!ok) return;
    }
    // Iniciar la visita exige la ubicación del equipo en ese momento
    const gps = await ubicacionAhora(sig === 'proceso', 'avanzar');
    if (sig === 'proceso' && !gps) return;
    accion(`/visitas/${v.value.id}/avanzar`, { ...conNota(), ...(gps || {}) }, 'avanzar', opcionesForm);
}
async function cerrar(resultado: 'positiva' | 'negativa') {
    if (!requiereComentario()) return;
    const gps = await ubicacionAhora(true, resultado);
    if (!gps) return;
    accion(`/visitas/${v.value.id}/cerrar`, { resultado, ...conNota(), ...gps }, resultado, opcionesForm);
}
async function observar() {
    if (!requiereComentario()) return;
    const gps = enCampo.value ? await ubicacionAhora(false, 'obs') : null;
    accion(`/visitas/${v.value.id}/observaciones`, { ...conNota(), ...(gps || {}) }, 'obs', opcionesForm);
}
watch(nota, () => !sinEditar() && (faltaComentario.value = false));

// Ubicación en tiempo real del equipo: se sigue mientras el modal está abierto en una visita de campo
type Gps = { latitud: string; longitud: string; precision: number };
const gps = reactive({ actual: null as (Gps & { at: number }) | null, error: null as string | null });
let vigilancia: number | null = null;
const enCampo = computed(() => !!v.value?.can?.evidencia && ['programada', 'proceso'].includes(v.value.estatus));
const aGps = (p: GeolocationPosition): Gps => ({
    latitud: p.coords.latitude.toFixed(7), longitud: p.coords.longitude.toFixed(7), precision: Math.round(p.coords.accuracy),
});
const mensajeGps = (err: GeolocationPositionError) => err.code === 1
    ? 'Permite el acceso a la ubicación en el navegador para registrar la visita.'
    : 'No se pudo obtener la ubicación del equipo. Verifica el GPS e inténtalo de nuevo.';
function detenerGps() {
    if (vigilancia !== null) navigator.geolocation?.clearWatch(vigilancia);
    vigilancia = null;
}
watch(enCampo, (si) => {
    detenerGps();
    gps.actual = null;
    gps.error = null;
    if (!si) return;
    if (!navigator.geolocation) {
        gps.error = 'Este dispositivo no permite obtener la ubicación.';
        return;
    }
    vigilancia = navigator.geolocation.watchPosition(
        (p) => {
            gps.actual = { ...aGps(p), at: Date.now() };
            gps.error = null;
        },
        (err) => (gps.error = mensajeGps(err)),
        { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    );
});
onBeforeUnmount(detenerGps);
/** Lectura del GPS al momento de la acción (usa la lectura en vivo si tiene menos de 30 s). */
function ubicacionAhora(obligatoria: boolean, clave: string): Promise<Gps | null> {
    if (gps.actual && Date.now() - gps.actual.at < 30000) {
        const { at, ...g } = gps.actual;
        return Promise.resolve(g);
    }
    if (!navigator.geolocation) {
        if (obligatoria) message.error('Este dispositivo no permite obtener la ubicación.');
        return Promise.resolve(null);
    }
    enviando.value = clave;
    return new Promise((ok) => navigator.geolocation.getCurrentPosition(
        (p) => ok(aGps(p)),
        (err) => {
            enviando.value = null;
            if (obligatoria) message.error(mensajeGps(err));
            ok(null);
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    ));
}


// Visor de adjuntos guardados (imágenes, PDF y Office) en modal
const visor = reactive({ open: false, url: null as string | null, nombre: '', mime: null as string | null, detalle: null as string | null });
const verAdjunto = (a: any) => Object.assign(visor, {
    open: true, url: a.url, nombre: a.tipo === 'foto' ? 'Imagen de evidencia' : a.nombre, mime: a.tipo === 'foto' ? 'image/*' : null, detalle: `${a.usuario} · ${fechaHora(a.fecha)} · ${ESTATUS_INFO[a.estatus as Estatus].label}`,
});
const iconoAdj = (nombre: string) => ICONO_TIPO[tipoArchivo(nombre)];
const colorAdj = (nombre: string) => COLOR_TIPO[tipoArchivo(nombre)];

// Observaciones de la más reciente a la más antigua
const observacionesRecientes = computed(() => [...(v.value?.observaciones ?? [])].reverse());

const ICONOS: Record<string, any> = {
    programada: CalendarOutlined, proceso: PlayCircleOutlined, realizada: CheckCircleOutlined, revisada: EyeOutlined, supervisada: SafetyCertificateOutlined,
};

async function cancelarVisita() {
    const ok = await confirmar({ tipo: 'danger', titulo: '¿Cancelar esta visita?', texto: `La visita a “${v.value.prospecto.nombre}” se eliminará de la agenda.`, confirmar: 'Sí, cancelar visita', cancelar: 'No' });
    if (!ok) return;
    router.delete(`/visitas/${v.value.id}`, { preserveScroll: true, onSuccess: () => emit('close') });
}

const mapa = computed(() =>
    v.value?.latitud ? `https://maps.google.com/maps?q=${v.value.latitud},${v.value.longitud}&z=16&output=embed` : null,
);
</script>

<template>
    <a-modal :open="!!visitaId" width="min(1160px, 96vw)" centered :footer="null" :closable="false" destroy-on-close wrap-class-name="vt-vm-wrap" @cancel="emit('close')">
        <a-spin :spinning="cargando && !v">
            <div v-if="!v" style="height: 300px"></div>
            <div v-else class="vt-vm" :style="{ '--e': ESTATUS_DOT[v.estatus as Estatus] }">
                <!-- Encabezado: visita, quién la registró y bitácora -->
                <header class="vt-vm-head">
                    <span class="vt-vm-ic"><EnvironmentOutlined /></span>
                    <div class="min-w-0 flex-1">
                        <h2>{{ v.prospecto.nombre }}</h2>
                        <p>
                            <span>Visita #{{ v.id }} · {{ fechaLarga(v.fecha) }}<template v-if="v.hora"> · {{ hora12(v.hora) }}</template></span>
                            <span v-if="v.traza?.creado" class="vt-vm-reg">
                                <UserOutlined /> Registró <b>{{ v.traza.creado.usuario }}</b> · {{ fechaCorta(v.traza.creado.fecha) }}, {{ hora12(v.traza.creado.hora) }}
                            </span>
                        </p>
                    </div>
                    <div class="vt-vm-head-der">
                        <EstatusTag :estatus="v.estatus" />
                        <ResultadoTag v-if="v.resultado || v.vencida" :resultado="v.resultado" :vencida="v.vencida" />
                        <HistorialBoton tabla="visitas" :id="v.id" :titulo="`Visita #${v.id} · ${v.prospecto.nombre}`" />
                        <button class="vt-vm-x" aria-label="Cerrar" @click="emit('close')"><CloseOutlined /></button>
                    </div>
                </header>

                <!-- Ciclo de vida -->
                <div class="vt-vm-info">
                    <div class="vt-flujo">
                        <div v-for="(p, i) in pasos" :key="p.estatus" class="vt-flujo-p" :class="{ d: i < paso, cur: i === paso }" :style="{ '--s': ESTATUS_DOT[p.estatus] }">
                            <span class="vt-flujo-ic"><component :is="ICONOS[p.estatus]" /></span>
                            <b>{{ p.title }}</b>
                            <small>{{ p.description || '—' }}</small>
                        </div>
                    </div>
                </div>

                <div class="vt-vm-body">
                    <!-- Columna izquierda: información y evidencia -->
                    <div class="vt-vm-col">
                        <section class="vt-panel">
                            <div class="vt-panel-t"><InfoCircleOutlined style="background: #0461d8" /> Información relevante</div>
                            <div class="vt-datos">
                                <div><span><ShopOutlined style="color: #1597a5" /> Sucursal</span><b>{{ v.sucursal.nombre }}</b></div>
                                <div><span><UserOutlined style="color: #1faf5c" /> Promotor</span><b>{{ v.promotor.nombre }}</b></div>
                                <div><span><CalendarOutlined style="color: #7c4ddb" /> Fecha</span><b>{{ fechaLarga(v.fecha) }}</b></div>
                                <div><span><ClockCircleOutlined style="color: #f08c00" /> Hora</span><b>{{ v.hora ? hora12(v.hora) : 'Sin hora' }}</b></div>
                                <div><span><IdcardOutlined style="color: #e0457b" /> Prospecto</span><b class="truncate">{{ v.prospecto.nombre }}</b></div>
                                <div><span><TagsOutlined style="color: #7c4ddb" /> Giro</span><b>{{ v.prospecto.giro || '—' }}</b></div>
                            </div>
                            <div class="vt-desc">
                                <span><FileTextOutlined /> Descripción</span>
                                <p>{{ v.objetivo || 'Sin descripción registrada para esta visita.' }}</p>
                            </div>
                            <div class="vt-contacto">
                                <a class="min-w-0 truncate" @click="verMapa(v.prospecto.nombre, v.prospecto.direccion)"><EnvironmentOutlined /> {{ v.prospecto.direccion }}</a>
                                <span v-if="v.prospecto.contacto" class="vt-small vt-muted vt-nowrap"><UserOutlined /> {{ v.prospecto.contacto }}</span>
                                <a-button v-if="v.prospecto.telefono" :href="'tel:' + v.prospecto.telefono"><PhoneOutlined style="color: #1faf5c" /> Llamar</a-button>
                            </div>
                        </section>

                        <section v-if="user.perfil !== 'Prospecto'" class="vt-panel vt-panel-crece">
                            <div class="vt-panel-t"><AimOutlined style="background: #1faf5c" /> Ubicación de la visita <span class="vt-panel-n">{{ v.ubicaciones.length }} registro(s)</span></div>
                            <p class="vt-small vt-muted mb-2">Comprueba que la visita se realizó en el domicilio del prospecto.</p>
                            <div class="vt-small mb-2" :class="v.geo ? 'vt-okline' : 'vt-warn'">
                                <CheckCircleOutlined v-if="v.geo" /><AimOutlined v-else />
                                <template v-if="v.geo">
                                    Visita comprobada · <a @click="verMapa('Ubicación de la visita', v.prospecto.nombre, v.latitud, v.longitud)">{{ v.latitud.toFixed(5) }}, {{ v.longitud.toFixed(5) }}</a>
                                    <span class="vt-muted"> · {{ fechaHora(v.ubicacion_at) }}</span>
                                </template>
                                <template v-else>Ubicación obligatoria al momento de la visita</template>
                            </div>
                            <div class="vt-evid">
                                <iframe v-if="mapa" :src="mapa" class="vt-mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación de la visita"></iframe>
                                <div v-else class="vt-mapa vt-mapa-vacio"><EnvironmentOutlined /><span>Sin ubicación registrada</span></div>
                            </div>
                            <div v-if="enCampo" class="vt-gps" :class="{ error: gps.error && !gps.actual }">
                                <span class="vt-gps-dot"></span>
                                <div class="min-w-0">
                                    <b>Ubicación del equipo en tiempo real</b>
                                    <span v-if="gps.actual">{{ Number(gps.actual.latitud).toFixed(5) }}, {{ Number(gps.actual.longitud).toFixed(5) }} · ±{{ gps.actual.precision }} m</span>
                                    <span v-else-if="gps.error">{{ gps.error }}</span>
                                    <span v-else>Obteniendo ubicación del GPS…</span>
                                    <small>Se registra automáticamente al iniciar, cerrar o comentar la visita.</small>
                                </div>
                            </div>
                            <a-collapse v-if="v.ubicaciones.length > 1" ghost class="mt-2">
                                <a-collapse-panel v-if="v.ubicaciones.length > 1" key="u" :header="`Historial de ubicaciones (${v.ubicaciones.length})`">
                                    <div v-for="u in v.ubicaciones" :key="u.id" class="vt-small mb-1">
                                        <a @click="verMapa('Ubicación registrada', u.usuario, u.latitud, u.longitud)">{{ u.latitud.toFixed(5) }}, {{ u.longitud.toFixed(5) }}</a>
                                        <span class="vt-muted"> · ±{{ u.precision ?? '?' }} m · {{ u.usuario }} · {{ fechaHora(u.fecha) }}</span>
                                    </div>
                                </a-collapse-panel>
                            </a-collapse>
                        </section>
                    </div>

                    <!-- Columna derecha: observaciones (de la más reciente a la más antigua) y gestión -->
                    <div class="vt-vm-col vt-vm-col-der">
                        <section v-if="user.perfil !== 'Prospecto'" class="vt-panel vt-panel-crece vt-panel-obs">
                            <div class="vt-panel-t"><CommentOutlined style="background: #0461d8" /> Observaciones <span class="vt-panel-n">{{ v.observaciones.length }}</span></div>

                            <div v-if="v.can.observar" class="vt-obs-form">
                                <a-select v-model:value="formatoId" allow-clear placeholder="Formato libre (elige un formato prediseñado)" class="w-full"
                                    :options="formatos.map((f) => ({ value: f.id, label: f.nombre }))" @change="aplicarFormato">
                                    <template #suffixIcon><FormOutlined /></template>
                                </a-select>
                                <div v-if="formatoEtapa && formatoId === formatoEtapa.id" class="vt-obs-auto">
                                    <ThunderboltOutlined /> Formato cargado según la etapa: <b>{{ ESTATUS_INFO[v.estatus as Estatus].label }}</b>. Completa los campos para continuar.
                                </div>
                                <a-textarea v-model:value="nota" :auto-size="{ minRows: 3, maxRows: 8 }" class="mt-3" :maxlength="5000" show-count
                                    :status="faltaComentario ? 'error' : ''"
                                    :placeholder="accionProceso || v.can.cerrar ? 'Comentario (obligatorio para continuar con el proceso)' : 'Escribe una observación'" />
                                <div v-if="faltaComentario" class="vt-obs-err"><ExclamationCircleOutlined /> El comentario es obligatorio para continuar.</div>

                                <!-- Evidencia opcional del comentario -->
                                <div class="vt-adj">
                                    <span class="vt-adj-t"><PaperClipOutlined /> Evidencia <em>(opcional · fotos, PDF u Office · hasta 6)</em></span>
                                    <div class="vt-adj-lista">
                                        <a-tooltip v-for="(f, i) in adjuntosNuevos" :key="f.file.name + i" :title="f.url ? null : f.file.name">
                                            <div class="vt-adj-f" :class="{ doc: !f.url }">
                                                <img v-if="f.url" :src="f.url" alt="" />
                                                <template v-else>
                                                    <component :is="iconoAdj(f.file.name)" :style="{ color: colorAdj(f.file.name) }" />
                                                    <span>{{ f.file.name }}</span>
                                                </template>
                                                <button type="button" aria-label="Quitar" @click="quitarAdjunto(i)"><CloseOutlined /></button>
                                            </div>
                                        </a-tooltip>
                                        <template v-if="adjuntosNuevos.length < 6">
                                            <button type="button" class="vt-adj-add" @click="camara = true"><CameraOutlined /><span>Tomar foto</span></button>
                                            <button type="button" class="vt-adj-add" @click="adjuntoInput?.click()"><PaperClipOutlined /><span>Adjuntar</span></button>
                                        </template>
                                        <input ref="adjuntoInput" type="file" :accept="ACEPTADOS" multiple class="hidden" @change="elegirAdjuntos" />
                                    </div>
                                </div>

                                <!-- Acciones del proceso -->
                                <div class="vt-obs-acc">
                                    <a-button size="large" :loading="enviando === 'obs'" @click="observar"><SendOutlined /> Solo guardar comentario</a-button>
                                    <a-button v-if="accionProceso" size="large" type="primary" :loading="enviando === 'avanzar'" @click="avanzar">
                                        <component :is="accionProceso.icono" /> {{ accionProceso.texto }}
                                    </a-button>
                                    <template v-if="v.can.cerrar">
                                        <a-button size="large" type="primary" class="vt-btn-ok" :loading="enviando === 'positiva'" @click="cerrar('positiva')"><LikeOutlined /> Cerrar con venta</a-button>
                                        <a-button size="large" danger :loading="enviando === 'negativa'" @click="cerrar('negativa')"><DislikeOutlined /> Cerrar sin venta</a-button>
                                    </template>
                                </div>
                                <div v-if="v.can.siguiente === 'proceso' || v.can.cerrar" class="vt-obs-aviso"><AimOutlined /> Al {{ v.can.cerrar ? 'cerrar' : 'iniciar' }} la visita se registra la <b>ubicación en tiempo real</b> del equipo.</div>
                            </div>
                            <div class="vt-obs-list">
                                <div v-for="o in observacionesRecientes" :key="o.id" class="vt-obs" :style="{ '--s': ESTATUS_DOT[o.estatus as Estatus] }">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="vt-av vt-av-sm">{{ iniciales(o.usuario || '?') }}</span>
                                        <b>{{ o.usuario }}</b><span class="vt-small vt-muted">{{ o.perfil }}</span>
                                        <span class="vt-small vt-muted ml-auto vt-nowrap"><ClockCircleOutlined /> {{ fechaHora(o.fecha) }}</span>
                                    </div>
                                    <div class="flex gap-1 my-2">
                                        <EstatusTag :estatus="o.estatus" suave />
                                        <a-tag v-if="o.formato" class="m-0" color="blue"><FormOutlined /> {{ o.formato }}</a-tag>
                                    </div>
                                    <div class="whitespace-pre-line">{{ o.texto }}</div>
                                    <div v-if="o.adjuntos?.length" class="vt-obs-adj">
                                        <button v-for="a in o.adjuntos" :key="a.id" type="button" class="vt-adj-g" :aria-label="a.tipo === 'foto' ? 'Ver imagen' : a.nombre" @click="verAdjunto(a)">
                                            <img v-if="a.tipo === 'foto'" :src="a.url" alt="" />
                                            <component v-else :is="iconoAdj(a.nombre)" :style="{ color: colorAdj(a.nombre) }" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="!v.observaciones.length" class="vt-empty"><CommentOutlined style="font-size: 26px; color: var(--vt-blue)" /><br />Aún no hay observaciones</div>
                            </div>
                        </section>

                        <section v-if="v.can.editar" class="vt-panel">
                            <div class="vt-panel-t"><SettingOutlined style="background: #5d7085" /> Gestión</div>
                            <div class="grid grid-cols-2 gap-2">
                                <a-button size="large" @click="reprogramar = true"><EditOutlined style="color: var(--vt-blue)" /> Reprogramar</a-button>
                                <a-button size="large" danger @click="cancelarVisita"><CloseCircleOutlined /> Cancelar visita</a-button>
                            </div>
                        </section>
                    </div>
                </div>

                <ProgramarVisitaModal v-if="opciones && v.can.editar" v-model:open="reprogramar" :opciones="opciones" :visita="v" />
            </div>
        </a-spin>
    </a-modal>

    <CamaraModal v-model:open="camara" @foto="agregarAdjunto" />
    <VisorArchivo v-model:open="visor.open" :url="visor.url" :nombre="visor.nombre" :mime="visor.mime" :detalle="visor.detalle" />
    <MapaModal v-model:open="mapaVer.open" :titulo="mapaVer.titulo" :direccion="mapaVer.direccion" :latitud="mapaVer.latitud" :longitud="mapaVer.longitud" />
</template>

<style>
.vt-vm-wrap .ant-modal-content {
    padding: 0 !important;
    overflow: hidden;
    border-radius: 20px !important;
}
.vt-vm-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    color: #fff;
    background: linear-gradient(120deg, #0a1f44 0%, color-mix(in srgb, var(--e) 75%, #0a1f44) 100%);
}
.vt-vm-head h2 {
    margin: 0;
    font-size: 22px;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-vm-head p {
    margin: 2px 0 0;
    color: #cfdbec;
    font-size: 13px;
}
.vt-vm-ic {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 22px;
    background: rgba(255, 255, 255, 0.15);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
    flex: none;
}
.vt-vm-head .vt-tag.solido {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.35);
}
.vt-vm-x {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    cursor: pointer;
    font-size: 15px;
    display: grid;
    place-items: center;
    transition: background 0.15s, transform 0.15s;
}
.vt-vm-x:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: rotate(90deg);
}
.vt-vm-info {
    padding: 14px 22px 4px;
    background: #f7fafc;
    border-bottom: 1px solid var(--vt-line);
}
.vt-chips-info {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}
.vt-chips-info span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 600;
    padding: 4px 11px;
    border-radius: 99px;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 11%, #fff);
    border: 1px solid color-mix(in srgb, var(--c) 20%, transparent);
}
.vt-flujo {
    display: flex;
    padding-bottom: 10px;
}
.vt-flujo-p {
    flex: 1;
    position: relative;
    text-align: center;
    color: var(--vt-muted);
    min-width: 0;
}
.vt-flujo-p::before {
    content: '';
    position: absolute;
    top: 17px;
    left: -50%;
    width: 100%;
    height: 3px;
    background: #dfe6ee;
    z-index: 0;
}
.vt-flujo-p:first-child::before {
    display: none;
}
.vt-flujo-p.d::before,
.vt-flujo-p.cur::before {
    background: var(--s);
}
.vt-flujo-ic {
    position: relative;
    z-index: 1;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    margin: 0 auto 6px;
    font-size: 15px;
    background: #fff;
    border: 2px solid #dfe6ee;
    color: #a3b1c2;
    transition: transform 0.2s;
}
.vt-flujo-p.d .vt-flujo-ic {
    background: var(--s);
    border-color: var(--s);
    color: #fff;
}
.vt-flujo-p.cur .vt-flujo-ic {
    background: var(--s);
    border-color: #fff;
    color: #fff;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--s) 30%, transparent);
    transform: scale(1.12);
    animation: vt-pulse 2s ease-in-out infinite;
}
.vt-flujo-p b {
    display: block;
    font-size: 12.5px;
    color: var(--vt-navy);
    font-weight: 600;
}
.vt-flujo-p.cur b {
    color: var(--s);
    font-weight: 800;
}
.vt-flujo-p small {
    display: block;
    font-size: 11px;
    white-space: nowrap;
}
.vt-vm-body {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 18px;
    padding: 18px 22px;
    max-height: calc(100vh - 360px);
    overflow: auto;
    background: #fbfcfe;
}
.vt-vm-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
}
.vt-panel {
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 14px;
    padding: 16px;
}
.vt-panel-accion {
    border-color: color-mix(in srgb, var(--e) 40%, var(--vt-line));
    background: color-mix(in srgb, var(--e) 5%, #fff);
}
.vt-panel-t {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
    font-size: 15px;
    color: var(--vt-navy);
    margin-bottom: 12px;
}
.vt-panel-t > .anticon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 14px;
}
.vt-panel-n {
    margin-left: auto;
    font-size: 12px;
    font-weight: 600;
    color: var(--vt-muted);
    background: #eef2f7;
    padding: 1px 9px;
    border-radius: 99px;
}
.vt-btn-avanzar {
    min-width: 150px;
    background: linear-gradient(90deg, #0461d8, #1597a5) !important;
    border: 0 !important;
}
.vt-btn-ok {
    background: #1faf5c !important;
}
.vt-evid {
    display: grid;
    gap: 10px;
}
.vt-mapa {
    width: 100%;
    height: 180px;
    border: 1px solid var(--vt-line);
    border-radius: 12px;
}
.vt-fotos {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.vt-foto {
    object-fit: cover;
    border-radius: 10px;
}
.vt-fotos .ant-image {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(10, 31, 68, 0.12);
}
.vt-ev-btns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}
.vt-warn {
    color: #a86f00;
    background: #fff4d9;
    padding: 8px 12px;
    border-radius: 10px;
}
.vt-okline {
    color: var(--vt-green-dark);
    background: var(--vt-green-soft);
    padding: 8px 12px;
    border-radius: 10px;
}
.vt-obs-list {
    max-height: 300px;
    overflow: auto;
    padding-right: 4px;
}
.vt-obs {
    padding: 12px 14px;
    margin: 0 0 12px;
    background: #f9fbfd;
    border: 1px solid var(--vt-line);
    border-left: 4px solid var(--s);
    border-radius: 12px;
}
.vt-av.vt-av-sm {
    width: 26px;
    height: 26px;
    font-size: 10.5px;
}
.vt-obs-form {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed var(--vt-line);
}
.vt-vm-pie {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 22px;
    border-top: 1px solid var(--vt-line);
    background: #fff;
}
@media (max-width: 900px) {
    .vt-vm-body {
        grid-template-columns: 1fr;
        max-height: none;
    }
    .vt-ev-btns {
        grid-template-columns: 1fr;
    }
    .vt-flujo-p small {
        display: none;
    }
}
/* ----- Modal de visita: versión compacta y pareja ----- */
.vt-vm-head p {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
}
.vt-vm-reg {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding-left: 14px;
    border-left: 1px solid rgba(255, 255, 255, 0.3);
}
.vt-vm-reg b {
    color: #fff;
    text-transform: uppercase;
    font-size: 12px;
}
.vt-vm-head-der {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: none;
}
.vt-vm-head .vt-btn-hist {
    background: rgba(255, 255, 255, 0.14) !important;
    border-color: rgba(255, 255, 255, 0.35) !important;
    color: #fff !important;
}
.vt-vm-head .vt-btn-hist .anticon {
    color: #fff;
}
.vt-vm-info {
    padding: 16px 22px 12px;
}
.vt-paso {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 6px;
    padding: 12px 16px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--e) 7%, #fff);
    border: 1px solid color-mix(in srgb, var(--e) 30%, var(--vt-line));
}
.vt-paso-ic {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 18px;
    color: #fff;
    background: linear-gradient(135deg, #0461d8, #1597a5);
    flex: none;
}
.vt-vm-body {
    grid-template-columns: 1fr 1fr !important;
    align-items: stretch;
}
.vt-vm-col > .vt-panel-crece {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.vt-panel-obs .vt-obs-list {
    flex: 1;
    max-height: none;
    min-height: 120px;
    margin-top: 14px;
}
.vt-panel-obs .vt-obs-form {
    margin-top: 0;
    padding-top: 0;
    border-top: 0;
    padding-bottom: 14px;
    border-bottom: 1px dashed var(--vt-line);
}
.vt-datos {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.vt-datos > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    background: #f7fafc;
    border: 1px solid var(--vt-line);
    border-radius: 10px;
    min-width: 0;
}
.vt-datos span {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--vt-muted);
}
.vt-datos b {
    font-size: 13.5px;
    color: var(--vt-navy);
}
.vt-desc {
    margin-top: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    background: #fff8e8;
    border-left: 4px solid #f0a202;
}
.vt-desc span {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #a86f00;
}
.vt-desc p {
    margin: 2px 0 0;
    white-space: pre-line;
}
.vt-contacto {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.vt-contacto > a:first-child {
    flex: 1;
}

/* ===================== Estilo formal del modal ===================== */
.vt-vm-wrap .vt-vm-head {
    background: #fff;
    color: var(--vt-navy);
    border-bottom: 1px solid var(--vt-line);
    padding: 18px 24px;
}
.vt-vm-wrap .vt-vm-head h2 {
    color: var(--vt-navy);
    font-size: 21px;
}
.vt-vm-wrap .vt-vm-head p {
    color: #5d7085;
}
.vt-vm-wrap .vt-vm-reg {
    border-left-color: var(--vt-line);
}
.vt-vm-wrap .vt-vm-reg b {
    color: var(--vt-navy);
}
.vt-vm-wrap .vt-vm-ic {
    background: #0a1f44;
    color: #fff;
    box-shadow: none;
    border-radius: 12px;
}
.vt-vm-wrap .vt-vm-head .vt-tag.solido {
    box-shadow: none;
}
.vt-vm-wrap .vt-vm-head .vt-btn-hist {
    background: #fff !important;
    border-color: #d5dde7 !important;
    color: var(--vt-navy) !important;
}
.vt-vm-wrap .vt-vm-head .vt-btn-hist .anticon {
    color: #5d7085;
}
.vt-vm-wrap .vt-vm-x {
    background: #f1f4f8;
    color: #5d7085;
}
.vt-vm-wrap .vt-vm-x:hover {
    background: #e3e8ef;
    color: var(--vt-navy);
}
.vt-vm-wrap .vt-vm-info {
    background: #f8fafc;
}
/* Flujo sobrio: azul marino para pasos cumplidos, azul para el actual */
.vt-vm-wrap .vt-flujo-p {
    --s: #0a1f44 !important;
}
.vt-vm-wrap .vt-flujo-p.cur {
    --s: #0461d8 !important;
}
.vt-vm-wrap .vt-flujo-p.cur .vt-flujo-ic {
    animation: none;
    transform: none;
    box-shadow: 0 0 0 4px #dbe8fb;
}
.vt-vm-wrap .vt-flujo-p b {
    font-weight: 600;
}
.vt-vm-wrap .vt-paso {
    background: #fff;
    border-color: var(--vt-line);
}
.vt-vm-wrap .vt-paso-ic {
    background: #0a1f44;
}
.vt-vm-wrap .vt-btn-avanzar {
    background: #0461d8 !important;
}
/* Títulos de sección y datos en un solo tono */
.vt-vm-wrap .vt-panel {
    border-radius: 12px;
    box-shadow: none;
}
.vt-vm-wrap .vt-panel-t {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #44566c;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--vt-line);
}
.vt-vm-wrap .vt-panel-t > .anticon {
    background: #eef2f7 !important;
    color: #0a1f44;
}
.vt-vm-wrap .vt-datos > div {
    background: #fff;
}
.vt-vm-wrap .vt-datos span .anticon,
.vt-vm-wrap .vt-contacto .anticon {
    color: #5d7085 !important;
}
.vt-vm-wrap .vt-desc {
    background: #f8fafc;
    border-left-color: #0a1f44;
}
.vt-vm-wrap .vt-desc span {
    color: #44566c;
}
.vt-vm-wrap .vt-ev-btns .anticon {
    color: #0a1f44 !important;
}
.vt-vm-wrap .vt-obs {
    background: #fff;
    border-left-width: 3px;
}
.vt-vm-wrap .vt-vm-body {
    background: #f8fafc;
}

/* ----- Comentario con evidencia y acciones del proceso ----- */
.vt-obs-err {
    margin-top: 4px;
    font-size: 12.5px;
    color: #c0262e;
}
.vt-obs-auto {
    margin-top: 8px;
    padding: 6px 10px;
    border-radius: 8px;
    background: #eef5ff;
    border: 1px solid #cfe0fb;
    font-size: 12.5px;
    color: #1f3b66;
}
.vt-obs-auto .anticon {
    color: #0461d8;
}
.vt-adj {
    margin-top: 12px;
}
.vt-adj-t {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #44566c;
    margin-bottom: 6px;
}
.vt-adj-t em {
    font-style: normal;
    font-weight: 500;
    text-transform: none;
    color: var(--vt-muted);
}
.vt-adj-lista {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.vt-adj-f {
    position: relative;
    width: 64px;
    height: 64px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--vt-line);
}
.vt-adj-f img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.vt-adj-f button {
    position: absolute;
    top: 3px;
    right: 3px;
    width: 20px;
    height: 20px;
    border: 0;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 10px;
    color: #fff;
    background: rgba(10, 31, 68, 0.75);
    cursor: pointer;
}
.vt-adj-add {
    width: 64px;
    height: 64px;
    border-radius: 10px;
    border: 1.5px dashed #c9d4e0;
    background: #f8fafc;
    color: #44566c;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    font: 600 11px Figtree, sans-serif;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.vt-adj-add .anticon {
    font-size: 17px;
}
.vt-adj-add:hover {
    border-color: var(--vt-blue);
    background: var(--vt-blue-soft);
    color: var(--vt-blue);
}
.vt-obs-acc {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}
.vt-obs-aviso {
    margin-top: 8px;
    font-size: 12.5px;
    color: #a86f00;
    text-align: right;
}
.vt-bloqueo {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 14px;
    margin-bottom: 4px;
    border-radius: 10px;
    background: #f1f4f8;
    border: 1px solid var(--vt-line);
    color: #44566c;
}
.vt-bloqueo > .anticon {
    font-size: 18px;
    color: #0a1f44;
    margin-top: 2px;
}
.vt-bloqueo b {
    display: block;
    color: var(--vt-navy);
}
.vt-bloqueo span {
    font-size: 12.5px;
}

/* ----- Ubicación y adjuntos ----- */
.vt-mapa-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: var(--vt-muted);
    background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 10px, #f1f5f9 10px, #f1f5f9 20px);
    font-size: 13px;
}
.vt-mapa-vacio .anticon {
    font-size: 26px;
    color: #94a3b8;
}
.vt-ev-btns {
    grid-template-columns: 1fr;
}
.vt-adj-f.doc {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 4px;
    background: #fff;
}
.vt-adj-f.doc > .anticon {
    font-size: 24px;
}
.vt-adj-f.doc > span {
    max-width: 100%;
    font-size: 10px;
    color: #44566c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-adj-g {
    width: 56px;
    height: 56px;
    padding: 0;
    border-radius: 10px;
    border: 1px solid var(--vt-line);
    background: #fff;
    display: grid;
    place-items: center;
    overflow: hidden;
    font-size: 24px;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.vt-adj-g img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.vt-adj-g:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(10, 31, 68, 0.14);
}
.vt-obs-adj {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}
.vt-obs-adj-i {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 220px;
    height: 32px;
    padding: 0 10px 0 4px;
    border: 1px solid var(--vt-line);
    border-radius: 8px;
    background: #fff;
    font: 500 12.5px Figtree, sans-serif;
    color: #1f3b66;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.vt-obs-adj-i img {
    width: 24px;
    height: 24px;
    border-radius: 5px;
    object-fit: cover;
}
.vt-obs-adj-i > .anticon {
    width: 24px;
    font-size: 17px;
}
.vt-obs-adj-i span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-obs-adj-i:hover {
    border-color: var(--vt-blue);
    background: var(--vt-blue-soft);
}

/* ----- GPS en tiempo real y miniaturas ----- */
.vt-gps {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #ecfdf3;
    border: 1px solid #bfe9cf;
}
.vt-gps > div {
    display: flex;
    flex-direction: column;
    font-size: 13px;
    color: #1f3b2d;
}
.vt-gps small {
    color: var(--vt-muted);
    font-size: 11.5px;
}
.vt-gps-dot {
    flex: none;
    position: relative;
    width: 12px;
    height: 12px;
    margin-top: 4px;
    border-radius: 50%;
    background: #1faf5c;
}
.vt-gps-dot::after {
    content: '';
    position: absolute;
    inset: -5px;
    border-radius: 50%;
    border: 2px solid #1faf5c;
    animation: vt-gps-pulso 1.6s ease-out infinite;
}
.vt-gps.error {
    background: #fff7ed;
    border-color: #fed7aa;
}
.vt-gps.error .vt-gps-dot {
    background: #f08c00;
}
.vt-gps.error .vt-gps-dot::after {
    border-color: #f08c00;
}
@keyframes vt-gps-pulso {
    from { transform: scale(0.6); opacity: 1; }
    to { transform: scale(1.6); opacity: 0; }
}
.vt-obs-foto {
    width: 72px;
    height: 72px;
    padding: 0;
    border: 1px solid var(--vt-line);
    border-radius: 10px;
    overflow: hidden;
    cursor: zoom-in;
    background: #f1f5f9;
    transition: transform 0.15s, box-shadow 0.15s;
}
.vt-obs-foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.vt-obs-foto:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(10, 31, 68, 0.16);
}

/* ----- Columnas parejas: la derecha toma la altura de la izquierda ----- */
@media (min-width: 900px) {
    .vt-vm-col-der {
        contain: size;
        min-height: 460px;
    }
    .vt-vm-col-der > .vt-panel-obs {
        min-height: 0;
        overflow: hidden;
    }
    .vt-vm-col-der .vt-obs-list {
        min-height: 0;
    }
}
.vt-obs-adj {
    gap: 8px;
}
</style>
