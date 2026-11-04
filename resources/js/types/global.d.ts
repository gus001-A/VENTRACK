import type { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof routeFn;
    }
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<object, object, any>;
    export default component;
}

export interface AuthUser {
    id: number;
    nombre: string;
    nombre_completo: string;
    email: string;
    perfil: 'Administrador' | 'Promotor' | 'Auditor' | 'Supervisor' | 'Prospecto';
    sucursales: string[];
}

export interface SharedProps {
    appName: string;
    hoy: string;
    auth: {
        user: AuthUser | null;
        can: { programar?: boolean; admin?: boolean; global?: boolean; bitacora?: boolean };
    };
    mensajesSinLeer: number;
    misSucursales: { id: number; nombre: string }[];
    sucursalActual: number | null;
    flash: { success?: string | null; error?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
