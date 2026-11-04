<script setup lang="ts">
/**
 * Toma fotografías directamente con la cámara del dispositivo (computadora o teléfono).
 * Si el navegador no permite usar la cámara (p. ej. sin HTTPS), abre la cámara nativa del teléfono.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import { CameraOutlined, RetweetOutlined, CheckOutlined, ReloadOutlined, SwapOutlined } from '@ant-design/icons-vue';

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ foto: [file: File] }>();

const video = ref<HTMLVideoElement | null>(null);
const nativo = ref<HTMLInputElement | null>(null);
const stream = ref<MediaStream | null>(null);
const captura = ref<string | null>(null);
const blob = ref<Blob | null>(null);
const frontal = ref(false);
const iniciando = ref(false);
const error = ref<string | null>(null);

function detener() {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
}

async function iniciar() {
    detener();
    error.value = null;
    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'Este navegador no permite usar la cámara aquí (se requiere HTTPS). Usa la cámara del teléfono.';
        return;
    }
    iniciando.value = true;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: frontal.value ? 'user' : 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
            audio: false,
        });
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play();
        }
    } catch (e: any) {
        error.value = e?.name === 'NotAllowedError'
            ? 'Permite el acceso a la cámara en el navegador para tomar la foto.'
            : 'No se encontró una cámara disponible.';
    } finally {
        iniciando.value = false;
    }
}

function tomar() {
    const v = video.value;
    if (!v || !v.videoWidth) return;
    const c = document.createElement('canvas');
    c.width = v.videoWidth;
    c.height = v.videoHeight;
    c.getContext('2d')!.drawImage(v, 0, 0);
    c.toBlob((b) => {
        if (!b) return;
        blob.value = b;
        captura.value = URL.createObjectURL(b);
        detener();
    }, 'image/jpeg', 0.9);
}

function repetir() {
    if (captura.value) URL.revokeObjectURL(captura.value);
    captura.value = null;
    blob.value = null;
    iniciar();
}

function usar() {
    if (!blob.value) return;
    const nombre = `foto-${new Date().toISOString().replace(/[:.]/g, '-')}.jpg`;
    emit('foto', new File([blob.value], nombre, { type: 'image/jpeg' }));
    open.value = false;
}

function cambiarCamara() {
    frontal.value = !frontal.value;
    iniciar();
}

function desdeNativo(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (!f) return;
    if (!f.type.startsWith('image/')) return message.error('El archivo debe ser una imagen');
    emit('foto', f);
    open.value = false;
}

watch(open, (o) => {
    if (o) {
        captura.value = null;
        blob.value = null;
        setTimeout(iniciar, 50);
    } else {
        detener();
        if (captura.value) URL.revokeObjectURL(captura.value);
    }
});
onBeforeUnmount(detener);
</script>

<template>
    <a-modal v-model:open="open" :footer="null" width="min(720px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #7c4ddb, #e0457b)"><CameraOutlined /></span>
                <div>Tomar fotografía<small>Usa la cámara del dispositivo</small></div>
            </div>
        </template>

        <div class="vt-cam">
            <img v-if="captura" :src="captura" alt="Foto tomada" />
            <video v-show="!captura && !error" ref="video" playsinline muted></video>
            <div v-if="error" class="vt-cam-error">
                <CameraOutlined style="font-size: 40px" />
                <p>{{ error }}</p>
                <a-button type="primary" size="large" @click="nativo?.click()"><CameraOutlined /> Abrir cámara del teléfono</a-button>
                <a-button size="large" @click="iniciar"><ReloadOutlined /> Reintentar</a-button>
            </div>
            <a-spin v-if="iniciando" class="vt-cam-spin" />
        </div>
        <input ref="nativo" type="file" accept="image/*" capture="environment" class="hidden" @change="desdeNativo" />

        <div class="flex justify-center gap-3 mt-4 flex-wrap">
            <template v-if="!captura">
                <a-button size="large" :disabled="!!error" @click="cambiarCamara"><SwapOutlined /> Cambiar cámara</a-button>
                <a-button type="primary" size="large" class="vt-cam-shot" :disabled="!stream" @click="tomar"><CameraOutlined /> Tomar foto</a-button>
            </template>
            <template v-else>
                <a-button size="large" @click="repetir"><RetweetOutlined /> Tomar otra</a-button>
                <a-button type="primary" size="large" @click="usar"><CheckOutlined /> Usar esta foto</a-button>
            </template>
        </div>
    </a-modal>
</template>

<style>
.vt-cam {
    position: relative;
    background: #0a1f44;
    border-radius: 14px;
    overflow: hidden;
    min-height: 360px;
    display: grid;
    place-items: center;
    margin-top: 10px;
}
.vt-cam video,
.vt-cam img {
    width: 100%;
    max-height: 62vh;
    object-fit: contain;
    display: block;
}
.vt-cam-error {
    color: #d6e3f5;
    text-align: center;
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.vt-cam-spin {
    position: absolute;
}
.vt-cam-shot {
    min-width: 180px;
}
</style>
