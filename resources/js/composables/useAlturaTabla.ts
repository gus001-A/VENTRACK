import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Alto disponible para el cuerpo de una tabla de modo que toda la pantalla quepa sin desplazarse:
 * el scroll queda dentro de la tabla. `reservado` = espacio que ocupa todo lo demás (navbar, encabezado,
 * cabecera de la tabla, filtros y paginación).
 */
export function useAlturaTabla(reservado: number, minimo = 220) {
    const alto = ref(400);
    const medir = () => (alto.value = Math.max(minimo, window.innerHeight - reservado));
    onMounted(() => {
        medir();
        window.addEventListener('resize', medir);
    });
    onBeforeUnmount(() => window.removeEventListener('resize', medir));
    return alto;
}
