# VENTRACK · Promoción de ventas en tiempo real

Sistema de seguimiento de visitas, promoción y ventas realizadas por promotores.

**Stack:** Laravel 12 · PHP 8.2+ (compatible 8.4) · Inertia.js 3 · Vue 3 + TypeScript · Ant Design Vue 4 · Tailwind CSS 4 · SQLite (archivo `database/database.sqlite`).

## Módulos

| Módulo | Qué hace |
| --- | --- |
| **Panel** | KPIs, visitas de hoy, alertas (vencidas, sin ubicación, por revisar), efectividad, próximos 7 días y actividad reciente. Portal propio para prospectos. |
| **Agenda** | Vista mes / semana / lista con alertas de incumplimiento. Arrastra una visita programada para reprogramarla. |
| **Visitas** | Lista de trabajo filtrada por perfil. Ciclo: Programada → En proceso → Realizada (positiva/negativa) → Revisada → Supervisada. Ubicación GPS obligatoria, fotos de evidencia y observaciones con formatos prediseñados en cada estatus. |
| **Prospectos** | Personas físicas o morales, ubicación (Google Maps / GPS / enlace), tipo de negocio, documentos y acceso opcional al portal. |
| **Sucursales** | Datos generales, contacto, redes sociales, responsable (administrador), productos que comercializa y documentos. |
| **Usuarios** | Expediente completo (datos, estudios, documentos: acta, INE, CURP, comprobante…), perfil y asignación a una, varias o todas las sucursales. |
| **Productos** | Área/departamento, costos, información, enlaces y presentaciones; envío por WhatsApp, correo o Facebook. |
| **Catálogos** | Tipos de negocio, tipos de documento y formatos de observación (con plantilla), todos con nota descriptiva. |
| **Mensajes** | Comunicación prospecto ⇄ promotor asignado. |
| **Bitácora** | Trazabilidad global (`sys_logs`): usuario, fecha, hora, módulo, acción y valores antes/después. Cada registro muestra además su última modificación y su historial en un clic. |

### Perfiles (RBAC)

- **Administrador:** control total; ve las sucursales que tiene asignadas.
- **Supervisor / Auditor:** visibilidad global de todas las sucursales; revisan (y el supervisor supervisa) visitas.
- **Promotor:** solo sus visitas; registra evidencia, observaciones y cierra visitas.
- **Prospecto:** consulta sus visitas y escribe a sus promotores.

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

La base de datos es **SQLite** (`DB_CONNECTION=sqlite` en `.env`): todo vive en el archivo `database/database.sqlite`, sin servidor. Para respaldarla basta con copiar ese archivo. Si no existe, créalo vacío antes de migrar:

```bash
touch database/database.sqlite
php artisan migrate --seed
npm run build        # o: npm run dev
php artisan serve
```

Usuarios de demostración (contraseña `password`): `admin@`, `supervisor@`, `auditor@`, `carlos@` (promotor), `ana@` (promotora) y `chela@` (prospecto) `ventrack.test`. En entorno `local` la pantalla de acceso muestra botones de acceso rápido.

> La geolocalización del navegador requiere **HTTPS** (o `localhost`). Para usarla desde el teléfono del promotor publica el sistema con certificado SSL.

## Pruebas

Las pruebas usan una base SQLite en memoria (ver `phpunit.xml`), nunca la de trabajo.

```bash
php artisan test
npx vue-tsc --noEmit
```

## Estructura

- `app/Models/Concerns/Auditable.php` — registro automático en bitácora de altas, cambios y bajas.
- `app/Models/User.php` — perfiles y filtro de sucursales visibles (`sucursalIds()`).
- `app/Http/Controllers/VisitaController.php` — workflow de visitas y evidencia.
- `resources/js/Pages` — vistas Vue (Inertia); `resources/js/Components/VisitaDrawer.vue` — panel de la visita.
- `resources/js/theme.ts` — paleta del logo aplicada a Ant Design.
- Archivos (documentos y fotos) se guardan en `storage/app/private` y se sirven con control de acceso; las presentaciones de productos tienen enlace público por UUID para compartirse.
