import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types/global';

export function useShared() {
    const page = usePage();
    const props = computed(() => page.props as unknown as SharedProps);

    return {
        props,
        user: computed(() => props.value.auth.user!),
        can: computed(() => props.value.auth.can),
        hoy: computed(() => props.value.hoy),
    };
}
