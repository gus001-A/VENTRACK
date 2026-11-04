<script setup lang="ts">
/**
 * Campos de dirección con autocompletado por código postal:
 * al capturar los 5 dígitos se llenan estado, municipio/ciudad y la lista de colonias.
 */
import { ref, watch } from 'vue';
import axios from 'axios';
import { EnvironmentOutlined, NumberOutlined, HomeOutlined, ApartmentOutlined, FlagOutlined, CheckCircleFilled, LoadingOutlined } from '@ant-design/icons-vue';

const props = withDefaults(defineProps<{
    modelValue: { direccion: string; colonia: string; ciudad: string; estado?: string; codigo_postal: string };
    errors?: Record<string, string>;
    requerida?: boolean;
    conEstado?: boolean;
}>(), { errors: () => ({}), requerida: false, conEstado: true });

const m = props.modelValue;
const colonias = ref<string[]>([]);
const buscando = ref(false);
const encontrado = ref(false);
const aviso = ref<string | null>(null);

async function buscar(cp: string, inicial = false) {
    if (!/^\d{5}$/.test(cp)) {
        encontrado.value = false;
        return;
    }
    buscando.value = true;
    aviso.value = null;
    try {
        const { data } = await axios.get(`/codigo-postal/${cp}`);
        colonias.value = data.colonias || [];
        // Al editar un registro existente solo se carga la lista de colonias
        if (!inicial) {
            if (data.estado && props.conEstado) m.estado = data.estado;
            if (data.municipio) m.ciudad = data.municipio;
            if (colonias.value.length === 1) m.colonia = colonias.value[0];
            else if (m.colonia && colonias.value.length && !colonias.value.includes(m.colonia)) m.colonia = '';
        }
        encontrado.value = !!(data.estado || colonias.value.length);
        if (!encontrado.value) aviso.value = 'No encontramos ese código postal; captura los datos manualmente.';
    } catch {
        aviso.value = 'No se pudo consultar el código postal; captura los datos manualmente.';
    } finally {
        buscando.value = false;
    }
}

watch(() => m.codigo_postal, (cp) => buscar((cp || '').trim()));
if (m.codigo_postal) buscar(m.codigo_postal, true);

const err = (k: string) => ({ 'validate-status': props.errors[k] ? 'error' : '', help: props.errors[k] });
</script>

<template>
    <div class="vt-form-grid vt-g3">
        <a-form-item label="Código postal" v-bind="err('codigo_postal')" :extra="aviso || (encontrado ? 'Dirección completada automáticamente' : 'Escribe el C.P. para autocompletar')">
            <a-input v-model:value="m.codigo_postal" :maxlength="5" placeholder="62000" inputmode="numeric">
                <template #prefix><NumberOutlined style="color: var(--vt-blue)" /></template>
                <template #suffix>
                    <LoadingOutlined v-if="buscando" />
                    <CheckCircleFilled v-else-if="encontrado" style="color: #1faf5c" />
                </template>
            </a-input>
        </a-form-item>
        <a-form-item label="Colonia" v-bind="err('colonia')">
            <a-auto-complete v-model:value="m.colonia" :options="colonias.map((c) => ({ value: c }))" :filter-option="(i: string, o: any) => o.value.toLowerCase().includes(i.toLowerCase())"
                :placeholder="colonias.length ? `${colonias.length} colonias disponibles` : 'Colonia'">
                <template #default><a-input><template #prefix><ApartmentOutlined style="color: #7c4ddb" /></template></a-input></template>
            </a-auto-complete>
        </a-form-item>
        <a-form-item label="Municipio / ciudad" v-bind="err('ciudad')">
            <a-input v-model:value="m.ciudad"><template #prefix><EnvironmentOutlined style="color: #f08c00" /></template></a-input>
        </a-form-item>
        <a-form-item v-if="conEstado" label="Estado" v-bind="err('estado')">
            <a-input v-model:value="m.estado"><template #prefix><FlagOutlined style="color: #1597a5" /></template></a-input>
        </a-form-item>
        <a-form-item label="Calle y número" :required="requerida" :class="conEstado ? 'span2' : 'full'" v-bind="err('direccion')">
            <a-input v-model:value="m.direccion" placeholder="Ej. Av. Morelos 120, int. 4"><template #prefix><HomeOutlined style="color: #e0457b" /></template></a-input>
        </a-form-item>
    </div>
</template>
