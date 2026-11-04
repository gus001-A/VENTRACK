<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    MailOutlined, LockOutlined, LoginOutlined, EnvironmentOutlined, CameraOutlined, HistoryOutlined,
    SafetyCertificateOutlined, ThunderboltOutlined, SendOutlined, CheckCircleFilled, ArrowLeftOutlined, KeyOutlined,
} from '@ant-design/icons-vue';

defineProps<{ demo: { id: number; name: string; apellido_paterno: string | null; email: string; perfil: string }[] }>();

const form = useForm({ email: '', password: '', remember: false });
const aviso = computed(() => (usePage().props as any).flash?.success as string | null);

// Recuperación de acceso dentro de la misma tarjeta: login → recuperar → enviado
const vista = ref<'login' | 'recuperar' | 'enviado'>('login');
const recuperar = useForm({ email: '' });
function abrirOlvido() {
    recuperar.email = form.email;
    recuperar.clearErrors();
    vista.value = 'recuperar';
}
function volver() {
    if (recuperar.email) form.email = recuperar.email;
    vista.value = 'login';
}
function enviarEnlace() {
    recuperar.post('/olvide-contrasena', { preserveScroll: true, onSuccess: () => (vista.value = 'enviado') });
}
const anio = new Date().getFullYear();

function entrar() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
function demoLogin(email: string) {
    form.email = email;
    form.password = 'password';
    entrar();
}

const features = [
    { icon: EnvironmentOutlined, t: 'Visitas en campo', d: 'Agenda, lista de trabajo y alertas de incumplimiento.' },
    { icon: CameraOutlined, t: 'Evidencia en tiempo real', d: 'Fotos y geolocalización desde el teléfono.' },
    { icon: HistoryOutlined, t: 'Trazabilidad completa', d: 'Usuario, fecha y hora de cada movimiento.' },
];
</script>

<template>
    <Head title="Iniciar sesión" />
    <div class="lg-wrap">
        <!-- Panel de marca -->
        <section class="lg-brand">
            <span class="lg-corner tl"></span><span class="lg-corner bl"></span>
            <div class="lg-dots"></div>
            <div class="lg-ring"></div>
            <div class="lg-glow g1"></div><div class="lg-glow g2"></div>

            <div class="lg-brand-in">
                <div class="lg-logo-card"><img src="/images/ventrack-logo.webp" alt="VENTRACK · Promoción de ventas en tiempo real" /></div>
                <div class="lg-line"></div>
                <p class="lg-tag">Sistema de seguimiento de <b>visitas, promoción</b> y <b>ventas</b> en tiempo real</p>
                <div class="lg-feats">
                    <div v-for="f in features" :key="f.t" class="lg-feat">
                        <span class="lg-feat-ic"><component :is="f.icon" /></span>
                        <b>{{ f.t }}</b>
                        <small>{{ f.d }}</small>
                    </div>
                </div>
                <div class="lg-copy">© VENTRACK {{ anio }}</div>
            </div>
            <svg class="lg-curve" viewBox="0 0 100 1000" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient id="lg-borde" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#0461d8" />
                        <stop offset="0.55" stop-color="#1597a5" />
                        <stop offset="1" stop-color="#2dcb6a" />
                    </linearGradient>
                </defs>
                <path d="M62 0 C 2 250, 2 750, 62 1000" fill="none" stroke="url(#lg-borde)" stroke-width="5" vector-effect="non-scaling-stroke" opacity="0.9" />
                <path d="M100 0 L66 0 C 6 250, 6 750, 66 1000 L100 1000 Z" fill="#fff" />
            </svg>
        </section>

        <!-- Formulario -->
        <section class="lg-form">
            <div class="lg-card">
                <img src="/images/ventrack-icono.webp" alt="" class="lg-mobile-logo" />
                <span class="lg-chip"><EnvironmentOutlined /> Promoción de ventas en tiempo real</span>
                <Transition name="lg-vista" mode="out-in">
                    <!-- Iniciar sesión -->
                    <div v-if="vista === 'login'" key="login">
                        <div class="lg-title">
                            <h1>Bienvenido</h1>
                            <p>Ingresa a tu cuenta para acceder al sistema</p>
                        </div>

                        <a-form layout="vertical" @submit.prevent="entrar" style="margin-top: 30px">
                            <a-form-item label="Correo electrónico" :validate-status="form.errors.email ? 'error' : ''" :help="form.errors.email">
                                <a-input v-model:value="form.email" size="large" type="email" autocomplete="username" placeholder="tu@correo.com" class="lg-input">
                                    <template #prefix><MailOutlined class="lg-pre" /></template>
                                </a-input>
                            </a-form-item>
                            <a-form-item label="Contraseña" :validate-status="form.errors.password ? 'error' : ''" :help="form.errors.password">
                                <a-input-password v-model:value="form.password" size="large" autocomplete="current-password" placeholder="••••••••" class="lg-input">
                                    <template #prefix><LockOutlined class="lg-pre" /></template>
                                </a-input-password>
                            </a-form-item>
                            <div class="flex items-center justify-between -mt-1 mb-5">
                                <a-checkbox v-model:checked="form.remember">Recordarme</a-checkbox>
                                <a class="lg-link" @click.prevent="abrirOlvido">¿Olvidaste tu contraseña?</a>
                            </div>
                            <a-button type="primary" html-type="submit" size="large" block class="lg-btn" :loading="form.processing">
                                <LoginOutlined /> Iniciar sesión
                            </a-button>
                        </a-form>

                        <a-alert v-if="aviso" type="success" show-icon :message="aviso" class="mt-4" />
                        <div class="lg-secure"><SafetyCertificateOutlined /> Conexión protegida · cada acceso queda registrado</div>
                    </div>

                    <!-- Recuperar contraseña -->
                    <div v-else-if="vista === 'recuperar'" key="recuperar">
                        <div class="lg-title">
                            <h1>Recupera tu acceso</h1>
                            <p>Escribe el correo con el que entras a VENTRACK y te enviaremos un enlace para crear una nueva contraseña.</p>
                        </div>
                        <a-form layout="vertical" @submit.prevent="enviarEnlace" style="margin-top: 30px">
                            <a-form-item label="Correo electrónico" :validate-status="recuperar.errors.email ? 'error' : ''" :help="recuperar.errors.email">
                                <a-input v-model:value="recuperar.email" size="large" type="email" autocomplete="username" placeholder="tu@correo.com" class="lg-input" autofocus
                                    @change="recuperar.clearErrors('email')">
                                    <template #prefix><MailOutlined class="lg-pre" /></template>
                                </a-input>
                            </a-form-item>
                            <a-button type="primary" html-type="submit" size="large" block class="lg-btn" :loading="recuperar.processing" :disabled="!recuperar.email">
                                <SendOutlined /> Enviar enlace de recuperación
                            </a-button>
                        </a-form>
                        <a class="lg-volver" @click.prevent="volver"><ArrowLeftOutlined /> Volver a iniciar sesión</a>
                    </div>

                    <!-- Enlace enviado -->
                    <div v-else key="enviado" class="lg-enviado">
                        <span class="lg-enviado-ic"><CheckCircleFilled /></span>
                        <h1>Revisa tu correo</h1>
                        <p>Enviamos un enlace de recuperación a <b>{{ recuperar.email }}</b>. Ábrelo para crear tu nueva contraseña; vence en 60 minutos.</p>
                        <div class="lg-enviado-tip"><KeyOutlined /> Si no lo ves en tu bandeja de entrada, revisa la carpeta de spam.</div>
                        <a-button size="large" block class="mt-4" :loading="recuperar.processing" @click="enviarEnlace"><SendOutlined /> Reenviar enlace</a-button>
                        <a class="lg-volver" @click.prevent="volver"><ArrowLeftOutlined /> Volver a iniciar sesión</a>
                    </div>
                </Transition>

                <div v-if="demo.length && vista === 'login'" class="lg-demo">
                    <div class="lg-demo-t"><ThunderboltOutlined /> Accesos de demostración (solo desarrollo)</div>
                    <div class="flex flex-wrap gap-2 justify-center">
                        <a-button v-for="u in demo" :key="u.id" shape="round" @click="demoLogin(u.email)">{{ u.perfil }}</a-button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style>
.lg-wrap {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #fff;
}
.lg-brand {
    position: relative;
    overflow: hidden;
    background: linear-gradient(160deg, #0a1f44 0%, #0d3270 38%, #0f5a78 70%, #138a6c 100%);
    color: #fff;
    display: grid;
    place-items: center;
    padding: 48px 90px 48px 48px;
}
.lg-curve {
    position: absolute;
    top: 0;
    right: -1px;
    height: 100%;
    width: 140px;
}
.lg-corner {
    position: absolute;
    width: 28px;
    height: 28px;
    border-color: rgba(255, 255, 255, 0.85);
    border-style: solid;
    border-width: 0;
}
.lg-corner.tl { top: 20px; left: 22px; border-top-width: 2px; border-left-width: 2px; border-top-left-radius: 8px; }
.lg-corner.bl { bottom: 20px; left: 22px; border-bottom-width: 2px; border-left-width: 2px; border-bottom-left-radius: 8px; }
.lg-dots {
    position: absolute;
    top: 40px;
    left: 70px;
    width: 140px;
    height: 100px;
    background-image: radial-gradient(rgba(255, 255, 255, 0.35) 1.4px, transparent 1.4px);
    background-size: 18px 18px;
}
.lg-ring {
    position: absolute;
    left: 80px;
    top: 36%;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    border: 2px dashed rgba(255, 255, 255, 0.18);
    animation: lg-spin 60s linear infinite;
}
.lg-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.55;
}
.lg-glow.g1 { width: 340px; height: 340px; right: 10%; top: -80px; background: #4c7bb0; }
.lg-glow.g2 { width: 360px; height: 360px; left: -80px; bottom: -120px; background: #2dcb6a; opacity: 0.35; }
.lg-brand-in {
    position: relative;
    z-index: 1;
    max-width: 540px;
    text-align: center;
    animation: vt-rise 0.6s both;
}
.lg-logo-card {
    background: #fff;
    border-radius: 26px;
    padding: 30px 44px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3), 0 0 0 8px rgba(255, 255, 255, 0.08);
    display: inline-block;
    animation: lg-float 6s ease-in-out infinite;
}
@keyframes lg-float {
    50% { transform: translateY(-6px); }
}
.lg-feat {
    padding: 12px;
    border-radius: 14px;
    transition: background 0.2s, transform 0.2s;
}
.lg-feat:hover {
    background: rgba(255, 255, 255, 0.07);
    transform: translateY(-3px);
}
.lg-logo-card img {
    width: 380px;
    max-width: 100%;
    display: block;
}
.lg-line {
    width: 84px;
    height: 4px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.75);
    margin: 30px auto 24px;
}
.lg-tag {
    font-size: 16px;
    color: #d6e3f5;
    max-width: 380px;
    margin: 0 auto 34px;
    line-height: 1.5;
}
.lg-tag b { color: #fff; }
.lg-feats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    text-align: left;
}
.lg-feat-ic {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    border: 1.5px solid rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.08);
    font-size: 17px;
    margin-bottom: 10px;
}
.lg-feat b {
    display: block;
    font: 700 13.5px 'Bricolage Grotesque', sans-serif;
}
.lg-feat small {
    display: block;
    color: #b9cbe3;
    font-size: 12px;
    line-height: 1.45;
    margin-top: 3px;
}
.lg-copy {
    margin-top: 36px;
    font-size: 12px;
    letter-spacing: 0.06em;
    color: #9fb3cf;
}
.lg-form {
    display: grid;
    place-items: center;
    padding: 40px 24px;
}
.lg-card {
    width: min(420px, 100%);
    animation: vt-rise 0.45s 0.1s both;
}
.lg-mobile-logo {
    display: none;
    width: 70px;
    margin: 0 auto 18px;
}
.lg-title {
    border-left: 4px solid;
    border-image: linear-gradient(180deg, #0461d8, #2dcb6a) 1;
    padding-left: 16px;
}
.lg-title h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0;
    color: var(--vt-navy);
    line-height: 1.1;
}
.lg-title p {
    margin: 4px 0 0;
    color: var(--vt-muted);
}
.lg-input {
    border-radius: 12px !important;
    background: #f7f9fc !important;
    height: 50px;
    border-color: #dfe6ef !important;
    transition: box-shadow 0.2s, border-color 0.2s, background 0.2s !important;
}
.lg-input:hover,
.lg-input.ant-input-affix-wrapper-focused {
    background: #fff !important;
    border-color: var(--vt-blue) !important;
    box-shadow: 0 0 0 4px rgba(4, 97, 216, 0.1) !important;
}
.lg-card .ant-form-item-label > label {
    font-weight: 600;
    color: var(--vt-navy);
    font-size: 13px;
}
.lg-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--vt-green-dark);
    background: var(--vt-green-soft);
    padding: 4px 12px;
    border-radius: 99px;
    margin-bottom: 18px;
}
.lg-input input {
    background: transparent !important;
}
.lg-pre {
    color: var(--vt-blue);
    margin-right: 4px;
}
.lg-link {
    font-weight: 600;
    color: var(--vt-navy) !important;
    font-size: 13px;
}
.lg-link:hover {
    color: var(--vt-blue) !important;
}
.lg-btn {
    height: 50px !important;
    border-radius: 12px !important;
    border: 0 !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    background: linear-gradient(90deg, #0a2f66 0%, #0461d8 45%, #1fa86a 100%) !important;
    box-shadow: 0 10px 24px rgba(4, 97, 216, 0.28) !important;
}
.lg-btn:hover {
    filter: brightness(1.08);
}
.lg-secure {
    margin-top: 16px;
    text-align: center;
    font-size: 12px;
    color: var(--vt-muted);
}
.lg-secure .anticon {
    color: var(--vt-green-dark);
}
.lg-demo {
    margin-top: 26px;
    padding: 14px;
    border: 1.5px dashed var(--vt-line);
    border-radius: 14px;
}
.lg-demo-t {
    text-align: center;
    font-size: 12px;
    color: var(--vt-muted);
    margin-bottom: 10px;
}
.lg-demo-t .anticon {
    color: #f0a202;
}
@keyframes lg-spin {
    to { transform: rotate(360deg); }
}
@media (max-width: 960px) {
    .lg-wrap { grid-template-columns: 1fr; }
    .lg-brand { display: none; }
    .lg-mobile-logo { display: block; }
}

/* ----- Recuperación de acceso en la misma tarjeta ----- */
.lg-volver {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 18px;
    font-weight: 600;
    color: #0461d8;
    cursor: pointer;
}
.lg-enviado {
    text-align: center;
}
.lg-enviado h1 {
    margin: 10px 0 6px;
    font: 800 26px 'Bricolage Grotesque', sans-serif;
    color: #0a1f44;
}
.lg-enviado p {
    color: #5d7085;
    margin: 0;
}
.lg-enviado-ic {
    display: inline-grid;
    place-items: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    font-size: 40px;
    color: #1faf5c;
    background: #e9f9ef;
    animation: lg-pop 0.4s ease;
}
.lg-enviado-tip {
    margin-top: 16px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f3f6fa;
    font-size: 13px;
    color: #44566c;
}
.lg-vista-enter-active,
.lg-vista-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.lg-vista-enter-from {
    opacity: 0;
    transform: translateX(16px);
}
.lg-vista-leave-to {
    opacity: 0;
    transform: translateX(-16px);
}
@keyframes lg-pop {
    from { transform: scale(0.6); opacity: 0; }
    to { transform: none; opacity: 1; }
}
</style>
