<?php

namespace Database\Seeders;

use App\Models\Catalogo;
use App\Models\Mensaje;
use App\Models\Producto;
use App\Models\Prospecto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Visita;
use App\Models\VisitaObservacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de demostración (basados en el prototipo).
 * Todos los usuarios de ejemplo usan la contraseña: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Administradora (la bitácora del seeder queda a su nombre)
        $mk = fn ($n, $ap, $am, $email, $perfil, $extra = []) => User::create([
            'name' => $n, 'apellido_paterno' => $ap, 'apellido_materno' => $am, 'email' => $email,
            'password' => 'password', 'perfil' => $perfil, 'email_verified_at' => now(), ...$extra,
        ]);

        $laura = $mk('Laura', 'Méndez', 'Ruiz', 'admin@ventrack.test', User::ADMIN, ['telefono' => '7771002000', 'nivel_estudios' => 'Licenciatura', 'estudios_detalle' => 'Administración de empresas', 'ciudad' => 'Cuernavaca', 'estado' => 'Morelos']);
        Auth::setUser($laura);

        // Catálogos
        $giros = collect([
            'Comercio' => 'Tiendas, abarrotes y misceláneas', 'Salud' => 'Clínicas, consultorios y farmacias',
            'Hotelería' => 'Hoteles y hospedaje', 'Automotriz' => 'Talleres y refaccionarias', 'Alimentos' => 'Restaurantes y cocinas',
            'Servicios' => 'Servicios profesionales y de mantenimiento',
        ])->map(fn ($nota, $nombre) => Catalogo::create(['tipo' => Catalogo::TIPO_NEGOCIO, 'nombre' => $nombre, 'nota' => $nota]));

        foreach (['INE' => 'Identificación oficial vigente', 'CURP' => null, 'Acta de nacimiento' => null,
            'Comprobante de domicilio' => 'No mayor a 3 meses', 'Convenio' => 'Convenio comercial firmado', 'Contrato' => null,
            'Brochure' => 'Material de presentación comercial', 'Volante' => null, 'Ficha técnica' => null] as $n => $nota) {
            Catalogo::create(['tipo' => Catalogo::TIPO_DOCUMENTO, 'nombre' => $n, 'nota' => $nota]);
        }

        $formatos = [
            'Primera visita' => ['Presentación inicial y necesidades del cliente', "Necesidades detectadas:\nProductos presentados:\nInterés (alto/medio/bajo):\nSiguiente paso:", Visita::PROGRAMADA],
            'Seguimiento' => ['Seguimiento a una propuesta enviada', "Avance desde la última visita:\nObjeciones:\nAcuerdos:", null],
            'Cierre de venta' => ['Registro de venta concretada', "Producto/servicio vendido:\nMonto:\nForma de pago:\nFecha de inicio:", Visita::PROCESO],
            'Revisión de auditoría' => ['Validación de evidencia por auditoría', "Evidencia fotográfica (completa/incompleta):\nUbicación coincide (sí/no):\nComentarios:", Visita::REALIZADA],
            'Supervisión' => ['Visto bueno final del supervisor', "Proceso completo (sí/no):\nCalidad de la evidencia:\nAcuerdos finales:", Visita::REVISADA],
        ];
        // La etapa indica en qué estatus de la visita se precarga el formato automáticamente
        foreach ($formatos as $n => [$nota, $plantilla, $etapa]) {
            Catalogo::create(['tipo' => Catalogo::FORMATO, 'nombre' => $n, 'nota' => $nota, 'plantilla' => $plantilla, 'etapa' => $etapa]);
        }

        // Usuarios
        $carlos = $mk('Carlos', 'Ortega', 'Vela', 'carlos@ventrack.test', User::PROMOTOR, ['telefono' => '7772003000', 'nivel_estudios' => 'Bachillerato']);
        $ana = $mk('Ana', 'Beltrán', 'Soto', 'ana@ventrack.test', User::PROMOTOR, ['telefono' => '7773004000', 'nivel_estudios' => 'Licenciatura']);
        $jorge = $mk('Jorge', 'Salas', 'Pino', 'auditor@ventrack.test', User::AUDITOR);
        $marta = $mk('Marta', 'Cruz', 'León', 'supervisor@ventrack.test', User::SUPERVISOR);

        // Sucursales
        $centro = Sucursal::create([
            'nombre' => 'Centro', 'tipo_negocio_id' => $giros['Servicios']->id, 'descripcion' => 'Servicios de limpieza y mantenimiento',
            'direccion' => 'Av. Morelos 120', 'ciudad' => 'Jiutepec', 'estado' => 'Morelos', 'telefono' => '7771234567',
            'sitio_web' => 'https://ventrack.mx', 'instagram' => '@ventrack_centro', 'responsable_id' => $laura->id,
        ]);
        $cuerna = Sucursal::create([
            'nombre' => 'Cuernavaca', 'tipo_negocio_id' => $giros['Comercio']->id, 'descripcion' => 'Venta de equipo de seguridad',
            'direccion' => 'Blvd. Cuauhnáhuac 88', 'ciudad' => 'Cuernavaca', 'estado' => 'Morelos', 'telefono' => '7779876543',
            'responsable_id' => $laura->id,
        ]);
        $laura->sucursales()->sync([$centro->id, $cuerna->id]);
        $carlos->sucursales()->sync([$centro->id]);
        $ana->sucursales()->sync([$cuerna->id]);

        // Productos y servicios
        $p1 = Producto::create(['nombre' => 'Limpieza integral mensual', 'tipo' => 'Servicio', 'area' => 'Servicios', 'costo' => 4500, 'unidad' => 'por mes', 'descripcion' => 'Limpieza de áreas comunes y sanitarios, 5 días por semana.', 'links' => [['titulo' => 'Conoce el servicio', 'url' => 'https://ventrack.mx/limpieza']]]);
        $p2 = Producto::create(['nombre' => 'Kit de cámaras de seguridad', 'tipo' => 'Producto', 'area' => 'Seguridad', 'costo' => 7900, 'unidad' => 'kit', 'descripcion' => '4 cámaras HD, grabador y instalación incluida.']);
        $p3 = Producto::create(['nombre' => 'Mantenimiento preventivo', 'tipo' => 'Servicio', 'area' => 'Servicios', 'costo' => 2300, 'unidad' => 'por visita', 'descripcion' => 'Revisión eléctrica, hidráulica y reporte técnico.']);
        $centro->productos()->sync([$p1->id, $p3->id]);
        $cuerna->productos()->sync([$p2->id]);

        // Prospectos
        $pros = fn ($n, $giro, $suc, $dir, $tp, $lat, $lng, $extra = []) => Prospecto::create([
            'nombre' => $n, 'tipo_negocio_id' => $giros[$giro]->id, 'sucursal_id' => $suc->id, 'direccion' => $dir,
            'tipo_persona' => $tp, 'latitud' => $lat, 'longitud' => $lng, 'ciudad' => $suc->ciudad, ...$extra,
        ]);
        $chela = $pros('Abarrotes Doña Chela', 'Comercio', $centro, 'Calle Hidalgo 14', 'Moral', 18.8823, -99.1789, ['contacto' => 'Graciela Torres', 'telefono' => '7775550101']);
        $sonrie = $pros('Clínica Dental Sonríe', 'Salud', $centro, 'Av. Juárez 301', 'Física', 18.8851, -99.1752, ['contacto' => 'Dr. Luis Paredes']);
        $hotel = $pros('Hotel Tlahuica', 'Hotelería', $cuerna, 'Calle Reforma 55', 'Moral', 18.9218, -99.2341);
        $taller = $pros('Taller Mecánico Ramos', 'Automotriz', $cuerna, 'Av. Universidad 9', 'Física', 18.9312, -99.2387);
        $fogon = $pros('Restaurante El Fogón', 'Alimentos', $centro, 'Plaza Mayor 3', 'Moral', 18.8869, -99.1811);

        // Acceso al portal para un prospecto
        $chelaUser = User::create(['name' => 'Abarrotes Doña Chela', 'email' => 'chela@ventrack.test', 'password' => 'password', 'perfil' => User::PROSPECTO]);
        $chelaUser->sucursales()->sync([$centro->id]);
        $chela->update(['user_id' => $chelaUser->id]);

        // Visitas alrededor de hoy
        $d = fn (int $dias) => today()->addDays($dias)->toDateString();
        $v = fn ($p, $u, $fecha, $hora, $st, $extra = []) => Visita::create([
            'prospecto_id' => $p->id, 'sucursal_id' => $p->sucursal_id, 'promotor_id' => $u->id, 'programada_por' => $laura->id,
            'fecha' => $fecha, 'hora' => $hora, 'estatus' => $st, ...$extra,
        ]);
        $geo = fn ($p) => ['latitud' => $p->latitud, 'longitud' => $p->longitud, 'ubicacion_at' => now()];

        $v1 = $v($chela, $carlos, $d(0), '09:30', Visita::PROCESO, [...$geo($chela), 'iniciada_at' => now(), 'objetivo' => 'Presentar catálogo de limpieza']);
        $v($sonrie, $carlos, $d(0), '12:00', Visita::PROGRAMADA);
        $v($hotel, $ana, $d(1), '10:00', Visita::PROGRAMADA, ['objetivo' => 'Cotizar cámaras para recepción']);
        $v4 = $v($fogon, $carlos, $d(-3), '12:15', Visita::REALIZADA, [...$geo($fogon), 'resultado' => 'positiva', 'iniciada_at' => now()->subDays(3), 'realizada_at' => now()->subDays(3)]);
        $v5 = $v($taller, $ana, $d(-4), '11:00', Visita::REVISADA, [...$geo($taller), 'resultado' => 'negativa', 'iniciada_at' => now()->subDays(4), 'realizada_at' => now()->subDays(4), 'revisada_at' => now()->subDays(3)]);
        $v($chela, $carlos, $d(-7), '10:00', Visita::PROGRAMADA); // vencida
        $v($fogon, $carlos, $d(3), '13:00', Visita::PROGRAMADA, ['objetivo' => 'Seguimiento de servicio mensual']);
        $v($taller, $ana, $d(5), '09:00', Visita::PROGRAMADA);

        $obs = fn ($visita, $u, $st, $texto, $formato = null) => VisitaObservacion::create([
            'visita_id' => $visita->id, 'user_id' => $u->id, 'estatus' => $st, 'texto' => $texto,
            'formato_id' => $formato ? Catalogo::where('nombre', $formato)->value('id') : null,
        ]);
        $obs($v1, $carlos, Visita::PROCESO, 'Inicio de visita. Se presentó catálogo de limpieza.', 'Primera visita');
        $obs($v4, $carlos, Visita::REALIZADA, 'Cliente aceptó paquete mensual.', 'Cierre de venta');
        $obs($v5, $ana, Visita::REALIZADA, 'Ya cuenta con proveedor.');
        $obs($v5, $jorge, Visita::REVISADA, 'Evidencia validada.', 'Revisión de auditoría');

        Mensaje::create(['prospecto_user_id' => $chelaUser->id, 'promotor_id' => $carlos->id, 'from_id' => $chelaUser->id, 'texto' => 'Buen día, ¿pueden pasar el viernes con la cotización?']);

        Auth::forgetUser();
    }
}
