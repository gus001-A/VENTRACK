<script setup lang="ts">
/** Campo de teléfono: solo acepta números (10 a 15 dígitos). */
import { PhoneOutlined, WhatsAppOutlined } from '@ant-design/icons-vue';

withDefaults(defineProps<{ whatsapp?: boolean; placeholder?: string }>(), { whatsapp: false, placeholder: '10 dígitos' });
const valor = defineModel<string | null>('value', { default: '' });

function limpiar(e: Event) {
    const t = e.target as HTMLInputElement;
    const solo = t.value.replace(/\D/g, '').slice(0, 15);
    if (solo !== t.value) t.value = solo;
    valor.value = solo;
}
function tecla(e: KeyboardEvent) {
    if (e.key.length === 1 && !/\d/.test(e.key) && !e.ctrlKey && !e.metaKey) e.preventDefault();
}
</script>

<template>
    <a-input :value="valor" inputmode="numeric" :maxlength="15" :placeholder="placeholder" @input="limpiar" @keydown="tecla">
        <template #prefix>
            <WhatsAppOutlined v-if="whatsapp" style="color: #25d366" />
            <PhoneOutlined v-else style="color: #1faf5c" />
        </template>
    </a-input>
</template>
