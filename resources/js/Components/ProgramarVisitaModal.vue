<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { CalendarOutlined, IdcardOutlined, UserOutlined, FlagOutlined } from '@ant-design/icons-vue';
import type { Opcion } from '@/utils';

const props = defineProps<{
    opciones: { prospectos: Opcion[]; promotores: Opcion[] };
    fecha?: string | null;
    visita?: { id: number; fecha: string; hora: string | null; objetivo: string | null; promotor: { id: number } } | null;
}>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    prospecto_id: null as number | null,
    promotor_id: null as number | null,
    fecha: null as string | null,
    hora: null as string | null,
    objetivo: '',
});

watch(open, (o) => {
    if (!o) return;
    form.clearErrors();
    if (props.visita) {
        form.promotor_id = props.visita.promotor.id;
        form.fecha = props.visita.fecha;
        form.hora = props.visita.hora;
        form.objetivo = props.visita.objetivo || '';
    } else {
        form.reset();
        form.fecha = props.fecha || dayjs().format('YYYY-MM-DD');
    }
});

const prospecto = computed(() => props.opciones.prospectos.find((p) => p.id === form.prospecto_id));

/** Primero los promotores asignados a la sucursal del prospecto. */
const promotores = computed(() => {
    const sid = prospecto.value?.sucursal_id;
    return [...props.opciones.promotores]
        .sort((a, b) => Number(b.sucursales?.includes(sid)) - Number(a.sucursales?.includes(sid)))
        .map((u) => ({
            value: u.id,
            label: u.nombre + (sid && !u.sucursales?.includes(sid) ? ' (otra sucursal)' : ''),
        }));
});

function guardar() {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (props.visita) form.put(`/visitas/${props.visita.id}`, opts);
    else form.post('/visitas', opts);
}
</script>

<template>
    <a-modal v-model:open="open" width="min(720px, 96vw)" centered :confirm-loading="form.processing" :ok-text="visita ? 'Guardar' : 'Programar'" cancel-text="Cancelar" @ok="guardar" destroy-on-close>
        <template #title>
            <div class="flex items-center gap-3">
                <span class="vt-head-icon" style="width: 36px; height: 36px; font-size: 17px"><CalendarOutlined /></span>
                <div>
                    <div>{{ visita ? 'Reprogramar visita' : 'Programar visita' }}</div>
                    <div class="vt-muted vt-small font-normal">Vincula un prospecto con un promotor</div>
                </div>
            </div>
        </template>
        <a-form layout="vertical" class="mt-4">
            <a-form-item v-if="!visita" required :validate-status="form.errors.prospecto_id ? 'error' : ''" :help="form.errors.prospecto_id">
                <template #label><IdcardOutlined style="color: #f08c00" class="mr-1" /> Prospecto</template>
                <a-select v-model:value="form.prospecto_id" show-search option-filter-prop="label" placeholder="Busca un prospecto"
                    :options="opciones.prospectos.map((p) => ({ value: p.id, label: `${p.nombre} · ${p.sucursal}` }))" />
            </a-form-item>
            <a-form-item required :validate-status="form.errors.promotor_id ? 'error' : ''" :help="form.errors.promotor_id">
                <template #label><UserOutlined style="color: #1faf5c" class="mr-1" /> Promotor</template>
                <a-select v-model:value="form.promotor_id" show-search option-filter-prop="label" placeholder="Selecciona un promotor" :options="promotores" />
            </a-form-item>
            <div class="vt-form-grid">
                <a-form-item label="Fecha" required :validate-status="form.errors.fecha ? 'error' : ''" :help="form.errors.fecha">
                    <a-date-picker v-model:value="form.fecha" value-format="YYYY-MM-DD" format="DD/MM/YYYY" class="w-full" />
                </a-form-item>
                <a-form-item label="Hora" :validate-status="form.errors.hora ? 'error' : ''" :help="form.errors.hora">
                    <a-time-picker v-model:value="form.hora" value-format="HH:mm" format="h:mm a" use12-hours :minute-step="5" class="w-full" placeholder="Selecciona la hora" />
                </a-form-item>
            </div>
            <a-form-item>
                <template #label><FlagOutlined style="color: #f08c00" class="mr-1" /> Descripción / información relevante</template>
                <a-input v-model:value="form.objetivo" placeholder="Ej. presentar catálogo, cerrar cotización…" :maxlength="255" />
            </a-form-item>
        </a-form>
    </a-modal>
</template>
