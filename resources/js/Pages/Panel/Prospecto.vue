<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { AppstoreOutlined, EnvironmentOutlined, UserOutlined, MessageOutlined, PhoneOutlined, MailOutlined } from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import EstatusTag from '@/Components/EstatusTag.vue';
import VisitaModal from '@/Components/VisitaModal.vue';
import { diaMes, iniciales, fechaLarga, type VisitaFila, hora12 } from '@/utils';
import { useShared } from '@/composables/useShared';

defineProps<{ visitas: VisitaFila[]; promotores: { id: number; nombre: string; telefono: string | null; email: string }[] }>();
const { user, hoy } = useShared();
const abierta = ref<number | null>(null);
</script>

<template>
    <PageHeader :title="`Hola, ${user.nombre}`" :subtitle="fechaLarga(hoy)" :icon="AppstoreOutlined">
        <template #extra>
            <a-button type="primary" @click="router.visit('/mensajes')"><MessageOutlined /> Contactar a mi promotor</a-button>
        </template>
    </PageHeader>

    <div class="vt-g12 vt-enter" style="margin-top: 0">
        <div class="vt-card s8">
            <div class="vt-card-title"><h3><EnvironmentOutlined /> Tus visitas</h3></div>
            <div v-for="v in visitas" :key="v.id" class="vt-row" @click="abierta = v.id">
                <span><b>{{ diaMes(v.fecha) }} {{ hora12(v.hora) }}</b><br /><span class="vt-muted vt-small">{{ v.sucursal.nombre }} · {{ v.promotor.nombre }}</span></span>
                <EstatusTag :estatus="v.estatus" />
            </div>
            <div v-if="!visitas.length" class="vt-empty">Aún no tienes visitas programadas</div>
        </div>
        <div class="vt-card s4">
            <div class="vt-card-title"><h3><UserOutlined /> Tu promotor</h3></div>
            <div v-for="u in promotores" :key="u.id" class="flex gap-3 items-center py-2">
                <span class="vt-av">{{ iniciales(u.nombre) }}</span>
                <div class="min-w-0">
                    <b>{{ u.nombre }}</b><br />
                    <a v-if="u.telefono" :href="'tel:' + u.telefono" class="vt-small"><PhoneOutlined /> {{ u.telefono }}</a>
                    <a v-else :href="'mailto:' + u.email" class="vt-small"><MailOutlined /> {{ u.email }}</a>
                </div>
            </div>
            <p v-if="!promotores.length" class="vt-muted">Sin promotor asignado</p>
            <a-button type="primary" class="mt-3" block @click="router.visit('/mensajes')"><MessageOutlined /> Escribir mensaje</a-button>
        </div>
    </div>

    <VisitaModal :visita-id="abierta" @close="abierta = null" />
</template>

<style>
.vt-g12 .s8 { grid-column: span 8; }
</style>
