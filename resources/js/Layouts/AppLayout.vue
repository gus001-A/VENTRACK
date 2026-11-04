<script setup lang="ts">
import { computed, nextTick, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { sinBase } from '@/base';
import { message } from 'ant-design-vue';
import {
    AppstoreOutlined, CalendarOutlined, EnvironmentOutlined, TeamOutlined, ShopOutlined, UserOutlined,
    GiftOutlined, BarChartOutlined, UnorderedListOutlined, HistoryOutlined, MessageOutlined, MenuOutlined, LogoutOutlined,
    DownOutlined, MailOutlined, IdcardOutlined, SettingOutlined, LockOutlined, BellOutlined, WarningOutlined, EyeOutlined, SafetyOutlined, CheckCircleOutlined,
} from '@ant-design/icons-vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PerfilModal from '@/Components/PerfilModal.vue';
import { confirmar } from '@/confirmar';
import { useShared } from '@/composables/useShared';
import { iniciales } from '@/utils';

const page = usePage();
const { props, user } = useShared();

type Item = { key: string; label: string; href: string; icon: any; color: string; perfiles: string[]; badge?: boolean; tag?: string };
const TODOS = ['Administrador', 'Supervisor', 'Auditor', 'Promotor', 'Prospecto'];
const INTERNOS = ['Administrador', 'Supervisor', 'Auditor', 'Promotor'];
const GESTION = ['Administrador', 'Supervisor', 'Auditor'];

const items: Item[] = [
    { key: 'panel', label: 'Panel', href: '/', icon: AppstoreOutlined, color: '#0461D8', perfiles: TODOS },
    { key: 'agenda', label: 'Agenda', href: '/agenda', icon: CalendarOutlined, color: '#7C4DDB', perfiles: INTERNOS },
    { key: 'visitas', label: 'Visitas', href: '/visitas', icon: EnvironmentOutlined, color: '#1FAF5C', perfiles: TODOS },
    { key: 'prospectos', label: 'Prospectos', href: '/prospectos', icon: TeamOutlined, color: '#F08C00', perfiles: INTERNOS },
    { key: 'sucursales', label: 'Sucursales', href: '/sucursales', icon: ShopOutlined, color: '#1597A5', perfiles: GESTION },
    { key: 'usuarios', label: 'Usuarios', href: '/usuarios', icon: UserOutlined, color: '#3F51B5', perfiles: ['Administrador'], tag: 'Admin' },
    { key: 'productos', label: 'Productos', href: '/productos', icon: GiftOutlined, color: '#E0457B', perfiles: TODOS },
    { key: 'reportes', label: 'Reportes', href: '/reportes', icon: BarChartOutlined, color: '#D9363E', perfiles: INTERNOS },
    { key: 'catalogos', label: 'Catálogos', href: '/catalogos', icon: UnorderedListOutlined, color: '#0E9F8E', perfiles: ['Administrador'], tag: 'Admin' },
    { key: 'mensajes', label: 'Mensajes', href: '/mensajes', icon: MessageOutlined, color: '#22B573', perfiles: ['Promotor', 'Prospecto'], badge: true },
];

const visibles = computed(() => items.filter((i) => i.perfiles.includes(user.value.perfil)));

const activo = computed(() => {
    const path = sinBase(page.url).split('?')[0];
    const hit = visibles.value.filter((i) => i.href !== '/').find((i) => path.startsWith(i.href));
    return hit ? hit.key : 'panel';
});

const drawer = ref(false);
const mobile = ref(false);
// Nivel del menú según el espacio real: 0 completo, 1 sin etiquetas, 2 solo iconos (con tooltip)
const nivel = ref(0);
const compacto = computed(() => nivel.value >= 2);
const sinTags = computed(() => nivel.value >= 1);
const navRef = ref<HTMLElement | null>(null);
async function medir() {
    mobile.value = window.innerWidth < 820;
    if (mobile.value) return;
    for (const n of [0, 1, 2]) {
        nivel.value = n;
        await nextTick();
        const el = navRef.value;
        if (!el) break;
        const ancho = Array.from(el.children).reduce((t, c) => t + (c as HTMLElement).offsetWidth + 2, 0);
        if (ancho <= el.clientWidth) break;
    }
}
let observador: ResizeObserver | null = null;
let midiendo = false;
async function remedir() {
    if (midiendo) return;
    midiendo = true;
    await medir();
    midiendo = false;
}
onMounted(() => {
    remedir();
    window.addEventListener('resize', remedir);
    document.fonts?.ready.then(remedir);
    // Recalcula cuando cambia el ancho real de la barra (fuentes, zoom, emulación, etc.)
    const barra = document.querySelector('.vt-nav-in');
    if (barra && 'ResizeObserver' in window) {
        let ancho = 0;
        observador = new ResizeObserver(([e]) => {
            if (Math.abs(e.contentRect.width - ancho) > 1) {
                ancho = e.contentRect.width;
                remedir();
            }
        });
        observador.observe(barra);
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', remedir);
    observador?.disconnect();
});
watch(() => page.url, () => {
    drawer.value = false;
    remedir();
});

const perfil = ref(false);
const menuUsuario = ref(false);

async function salir() {
    const ok = await confirmar({ tipo: 'question', titulo: '¿Cerrar sesión?', texto: 'Tendrás que volver a ingresar tu correo y contraseña.', confirmar: 'Sí, salir' });
    if (ok) router.post('/logout');
}

// Campana de notificaciones
const alertas = computed(() => (props.value as any).alertas ?? { vencidas: 0, hoy: 0, porRevisar: 0 });
const totalAlertas = computed(() => alertas.value.vencidas + alertas.value.porRevisar + (props.value.mensajesSinLeer || 0));
const COLOR_HEX: Record<string, string> = {
    Administrador: '#0461d8', Supervisor: '#7c4ddb', Auditor: '#1597a5', Promotor: '#1faf5c', Prospecto: '#f08c00',
};

// Mensajes flash del servidor
watch(
    () => props.value.flash,
    (f) => {
        if (f?.success) message.success(f.success);
        if (f?.error) message.error(f.error);
    },
    { deep: true, immediate: true },
);

const sucursalesTxt = computed(() => {
    const s = user.value.sucursales || [];
    return s.length > 2 ? `${s.length} sucursales` : s.join(', ');
});

const COLOR_PERFIL: Record<string, string> = {
    Administrador: 'blue', Supervisor: 'purple', Auditor: 'cyan', Promotor: 'green', Prospecto: 'orange',
};
</script>

<template>
    <div class="vt-app">
        <header class="vt-nav">
            <div class="vt-nav-top"></div>
            <div class="vt-nav-in">
                <a-button v-if="mobile" type="text" class="vt-nav-burger" aria-label="Menú" @click="drawer = true"><MenuOutlined /></a-button>
                <Link href="/" class="vt-nav-logo"><img :src="compacto && !mobile ? '/images/ventrack-icono.webp' : '/images/ventrack-logo.webp'" alt="VENTRACK" /></Link>

                <nav v-if="!mobile" ref="navRef" class="vt-nav-links">
                    <a-tooltip v-for="(i, n) in visibles" :key="i.key" :title="compacto && activo !== i.key ? i.label : ''" placement="bottom">
                        <Link :href="i.href" class="vt-nav-link" :class="{ on: activo === i.key }" :style="{ '--c': i.color, '--i': n }">
                            <component :is="i.icon" class="vt-nav-ic" />
                            <span v-if="!compacto || activo === i.key">{{ i.label }}</span>
                            <span v-if="i.tag && !sinTags" class="vt-nav-tag" :class="i.tag">{{ i.tag }}</span>
                            <span v-if="i.badge && props.mensajesSinLeer" class="vt-badge">{{ props.mensajesSinLeer }}</span>
                        </Link>
                    </a-tooltip>
                </nav>

                <div class="vt-nav-right">
                    <!-- Notificaciones -->
                    <a-dropdown placement="bottomRight" :trigger="['click']">
                        <button class="vt-bell" :class="{ hay: totalAlertas }" aria-label="Notificaciones">
                            <BellOutlined />
                            <span v-if="totalAlertas" class="vt-bell-n">{{ totalAlertas > 99 ? '99+' : totalAlertas }}</span>
                        </button>
                        <template #overlay>
                            <div class="vt-noti">
                                <div class="vt-noti-h"><BellOutlined /> Notificaciones</div>
                                <Link v-if="alertas.vencidas" href="/visitas?vencidas=1" class="vt-noti-i" style="--c: #e5484d">
                                    <span class="ic"><WarningOutlined /></span><span><b>{{ alertas.vencidas }} visita(s) vencida(s)</b><small>Programadas en fechas pasadas</small></span>
                                </Link>
                                <Link v-if="alertas.hoy" href="/agenda?vista=lista" class="vt-noti-i" style="--c: #0461d8">
                                    <span class="ic"><CalendarOutlined /></span><span><b>{{ alertas.hoy }} visita(s) para hoy</b><small>Pendientes de realizar</small></span>
                                </Link>
                                <Link v-if="alertas.porRevisar" href="/visitas?estatus=realizada" class="vt-noti-i" style="--c: #1597a5">
                                    <span class="ic"><EyeOutlined /></span><span><b>{{ alertas.porRevisar }} por revisar</b><small>Visitas realizadas esperan validación</small></span>
                                </Link>
                                <Link v-if="props.mensajesSinLeer" href="/mensajes" class="vt-noti-i" style="--c: #1faf5c">
                                    <span class="ic"><MessageOutlined /></span><span><b>{{ props.mensajesSinLeer }} mensaje(s) sin leer</b><small>De tus prospectos o promotores</small></span>
                                </Link>
                                <div v-if="!totalAlertas && !alertas.hoy" class="vt-noti-vacio"><CheckCircleOutlined /> Todo en orden</div>
                            </div>
                        </template>
                    </a-dropdown>

                    <!-- Usuario autenticado -->
                    <a-dropdown v-model:open="menuUsuario" placement="bottomRight" :trigger="['click']">
                        <button class="vt-user">
                            <span class="vt-av vt-av-nav" :style="{ background: COLOR_HEX[user.perfil] }">{{ iniciales(user.nombre_completo) }}</span>
                            <span class="vt-user-txt"><b>{{ user.nombre_completo }}</b><small :style="{ color: COLOR_HEX[user.perfil] }">{{ user.perfil }}</small></span>
                            <DownOutlined class="vt-user-caret" />
                        </button>
                        <template #overlay>
                            <div class="vt-perfil-card">
                                <div class="vt-perfil-top" :style="{ '--p': COLOR_HEX[user.perfil] }">
                                    <span class="vt-perfil-av">{{ iniciales(user.nombre_completo) }}</span>
                                    <b>{{ user.nombre_completo }}</b>
                                    <span class="vt-perfil-badge"><SafetyOutlined /> {{ user.perfil }}</span>
                                </div>
                                <div class="vt-perfil-body">
                                    <div><MailOutlined style="color: #e0457b" /> {{ user.email }}</div>
                                    <div><ShopOutlined style="color: #1597a5" /> {{ sucursalesTxt }}</div>
                                </div>
                                <div class="vt-perfil-opts">
                                    <button @click="(menuUsuario = false), (perfil = true)"><SettingOutlined style="color: #0461d8" /> Mi perfil<small>Cambiar correo o contraseña</small><LockOutlined class="ml-auto" style="color: #94a3b8" /></button>
                                </div>
                                <button class="vt-perfil-salir" @click="(menuUsuario = false), salir()"><LogoutOutlined /> Cerrar sesión</button>
                            </div>
                        </template>
                    </a-dropdown>
                </div>
            </div>
        </header>

        <!-- Menú en móvil -->
        <a-drawer v-model:open="drawer" placement="left" :width="280" :closable="false" :body-style="{ padding: '12px' }">
            <img src="/images/ventrack-logo.webp" alt="VENTRACK" class="w-48 mx-auto mb-4 block" />
            <Link v-for="(i, n) in visibles" :key="i.key" :href="i.href" class="vt-nav-link block-link" :class="{ on: activo === i.key }" :style="{ '--c': i.color, '--i': n }">
                <component :is="i.icon" class="vt-nav-ic" />
                <span class="flex-1">{{ i.label }}</span>
                <span v-if="i.tag" class="vt-nav-tag" :class="i.tag">{{ i.tag }}</span>
                <span v-if="i.badge && props.mensajesSinLeer" class="vt-badge">{{ props.mensajesSinLeer }}</span>
            </Link>
        </a-drawer>

        <main class="vt-content">
            <slot />
        </main>

        <ConfirmDialog />
        <PerfilModal v-model:open="perfil" />
    </div>
</template>

<style>
.vt-app {
    min-height: 100vh;
    background: var(--vt-bg);
}
.vt-nav {
    position: sticky;
    top: 0;
    z-index: 30;
    background: #fff;
    box-shadow: 0 6px 18px rgba(10, 31, 68, 0.05);
}
.vt-nav-top {
    height: 6px;
    background: var(--vt-navy);
}
.vt-nav-in {
    max-width: 1600px;
    margin: 0 auto;
    height: 62px;
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 0 20px;
}
.vt-nav-logo {
    flex: none;
}
.vt-nav-logo img {
    height: 36px;
    width: auto;
    display: block;
}
.vt-nav-links {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2px;
}
.vt-nav-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 11px;
    border-radius: 10px;
    color: #3b4656 !important;
    text-decoration: none !important;
    font-size: 14.5px;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
}
.vt-nav-ic {
    font-size: 16px;
    color: var(--c);
    transition: transform 0.2s;
}
.vt-nav-link:hover {
    background: #f3f5f8;
    color: var(--vt-navy) !important;
}
.vt-nav-link:hover .vt-nav-ic {
    transform: translateY(-1px) scale(1.08);
}
.vt-nav-link.on {
    background: #eff1f4;
    color: var(--vt-navy) !important;
    font-weight: 600;
}
.vt-nav-link.on::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 3px;
    width: 24px;
    height: 2.5px;
    margin-left: -12px;
    border-radius: 3px;
    background: var(--vt-navy);
}
.vt-nav-link.block-link {
    margin-bottom: 4px;
    padding: 10px 12px;
}
.vt-nav-link.block-link.on::after {
    display: none;
}
.vt-nav-tag {
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 99px;
    color: #1d4fbf;
    background: #dde8fb;
}
.vt-nav-tag.Auditoría {
    color: #44566c;
    background: #e8ecf1;
}
.vt-nav-burger {
    font-size: 18px;
}
.vt-badge {
    background: var(--vt-green);
    color: #fff;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    min-width: 20px;
    padding: 0 6px;
    text-align: center;
}
.vt-nav-right {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    flex: none;
}
.vt-bell {
    position: relative;
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 12px;
    background: #f3f5f8;
    color: #44566c;
    font-size: 18px;
    cursor: pointer;
    display: grid;
    place-items: center;
    transition: background 0.15s, transform 0.15s;
}
.vt-bell:hover {
    background: #e8edf3;
    transform: translateY(-2px);
}
.vt-bell.hay .anticon {
    animation: vt-campana 2.4s ease-in-out infinite;
    transform-origin: 50% 0;
}
.vt-bell-n {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 99px;
    background: #e5484d;
    color: #fff;
    font: 700 11px/20px Figtree, sans-serif;
    box-shadow: 0 0 0 2px #fff;
}
@keyframes vt-campana {
    0%, 70%, 100% { transform: rotate(0); }
    75% { transform: rotate(14deg); }
    80% { transform: rotate(-12deg); }
    85% { transform: rotate(8deg); }
    90% { transform: rotate(-5deg); }
}
.vt-noti {
    width: 320px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(10, 31, 68, 0.18);
    padding: 8px;
}
.vt-noti-h {
    font-weight: 700;
    padding: 8px 10px;
    color: var(--vt-navy);
}
.vt-noti-i {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 9px 10px;
    border-radius: 10px;
    color: var(--vt-navy) !important;
    transition: background 0.15s;
}
.vt-noti-i:hover {
    background: color-mix(in srgb, var(--c) 8%, #fff);
}
.vt-noti-i .ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--c);
    flex: none;
}
.vt-noti-i small {
    display: block;
    color: var(--vt-muted);
    font-size: 12px;
}
.vt-noti-vacio {
    padding: 14px 10px;
    color: #1faf5c;
    font-weight: 600;
}
.vt-perfil-card {
    width: 290px;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(10, 31, 68, 0.18);
}
.vt-perfil-top {
    text-align: center;
    padding: 20px 16px 16px;
    color: #fff;
    background: linear-gradient(135deg, var(--p), #0a1f44);
}
.vt-perfil-av {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    margin: 0 auto 8px;
    font: 800 20px 'Bricolage Grotesque', sans-serif;
    background: rgba(255, 255, 255, 0.18);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.35);
}
.vt-perfil-top b {
    display: block;
    font-size: 15px;
}
.vt-perfil-badge {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    margin-top: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.2);
}
.vt-perfil-body {
    padding: 12px 16px;
    display: grid;
    gap: 8px;
    font-size: 13px;
    color: #44566c;
    word-break: break-all;
}
.vt-perfil-opts {
    padding: 0 12px 10px;
}
.vt-perfil-opts button {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--vt-line);
    border-radius: 10px;
    background: #fff;
    padding: 10px 12px;
    font: 600 14px Figtree, sans-serif;
    color: var(--vt-navy);
    cursor: pointer;
    text-align: left;
    flex-wrap: wrap;
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
}
.vt-perfil-opts button small {
    width: 100%;
    order: 3;
    padding-left: 26px;
    font-weight: 400;
    font-size: 12px;
    color: var(--vt-muted);
    margin-top: -6px;
}
.vt-perfil-opts button:hover {
    background: var(--vt-blue-soft);
    border-color: var(--vt-blue);
    transform: translateY(-1px);
}
.vt-perfil-salir {
    width: calc(100% - 24px);
    margin: 0 12px 12px;
    border: 0;
    border-radius: 10px;
    padding: 10px;
    font: 600 14px Figtree, sans-serif;
    color: #e5484d;
    background: #fdeced;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.vt-perfil-salir:hover {
    background: #e5484d;
    color: #fff;
}
.vt-user {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 0;
    background: none;
    padding: 5px 8px;
    border-radius: 10px;
    cursor: pointer;
    font: inherit;
    color: var(--vt-navy);
}
.vt-user:hover {
    background: #f3f5f8;
}
.vt-av.vt-av-nav {
    width: 40px;
    height: 40px;
    font-size: 13px;
    color: #fff;
    box-shadow: 0 3px 8px rgba(10, 31, 68, 0.3);
}
.vt-user-txt {
    max-width: 170px;
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    text-align: left;
}
.vt-user-txt b {
    font-size: 13px;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vt-user-txt small {
    font-size: 11.5px;
    font-weight: 600;
}
.vt-user-caret {
    font-size: 11px;
    color: var(--vt-muted);
    margin-left: 8px;
}
.vt-content {
    padding: 20px 26px 16px;
    max-width: 1600px;
    width: 100%;
    margin: 0 auto;
}
@media (max-width: 640px) {
    .vt-content {
        padding: 16px 14px 32px;
    }
    .vt-user-txt,
    .vt-user-caret {
        display: none;
    }
    .vt-nav-logo img {
        height: 30px;
    }
}

/* ===================== Animaciones del navbar ===================== */
.vt-nav {
    animation: vt-nav-baja 0.5s cubic-bezier(0.2, 0.9, 0.3, 1) both;
}
@keyframes vt-nav-baja {
    from { transform: translateY(-100%); opacity: 0; }
}
.vt-nav-top {
    background: linear-gradient(90deg, #0a1f44, #0461d8, #1597a5, #2dcb6a, #0461d8, #0a1f44);
    background-size: 300% 100%;
    animation: vt-nav-brillo 9s linear infinite;
}
@keyframes vt-nav-brillo {
    to { background-position: 300% 0; }
}
.vt-nav-logo img {
    transition: transform 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.4), filter 0.3s;
}
.vt-nav-logo:hover img {
    transform: scale(1.06) rotate(-2deg);
    filter: drop-shadow(0 4px 10px rgba(4, 97, 216, 0.25));
}
.vt-nav-link {
    animation: vt-nav-entra 0.45s calc(0.15s + var(--i, 0) * 0.05s) both;
}
@keyframes vt-nav-entra {
    from { opacity: 0; transform: translateY(-10px); }
}
/* Subrayado que crece desde el centro al pasar el cursor */
.vt-nav-link::before {
    content: '';
    position: absolute;
    left: 50%;
    right: 50%;
    bottom: 3px;
    height: 2.5px;
    border-radius: 3px;
    background: var(--c);
    opacity: 0;
    transition: left 0.25s ease, right 0.25s ease, opacity 0.25s;
}
.vt-nav-link:not(.on):hover::before {
    left: 14px;
    right: 14px;
    opacity: 0.7;
}
.vt-nav-link:hover .vt-nav-ic {
    animation: vt-nav-ic-salta 0.5s ease;
}
@keyframes vt-nav-ic-salta {
    30% { transform: translateY(-4px) rotate(-8deg) scale(1.15); }
    60% { transform: translateY(1px) rotate(4deg); }
}
.vt-nav-link.on {
    animation: vt-nav-entra 0.45s calc(0.15s + var(--i, 0) * 0.05s) both, vt-nav-activo 0.4s ease;
}
@keyframes vt-nav-activo {
    0% { background: transparent; }
    50% { background: color-mix(in srgb, var(--c) 20%, transparent); }
}
.vt-nav-link.on::after {
    animation: vt-nav-linea 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.3) both;
    background: var(--c) !important;
}
@keyframes vt-nav-linea {
    from { transform: scaleX(0); }
}
.vt-nav-link.on .vt-nav-ic {
    animation: vt-nav-ic-salta 0.6s 0.1s ease;
}
.vt-user .vt-av {
    transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.3, 1.4), box-shadow 0.3s;
}
.vt-user:hover .vt-av {
    transform: scale(1.08);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--vt-blue) 20%, transparent);
}
.vt-user-caret {
    transition: transform 0.25s;
}
.ant-dropdown-open .vt-user-caret {
    transform: rotate(180deg);
}
.vt-noti,
.vt-perfil-card {
    animation: vt-nav-menu 0.22s cubic-bezier(0.2, 0.9, 0.3, 1.2) both;
    transform-origin: top right;
}
@keyframes vt-nav-menu {
    from { opacity: 0; transform: scale(0.92) translateY(-6px); }
}
.vt-noti-i {
    animation: vt-nav-entra 0.3s both;
}
.vt-bell-n {
    animation: vt-nav-badge 2s ease-in-out infinite;
}
@keyframes vt-nav-badge {
    0%, 100% { box-shadow: 0 0 0 2px #fff, 0 0 0 2px rgba(229, 72, 77, 0.4); }
    50% { box-shadow: 0 0 0 2px #fff, 0 0 0 7px rgba(229, 72, 77, 0); }
}
@media (prefers-reduced-motion: reduce) {
    .vt-nav, .vt-nav-top, .vt-nav-link, .vt-bell-n { animation: none !important; }
}
</style>
