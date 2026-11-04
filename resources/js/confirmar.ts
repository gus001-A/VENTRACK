import { reactive } from 'vue';

export type TipoDialogo = 'warning' | 'danger' | 'success' | 'question' | 'info';

interface Opciones {
    tipo?: TipoDialogo;
    titulo: string;
    texto?: string;
    confirmar?: string;
    cancelar?: string | false;
}

/** Estado del diálogo de confirmación global (estilo SweetAlert). */
export const dialogo = reactive({
    open: false,
    tipo: 'warning' as TipoDialogo,
    titulo: '',
    texto: '',
    confirmar: 'Sí, continuar',
    cancelar: 'Cancelar' as string | false,
    resolver: null as null | ((v: boolean) => void),
});

/** Muestra el diálogo y resuelve true si el usuario confirma. */
export function confirmar(o: Opciones): Promise<boolean> {
    dialogo.resolver?.(false);
    return new Promise((resolve) => {
        Object.assign(dialogo, {
            tipo: 'warning', texto: '', confirmar: 'Sí, continuar', cancelar: 'Cancelar',
            ...o,
            open: true,
            resolver: resolve,
        });
    });
}

export function responder(v: boolean) {
    dialogo.open = false;
    dialogo.resolver?.(v);
    dialogo.resolver = null;
}

/** Confirmación para activar/desactivar un registro. */
export const confirmarEstado = (activo: boolean, que: string, nombre: string) =>
    confirmar({
        tipo: activo ? 'danger' : 'success',
        titulo: activo ? `¿Desactivar ${que}?` : `¿Activar ${que}?`,
        texto: activo
            ? `“${nombre}” dejará de aparecer en las listas. Podrás activarlo de nuevo cuando quieras; su historial se conserva.`
            : `“${nombre}” volverá a estar disponible en el sistema.`,
        confirmar: activo ? 'Sí, desactivar' : 'Sí, activar',
    });
