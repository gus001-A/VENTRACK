import {
    FilePdfOutlined, FileWordOutlined, FileExcelOutlined, FilePptOutlined, FileImageOutlined, FileOutlined,
} from '@ant-design/icons-vue';

export type TipoArchivo = 'pdf' | 'word' | 'excel' | 'powerpoint' | 'imagen' | 'otro';

/** Formatos permitidos: PDF, Office e imágenes. */
export const ACEPTADOS = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp';
export const EXTENSIONES = ACEPTADOS.split(',').map((e) => e.slice(1));
export const MAX_MB = 25;

export function tipoArchivo(nombre: string, mime?: string | null): TipoArchivo {
    const ext = (nombre.split('.').pop() || '').toLowerCase();
    if (ext === 'pdf' || mime === 'application/pdf') return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'powerpoint';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) || mime?.startsWith('image/')) return 'imagen';
    return 'otro';
}

export const ICONO_TIPO: Record<TipoArchivo, any> = {
    pdf: FilePdfOutlined, word: FileWordOutlined, excel: FileExcelOutlined, powerpoint: FilePptOutlined, imagen: FileImageOutlined, otro: FileOutlined,
};

export const COLOR_TIPO: Record<TipoArchivo, string> = {
    pdf: '#D9363E', word: '#2B5CD8', excel: '#1D8A4E', powerpoint: '#D35230', imagen: '#7C4DDB', otro: '#5D7085',
};

export function archivoValido(f: File): string | null {
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!EXTENSIONES.includes(ext)) return 'Solo se aceptan PDF, documentos de Office (Word, Excel, PowerPoint) e imágenes.';
    if (f.size > MAX_MB * 1024 * 1024) return `El archivo supera los ${MAX_MB} MB.`;
    return null;
}
