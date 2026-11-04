import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { palette } from './theme';

dayjs.locale('es');

export type Estatus = 'programada' | 'proceso' | 'realizada' | 'revisada' | 'supervisada';

export const ESTATUS: Estatus[] = ['programada', 'proceso', 'realizada', 'revisada', 'supervisada'];

export const ESTATUS_INFO: Record<Estatus, { label: string; color: string; bg: string; icon: string }> = {
    programada: { label: 'Programada', color: palette.blue, bg: '#E6F0FD', icon: 'CalendarOutlined' },
    proceso: { label: 'En proceso', color: '#A86F00', bg: '#FFF4D9', icon: 'PlayCircleOutlined' },
    realizada: { label: 'Realizada', color: palette.greenDark, bg: '#E3F8EC', icon: 'CheckCircleOutlined' },
    revisada: { label: 'Revisada', color: '#0E7D89', bg: '#E1F5F7', icon: 'EyeOutlined' },
    supervisada: { label: 'Supervisada', color: palette.purple, bg: '#EFEAFA', icon: 'SafetyCertificateOutlined' },
};

/** Color sólido para puntos y barras por estatus. */
export const ESTATUS_DOT: Record<Estatus, string> = {
    programada: palette.blue,
    proceso: palette.amber,
    realizada: palette.green,
    revisada: palette.teal,
    supervisada: palette.purple,
};

export interface VisitaFila {
    id: number;
    fecha: string;
    hora: string | null;
    estatus: Estatus;
    resultado: 'positiva' | 'negativa' | null;
    vencida: boolean;
    objetivo: string | null;
    geo: boolean;
    prospecto: { id: number; nombre: string };
    sucursal: { id: number; nombre: string };
    promotor: { id: number; nombre: string };
}

export interface Opcion {
    id: number;
    nombre: string;
    [k: string]: any;
}

export interface Paginado<T> {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface UltimaModificacion {
    usuario: string;
    fecha: string;
    hora: string;
    accion: string;
    total: number;
}

export interface TrazaFila {
    usuario: string;
    perfil: string | null;
    fecha: string;
    hora: string;
    accion: string;
}

/** Quién registró y quién modificó por última vez un registro. */
export interface Traza {
    creado: TrazaFila | null;
    ultima: TrazaFila | null;
    total: number;
}

export const fechaCorta = (d?: string | null) => (d ? dayjs(d).format('D MMM YYYY') : '—');
export const fechaLarga = (d?: string | null) => {
    const t = d ? dayjs(d).format('dddd D [de] MMMM') : '—';
    return t.charAt(0).toUpperCase() + t.slice(1);
};
/** Hora en formato de 12 horas: "14:05" o "14:05:30" → "2:05 p. m." */
export const hora12 = (h?: string | null, segundos = false) => {
    if (!h) return '';
    const [H, M, S] = h.split(':').map(Number);
    const h12 = H % 12 || 12;
    return `${h12}:${String(M).padStart(2, '0')}${segundos && S !== undefined ? ':' + String(S).padStart(2, '0') : ''} ${H < 12 ? 'a. m.' : 'p. m.'}`;
};
export const fechaHora = (d?: string | null) => (d ? `${dayjs(d).format('D MMM YYYY')} · ${hora12(dayjs(d).format('HH:mm'))}` : '—');
export const diaMes = (d: string) => dayjs(d).format('D MMM');

export const dinero = (n: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(n || 0);

export const iniciales = (n: string) => {
    const w = (n || '').trim().split(/\s+/);
    return (w.length > 1 ? w[0][0] + w[1][0] : (w[0] || '?').slice(0, 2)).toUpperCase();
};

export const tamano = (b: number) =>
    b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : b > 1024 ? Math.round(b / 1024) + ' KB' : b + ' B';

/** Elimina filtros vacíos antes de enviarlos en la URL. */
export const limpiar = (o: Record<string, any>) =>
    Object.fromEntries(Object.entries(o).filter(([, v]) => v !== null && v !== undefined && v !== ''));

/** Primer mensaje de error de un objeto de errores de Inertia. */
export const primerError = (errors: Record<string, string>) => Object.values(errors)[0];

/** Contraseña aleatoria segura (mayúsculas, minúsculas, números y símbolos). */
export function generarContrasena(largo = 12): string {
    const grupos = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?'];
    const todos = grupos.join('');
    const azar = (n: number) => {
        const a = new Uint32Array(1);
        crypto.getRandomValues(a);
        return a[0] % n;
    };
    const chars = grupos.map((g) => g[azar(g.length)]);
    while (chars.length < largo) chars.push(todos[azar(todos.length)]);
    for (let i = chars.length - 1; i > 0; i--) {
        const j = azar(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

/** Fecha máxima de nacimiento para ser mayor de edad (18 años). */
export const fechaMayorDeEdad = () => dayjs().subtract(18, 'year');
