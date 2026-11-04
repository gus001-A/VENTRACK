<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailOutlined, LockOutlined, SafetyCertificateOutlined, KeyOutlined, ArrowLeftOutlined, CheckOutlined, CloseOutlined } from '@ant-design/icons-vue';

const props = defineProps<{ token: string; email: string; vigencia: number }>();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

// Requisitos visibles mientras se escribe
const reglas = computed(() => [
    { t: 'Mínimo 8 caracteres', ok: form.password.length >= 8 },
    { t: 'Al menos una letra', ok: /[a-zA-ZñÑ]/.test(form.password) },
    { t: 'Al menos un número', ok: /\d/.test(form.password) },
    { t: 'Ambas contraseñas coinciden', ok: !!form.password && form.password === form.password_confirmation },
]);
const listo = computed(() => reglas.value.every((r) => r.ok) && !!form.email);

function guardar() {
    form.post('/restablecer', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Restablecer contraseña" />
    <div class="rs-wrap">
        <div class="rs-card">
            <div class="rs-band"></div>
            <img src="/images/ventrack-logo.webp" alt="VENTRACK" class="rs-logo" />
            <div class="rs-head">
                <span class="rs-ic"><KeyOutlined /></span>
                <div>
                    <h1>Crea una nueva contraseña</h1>
                    <p>El enlace es válido por {{ vigencia }} minutos desde que lo solicitaste.</p>
                </div>
            </div>

            <a-form layout="vertical" @submit.prevent="guardar">
                <a-form-item label="Correo electrónico" :validate-status="form.errors.email ? 'error' : ''" :help="form.errors.email">
                    <a-input v-model:value="form.email" size="large" type="email" :readonly="!!email">
                        <template #prefix><MailOutlined style="color: #0461d8" /></template>
                    </a-input>
                </a-form-item>
                <a-form-item label="Nueva contraseña" :validate-status="form.errors.password ? 'error' : ''" :help="form.errors.password">
                    <a-input-password v-model:value="form.password" size="large" autocomplete="new-password" autofocus>
                        <template #prefix><LockOutlined style="color: #1597a5" /></template>
                    </a-input-password>
                </a-form-item>
                <a-form-item label="Confirmar contraseña">
                    <a-input-password v-model:value="form.password_confirmation" size="large" autocomplete="new-password">
                        <template #prefix><LockOutlined style="color: #1597a5" /></template>
                    </a-input-password>
                </a-form-item>

                <ul class="rs-reglas">
                    <li v-for="r in reglas" :key="r.t" :class="{ ok: r.ok }"><CheckOutlined v-if="r.ok" /><CloseOutlined v-else /> {{ r.t }}</li>
                </ul>

                <a-button type="primary" html-type="submit" size="large" block :loading="form.processing" :disabled="!listo">
                    <SafetyCertificateOutlined /> Guardar nueva contraseña
                </a-button>
            </a-form>
            <Link href="/login" class="rs-volver"><ArrowLeftOutlined /> Volver a iniciar sesión</Link>
        </div>
    </div>
</template>

<style>
.rs-wrap {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px 16px;
    background: radial-gradient(circle at 15% 20%, rgba(21, 151, 165, 0.25), transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(45, 203, 106, 0.2), transparent 45%), #0a1f44;
}
.rs-card {
    position: relative;
    width: min(480px, 100%);
    background: #fff;
    border-radius: 20px;
    padding: 34px 32px 26px;
    overflow: hidden;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.35);
    animation: rs-entra 0.4s ease;
}
.rs-band {
    position: absolute;
    inset: 0 0 auto;
    height: 6px;
    background: linear-gradient(90deg, #0461d8, #1597a5, #2dcb6a);
}
.rs-logo {
    display: block;
    width: 190px;
    margin: 0 auto 18px;
}
.rs-head {
    display: flex;
    gap: 14px;
    align-items: center;
    margin-bottom: 20px;
}
.rs-head h1 {
    margin: 0;
    font: 800 21px 'Bricolage Grotesque', sans-serif;
    color: #0a1f44;
}
.rs-head p {
    margin: 2px 0 0;
    font-size: 13px;
    color: #6b7a90;
}
.rs-ic {
    flex: none;
    width: 46px;
    height: 46px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 21px;
    color: #fff;
    background: linear-gradient(135deg, #0461d8, #1597a5);
}
.rs-reglas {
    list-style: none;
    padding: 0;
    margin: -4px 0 18px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
    font-size: 12.5px;
    color: #94a3b8;
}
.rs-reglas li.ok {
    color: #1a8f4c;
}
.rs-volver {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    margin-top: 16px;
    font-size: 13.5px;
    color: #0461d8;
}
@keyframes rs-entra {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: none; }
}
@media (max-width: 480px) {
    .rs-reglas {
        grid-template-columns: 1fr;
    }
}
</style>
