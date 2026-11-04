/**
 * Publicación en una carpeta del dominio (p. ej. https://ric.sistema-sicad.site/ventrack).
 * El servidor indica la carpeta en <meta name="base-path">; en local viene vacía y nada cambia.
 * Las rutas fijas que empiezan con "/" (enlaces, visitas de Inertia, axios, fetch, imágenes…)
 * reciben el prefijo automáticamente, así el código puede seguir escribiendo "/productos".
 */
import type { AxiosStatic } from 'axios';

export const BASE = (document.querySelector('meta[name="base-path"]')?.getAttribute('content') ?? '').replace(/\/+$/, '');

const tieneBase = (u: string) => u === BASE || u.startsWith(`${BASE}/`) || u.startsWith(`${BASE}?`);

/** "/productos" → "/ventrack/m/desvs" (las URL completas o ya prefijadas no se tocan). */
export function conBase<T>(u: T): T {
    if (!BASE || typeof u !== 'string' || !u.startsWith('/') || u.startsWith('//') || tieneBase(u)) return u;
    return `${BASE}${u}` as T;
}

/** "/ventrack/ventas" → "/m/desvs" (para comparar la página actual con las rutas del menú). */
export function sinBase(u: string): string {
    if (!BASE || typeof u !== 'string' || !tieneBase(u)) return u;
    return u.slice(BASE.length) || '/';
}

const ATRIBUTO: Record<string, string> = { A: 'href', IMG: 'src', IFRAME: 'src', SOURCE: 'src', FORM: 'action', LINK: 'href' };
const SELECTOR = 'a[href^="/"],img[src^="/"],iframe[src^="/"],source[src^="/"],form[action^="/"]';

export function instalarBase(router: any, axios: AxiosStatic) {
    if (!BASE) return;

    axios.interceptors.request.use((c) => ({ ...c, url: conBase(c.url) }));

    for (const m of ['visit', 'prefetch']) {
        const original = router[m]?.bind(router);
        if (original) router[m] = (href: any, ...resto: any[]) => original(typeof href === 'string' ? conBase(href) : href, ...resto);
    }

    const fetchOriginal = window.fetch.bind(window);
    window.fetch = (entrada: RequestInfo | URL, opciones?: RequestInit) => fetchOriginal(typeof entrada === 'string' ? conBase(entrada) : entrada, opciones);
    const openOriginal = window.open.bind(window);
    window.open = (url?: string | URL, ...resto: any[]) => openOriginal(typeof url === 'string' ? conBase(url) : url, ...resto);

    // Enlaces e imágenes con ruta fija escritos en las plantillas
    const fijar = (el: Element) => {
        const atr = ATRIBUTO[el.tagName];
        const v = atr && el.getAttribute(atr);
        if (v && conBase(v) !== v) el.setAttribute(atr, conBase(v));
    };
    const recorrer = (n: Node) => {
        if (!(n instanceof Element)) return;
        fijar(n);
        n.querySelectorAll(SELECTOR).forEach(fijar);
    };
    new MutationObserver((cambios) => cambios.forEach((c) => (c.type === 'attributes' ? fijar(c.target as Element) : c.addedNodes.forEach(recorrer))))
        .observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['href', 'src', 'action'] });
    recorrer(document.documentElement);
}
