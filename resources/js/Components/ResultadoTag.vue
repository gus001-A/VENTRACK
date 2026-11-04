<script setup lang="ts">
import { LikeOutlined, DislikeOutlined, WarningOutlined, MinusCircleOutlined } from '@ant-design/icons-vue';

defineProps<{ resultado: 'positiva' | 'negativa' | null; vencida?: boolean }>();
</script>

<template>
    <span v-if="resultado === 'positiva'" class="vt-tag solido" style="background: #1faf5c"><LikeOutlined />Positiva</span>
    <span v-else-if="resultado === 'negativa'" class="vt-tag solido" style="background: #e5484d"><DislikeOutlined />Negativa</span>
    <span v-else-if="vencida" class="vt-tag solido" style="background: #d9363e"><WarningOutlined />Vencida</span>
    <span v-else class="vt-tag" style="background: #eef2f7; color: #5d7085"><MinusCircleOutlined />Sin resultado</span>
</template>
