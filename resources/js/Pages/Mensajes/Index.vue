<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { MessageOutlined, SendOutlined, PhoneOutlined, CheckOutlined } from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import { fechaHora, iniciales } from '@/utils';
import { useShared } from '@/composables/useShared';

const props = defineProps<{
    contactos: { id: number; nombre: string; telefono: string | null; sinLeer: number }[];
    chat: number | null;
    mensajes: { id: number; texto: string; mio: boolean; fecha: string; leido: boolean }[];
}>();
const { user } = useShared();
const esProspecto = user.value.perfil === 'Prospecto';

const form = useForm({ con: props.chat, texto: '' });
const caja = ref<HTMLElement | null>(null);

const bajar = () => nextTick(() => caja.value && (caja.value.scrollTop = caja.value.scrollHeight));
onMounted(bajar);
watch(() => props.mensajes.length, bajar);
watch(() => props.chat, (c) => (form.con = c));

function abrir(id: number) {
    router.get('/mensajes', { con: id }, { preserveState: true, preserveScroll: true, replace: true });
}
function enviar() {
    if (!form.texto.trim()) return;
    form.post('/mensajes', { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('texto') });
}

// Actualización periódica de la conversación
let timer: number | undefined;
onMounted(() => {
    timer = window.setInterval(() => {
        if (document.visibilityState === 'visible') router.reload({ only: ['mensajes', 'contactos', 'mensajesSinLeer'] });
    }, 20000);
});
onBeforeUnmount(() => clearInterval(timer));

const actual = () => props.contactos.find((c) => c.id === props.chat);
</script>

<template>
    <PageHeader title="Mensajes" :subtitle="esProspecto ? 'Contacta a tu promotor asignado' : 'Conversaciones con tus prospectos'" :icon="MessageOutlined" />

    <div v-if="contactos.length" class="vt-chat">
        <aside class="vt-chat-list">
            <button v-for="c in contactos" :key="c.id" class="vt-chat-item" :class="{ on: c.id === chat }" @click="abrir(c.id)">
                <span class="vt-av">{{ iniciales(c.nombre) }}</span>
                <span class="min-w-0 flex-1 text-left"><b class="block truncate">{{ c.nombre }}</b><small class="vt-muted">{{ esProspecto ? 'Promotor' : 'Prospecto' }}</small></span>
                <a-badge v-if="c.sinLeer" :count="c.sinLeer" color="#2DCB6A" />
            </button>
        </aside>
        <section class="vt-chat-main">
            <header class="vt-chat-head">
                <span class="vt-av vt-av-grad">{{ iniciales(actual()?.nombre || '') }}</span>
                <b class="flex-1 truncate">{{ actual()?.nombre }}</b>
                <a-button v-if="actual()?.telefono" :href="'tel:' + actual()?.telefono"><PhoneOutlined /> Llamar</a-button>
            </header>
            <div ref="caja" class="vt-chat-body">
                <div v-for="m in mensajes" :key="m.id" class="vt-msg" :class="{ mio: m.mio }">
                    <div class="whitespace-pre-line">{{ m.texto }}</div>
                    <small>{{ fechaHora(m.fecha) }} <CheckOutlined v-if="m.mio && m.leido" /></small>
                </div>
                <p v-if="!mensajes.length" class="vt-muted text-center mt-10">Escribe el primer mensaje.</p>
            </div>
            <form class="vt-chat-input" @submit.prevent="enviar">
                <a-textarea v-model:value="form.texto" :auto-size="{ minRows: 1, maxRows: 4 }" placeholder="Escribe un mensaje" @press-enter="(e: KeyboardEvent) => { if (!e.shiftKey) { e.preventDefault(); enviar(); } }" />
                <a-button type="primary" html-type="submit" :loading="form.processing"><SendOutlined /></a-button>
            </form>
        </section>
    </div>
    <div v-else class="vt-card"><a-empty description="Aún no tienes conversaciones disponibles. Aparecerán cuando tengas visitas con prospectos registrados en el portal." /></div>
</template>

<style>
.vt-chat {
    display: grid;
    grid-template-columns: 280px 1fr;
    background: #fff;
    border: 1px solid var(--vt-line);
    border-radius: 14px;
    overflow: hidden;
    height: calc(100vh - 220px);
    min-height: 420px;
}
.vt-chat-list {
    border-right: 1px solid var(--vt-line);
    overflow: auto;
    padding: 8px;
}
.vt-chat-item {
    display: flex;
    gap: 10px;
    align-items: center;
    width: 100%;
    border: 0;
    background: none;
    padding: 9px;
    border-radius: 10px;
    cursor: pointer;
    font: inherit;
    color: inherit;
}
.vt-chat-item:hover {
    background: var(--vt-bg);
}
.vt-chat-item.on {
    background: var(--vt-blue-soft);
}
.vt-chat-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.vt-chat-head {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--vt-line);
}
.vt-chat-body {
    flex: 1;
    overflow: auto;
    padding: 16px;
    background: linear-gradient(180deg, #f7fafc, #fff);
}
.vt-msg {
    max-width: 78%;
    padding: 8px 12px;
    border-radius: 14px 14px 14px 4px;
    margin: 6px 0;
    background: #fff;
    border: 1px solid var(--vt-line);
}
.vt-msg.mio {
    margin-left: auto;
    border-radius: 14px 14px 4px 14px;
    background: var(--vt-blue);
    border-color: var(--vt-blue);
    color: #fff;
}
.vt-msg small {
    display: block;
    font-size: 11px;
    opacity: 0.7;
    margin-top: 2px;
}
.vt-chat-input {
    display: flex;
    gap: 8px;
    padding: 10px;
    border-top: 1px solid var(--vt-line);
    align-items: flex-end;
}
@media (max-width: 760px) {
    .vt-chat {
        grid-template-columns: 1fr;
        height: auto;
    }
    .vt-chat-list {
        display: flex;
        border-right: 0;
        border-bottom: 1px solid var(--vt-line);
        overflow-x: auto;
    }
    .vt-chat-item {
        width: auto;
        flex: none;
    }
    .vt-chat-body {
        height: 55vh;
    }
}
</style>
