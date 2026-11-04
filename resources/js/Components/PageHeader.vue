<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title: string; subtitle?: string; icon?: any }>();
</script>

<template>
    <Head :title="title" />
    <div class="vt-page-head">
        <div class="min-w-0">
            <h1>
                <span v-if="icon" class="vt-head-icon"><component :is="icon" /></span>
                <span class="truncate">{{ title }}</span>
            </h1>
            <p v-if="subtitle">{{ subtitle }}</p>
        </div>
        <div class="flex gap-2 flex-wrap"><slot name="extra" /></div>
    </div>
</template>
