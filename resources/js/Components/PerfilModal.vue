<script setup lang="ts">
/** "Mi perfil": el usuario autenticado cambia su correo o su contraseña (confirmando la contraseña actual). */
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    UserOutlined, MailOutlined, LockOutlined, SafetyOutlined, SaveOutlined, CloseOutlined, ThunderboltOutlined, CopyOutlined,
    CheckCircleOutlined, ExclamationCircleFilled, KeyOutlined, ShopOutlined, IdcardOutlined,
} from '@ant-design/icons-vue';
import { useShared } from '@/composables/useShared';
import { iniciales, generarContrasena } from '@/utils';

const open = defineModel<boolean>('open', { default: false });
const { user } = useShared();
const pestana = ref('correo');

const fCorreo = useForm({ email: '', password_actual: '' });
const fPass = useForm({ password_actual: '', password: '', password_confirmation: '' });
const verPass = ref(false);

watch(open, (o) => {
    if (!o) return;
    pestana.value = 'correo';
    fCorreo.clearErrors();
    fPass.clearErrors();
    fCorreo.email = user.value.email;
    fCorreo.password_actual = '';
    fPass.reset();
    verPass.value = false;
});

function guardarCorreo() {
    fCorreo.put('/perfil/correo', { preserveScroll: true, onSuccess: () => (open.value = false), onFinish: () => (fCorreo.password_actual = '') });
}
function guardarPass() {
    if (fPass.password !== fPass.password_confirmation) {
        fPass.setError('password_confirmation', 'Las contraseñas no coinciden.');
        return;
    }
    fPass.put('/perfil/contrasena', { preserveScroll: true, onSuccess: () => (open.value = false), onFinish: () => fPass.reset('password_actual') });
}
const guardar = () => (pestana.value === 'correo' ? guardarCorreo() : guardarPass());
const procesando = computed(() => fCorreo.processing || fPass.processing);

function generar() {
    const p = generarContrasena(12);
    fPass.password = p;
    fPass.password_confirmation = p;
    verPass.value = true;
    navigator.clipboard?.writeText(p).then(() => message.success('Contraseña generada y copiada')).catch(() => message.success('Contraseña generada'));
}
async function copiar() {
    await navigator.clipboard?.writeText(fPass.password);
    message.success('Contraseña copiada');
}
const fuerza = computed(() => {
    const p = fPass.password || '';
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
const COLOR_PERFIL: Record<string, string> = {
    Administrador: '#0461d8', Supervisor: '#7c4ddb', Auditor: '#1597a5', Promotor: '#1faf5c', Prospecto: '#f08c00',
};
const err = (f: any, k: string) => ({ 'validate-status': f.errors[k] ? 'error' : '', help: f.errors[k] });
</script>

<template>
    <a-modal v-model:open="open" width="min(620px, 96vw)" centered destroy-on-close wrap-class-name="vt-perfil-modal">
        <template #title>
            <div class="vt-pm-head" :style="{ '--p': COLOR_PERFIL[user.perfil] }">
                <span class="vt-pm-av">{{ iniciales(user.nombre_completo) }}</span>
                <div class="min-w-0">
                    <div class="vt-pm-nombre">{{ user.nombre_completo }}</div>
                    <div class="vt-pm-meta">
                        <span><SafetyOutlined /> {{ user.perfil }}</span>
                        <span><MailOutlined /> {{ user.email }}</span>
                        <span><ShopOutlined /> {{ user.sucursales.join(', ') }}</span>
                    </div>
                </div>
            </div>
        </template>

        <a-tabs v-model:active-key="pestana" class="vt-tabs-form mt-2">
            <a-tab-pane key="correo">
                <template #tab><span class="vt-tab"><MailOutlined /> Mi correo <ExclamationCircleFilled v-if="Object.keys(fCorreo.errors).length" class="vt-tab-err" /></span></template>
                <p class="vt-muted vt-small mb-4"><IdcardOutlined /> El correo es tu usuario para iniciar sesión. Para cambiarlo confirma tu contraseña actual.</p>
                <a-form layout="vertical" @submit.prevent="guardarCorreo">
                    <a-form-item label="Correo electrónico" required v-bind="err(fCorreo, 'email')">
                        <a-input v-model:value="fCorreo.email" size="large" type="email" autocomplete="email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input>
                    </a-form-item>
                    <a-form-item label="Contraseña actual" required v-bind="err(fCorreo, 'password_actual')">
                        <a-input-password v-model:value="fCorreo.password_actual" size="large" autocomplete="current-password"><template #prefix><KeyOutlined style="color: #f0a202" /></template></a-input-password>
                    </a-form-item>
                </a-form>
            </a-tab-pane>

            <a-tab-pane key="contrasena">
                <template #tab><span class="vt-tab"><LockOutlined /> Mi contraseña <ExclamationCircleFilled v-if="Object.keys(fPass.errors).length" class="vt-tab-err" /></span></template>
                <p class="vt-muted vt-small mb-4"><SafetyOutlined /> Usa al menos 8 caracteres con letras y números. Puedes generar una contraseña segura.</p>
                <a-form layout="vertical" @submit.prevent="guardarPass">
                    <a-form-item label="Contraseña actual" required v-bind="err(fPass, 'password_actual')">
                        <a-input-password v-model:value="fPass.password_actual" size="large" autocomplete="current-password"><template #prefix><KeyOutlined style="color: #f0a202" /></template></a-input-password>
                    </a-form-item>
                    <div class="vt-form-grid">
                        <a-form-item label="Nueva contraseña" required v-bind="err(fPass, 'password')">
                            <a-input-password v-model:value="fPass.password" v-model:visible="verPass" size="large" autocomplete="new-password"><template #prefix><LockOutlined style="color: #0461d8" /></template></a-input-password>
                        </a-form-item>
                        <a-form-item label="Confirmar nueva contraseña" required v-bind="err(fPass, 'password_confirmation')">
                            <a-input-password v-model:value="fPass.password_confirmation" v-model:visible="verPass" size="large" autocomplete="new-password"
                                :status="fPass.password_confirmation && fPass.password_confirmation !== fPass.password ? 'error' : ''">
                                <template #prefix><LockOutlined style="color: #1faf5c" /></template>
                            </a-input-password>
                        </a-form-item>
                    </div>
                    <div class="vt-pass-tools">
                        <a-button @click="generar"><ThunderboltOutlined style="color: #f0a202" /> Generar contraseña segura</a-button>
                        <a-button v-if="fPass.password" @click="copiar"><CopyOutlined style="color: #0461d8" /> Copiar</a-button>
                        <div v-if="fPass.password" class="vt-fuerza">
                            <div class="vt-fuerza-bar"><i v-for="n in 5" :key="n" :style="{ background: n <= fuerza ? COLOR_FUERZA[fuerza] : '#e3e8ee' }"></i></div>
                            <span :style="{ color: COLOR_FUERZA[fuerza] }">{{ FUERZA[fuerza] }}</span>
                        </div>
                        <span v-if="fPass.password_confirmation" class="vt-small" :style="{ color: fPass.password === fPass.password_confirmation ? '#1faf5c' : '#e5484d' }">
                            <CheckCircleOutlined v-if="fPass.password === fPass.password_confirmation" /><ExclamationCircleFilled v-else />
                            {{ fPass.password === fPass.password_confirmation ? 'Las contraseñas coinciden' : 'No coinciden' }}
                        </span>
                    </div>
                </a-form>
            </a-tab-pane>
        </a-tabs>

        <template #footer>
            <div class="flex justify-between items-center gap-2 flex-wrap">
                <span class="vt-small vt-muted"><UserOutlined /> El cambio quedará registrado en la bitácora.</span>
                <div class="flex gap-2">
                    <a-button size="large" @click="open = false"><CloseOutlined /> Cancelar</a-button>
                    <a-button size="large" type="primary" :loading="procesando" @click="guardar"><SaveOutlined /> {{ pestana === 'correo' ? 'Guardar correo' : 'Cambiar contraseña' }}</a-button>
                </div>
            </div>
        </template>
    </a-modal>
</template>

<style>
.vt-pm-head {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: -20px -24px 0;
    padding: 20px 24px;
    border-radius: 8px 8px 0 0;
    color: #fff;
    background: linear-gradient(120deg, #0a1f44, var(--p));
}
.vt-pm-av {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font: 800 20px 'Bricolage Grotesque', sans-serif;
    background: rgba(255, 255, 255, 0.18);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.35);
    flex: none;
}
.vt-pm-nombre {
    font-size: 19px;
    font-weight: 700;
}
.vt-pm-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    font-size: 12.5px;
    font-weight: 400;
    color: #d6e3f5;
}
.vt-perfil-modal .ant-modal-header {
    margin-bottom: 0;
}
</style>
