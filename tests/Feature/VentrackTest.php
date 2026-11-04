<?php

namespace Tests\Feature;

use App\Models\Catalogo;
use App\Models\Documento;
use App\Models\Prospecto;
use App\Models\Sucursal;
use App\Models\SysLog;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\RestablecerContrasena;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VentrackTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_login_y_registro_en_bitacora(): void
    {
        $this->post('/login', ['email' => 'admin@ventrack.test', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->u('admin@ventrack.test'));
        $this->assertDatabaseHas('sys_logs', ['tabla_afectada' => 'sesiones', 'descripcion' => 'Inicio de sesión']);
    }

    public function test_usuario_inactivo_no_puede_entrar(): void
    {
        $this->u('ana@ventrack.test')->update(['activo' => false]);
        $this->post('/login', ['email' => 'ana@ventrack.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_promotor_solo_ve_sus_visitas(): void
    {
        $carlos = $this->u('carlos@ventrack.test');
        $this->actingAs($carlos)->get('/visitas')->assertInertia(fn (Assert $p) => $p
            ->component('Visitas/Index')
            ->where('visitas.total', Visita::where('promotor_id', $carlos->id)->count()));

        $ajena = Visita::where('promotor_id', '!=', $carlos->id)->first();
        $this->actingAs($carlos)->getJson("/visitas/{$ajena->id}/detalle")->assertForbidden();
    }

    public function test_auditor_ve_todas_las_sucursales(): void
    {
        $auditor = $this->u('auditor@ventrack.test');
        $suc = Sucursal::orderByDesc('id')->first();
        $this->actingAs($auditor)->get("/visitas?sucursal={$suc->id}")
            ->assertInertia(fn (Assert $p) => $p->has('misSucursales', Sucursal::count())
                ->where('sucursalActual', $suc->id)
                ->where('visitas.total', Visita::where('sucursal_id', $suc->id)->count()));

        // La sucursal elegida se respeta al cambiar de módulo
        $this->actingAs($auditor)->get('/prospectos')->assertInertia(fn (Assert $p) => $p
            ->where('sucursalActual', $suc->id)
            ->where('prospectos.total', Prospecto::where('sucursal_id', $suc->id)->where('activo', true)->count()));

        // No se puede filtrar con "desde" posterior a hoy
        $this->actingAs($auditor)->get('/visitas?desde='.today()->addDay()->toDateString())->assertSessionHasErrors('desde');
    }

    public function test_perfiles_restringidos(): void
    {
        $this->actingAs($this->u('carlos@ventrack.test'))->get('/usuarios')->assertForbidden();
        $this->actingAs($this->u('carlos@ventrack.test'))->get('/bitacora')->assertForbidden();
        $this->actingAs($this->u('chela@ventrack.test'))->get('/prospectos')->assertForbidden();
        $this->actingAs($this->u('supervisor@ventrack.test'))->get('/catalogos')->assertForbidden();
        $this->actingAs($this->u('carlos@ventrack.test'))->post('/visitas', [])->assertForbidden();
    }

    public function test_flujo_completo_de_visita_con_trazabilidad(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $carlos = $this->u('carlos@ventrack.test');
        $prospecto = Prospecto::where('nombre', 'Clínica Dental Sonríe')->first();

        // Programar
        $this->actingAs($admin)->post('/visitas', [
            'prospecto_id' => $prospecto->id, 'promotor_id' => $carlos->id, 'fecha' => today()->toDateString(), 'hora' => '16:00',
        ])->assertSessionHas('success');
        $v = Visita::latest('id')->first();
        $this->assertSame(Visita::PROGRAMADA, $v->estatus);

        // Sin la ubicación del equipo no se puede iniciar
        $this->actingAs($carlos)->post("/visitas/{$v->id}/avanzar", ['texto' => 'Avance registrado'])->assertSessionHas('error');
        $this->assertSame(Visita::PROGRAMADA, $v->fresh()->estatus);

        // Sin comentario no se puede avanzar
        $this->actingAs($carlos)->post("/visitas/{$v->id}/avanzar", [])->assertSessionHasErrors('texto');
        // Solo fotos, PDF u Office como evidencia
        $this->actingAs($carlos)->post("/visitas/{$v->id}/observaciones", ['texto' => 'Nota', 'archivos' => [UploadedFile::fake()->create('virus.exe', 10)]])
            ->assertSessionHasErrors('archivos.0');

        // Inicio con la ubicación en tiempo real + foto
        $gps = ['latitud' => 18.8851, 'longitud' => -99.1752, 'precision' => 10];
        Storage::fake('local');
        $this->actingAs($carlos)->post("/visitas/{$v->id}/foto", ['foto' => UploadedFile::fake()->image('fachada.jpg')])->assertSessionHas('success');
        $this->actingAs($carlos)->post("/visitas/{$v->id}/avanzar", ['texto' => 'Avance registrado', ...$gps])->assertStatus(302)->assertSessionHas('success');
        $v->refresh();
        $this->assertSame([Visita::PROCESO, 18.8851], [$v->estatus, $v->latitud]);

        // El promotor no puede revisar; cierra como positiva con formato
        $this->actingAs($carlos)->post("/visitas/{$v->id}/cerrar", [
            ...$gps, 'resultado' => 'positiva', 'texto' => 'Venta cerrada', 'archivos' => [UploadedFile::fake()->image('contrato.jpg'), UploadedFile::fake()->create('contrato.pdf', 40, 'application/pdf')], 'formato_id' => Catalogo::where('nombre', 'Cierre de venta')->value('id'),
        ])->assertSessionHas('success');
        $v->refresh();
        $this->assertSame([Visita::REALIZADA, 'positiva'], [$v->estatus, $v->resultado]);
        $this->actingAs($carlos)->post("/visitas/{$v->id}/avanzar", ['texto' => 'Avance registrado'])->assertForbidden();

        // Auditor revisa; solo supervisor/admin supervisa
        $this->actingAs($this->u('auditor@ventrack.test'))->post("/visitas/{$v->id}/avanzar", ['texto' => 'Evidencia OK'])->assertSessionHas('success');
        $this->actingAs($this->u('auditor@ventrack.test'))->post("/visitas/{$v->id}/avanzar", ['texto' => 'Avance registrado'])->assertForbidden();
        $this->actingAs($this->u('supervisor@ventrack.test'))->post("/visitas/{$v->id}/avanzar", ['texto' => 'Avance registrado'])->assertSessionHas('success');
        $this->assertSame(Visita::SUPERVISADA, $v->fresh()->estatus);

        // Supervisada: ya no admite comentarios ni evidencia
        $this->actingAs($this->u('supervisor@ventrack.test'))->post("/visitas/{$v->id}/observaciones", ['texto' => 'Otro'])->assertStatus(422);
        $this->actingAs($carlos)->post("/visitas/{$v->id}/ubicacion", ['latitud' => 18.8, 'longitud' => -99.1])->assertStatus(422);
        $this->actingAs($this->u('supervisor@ventrack.test'))->post("/visitas/{$v->id}/avanzar", ['texto' => 'Otro'])->assertStatus(422);
        $this->actingAs($admin)->put("/visitas/{$v->id}", ['fecha' => today()->addDay()->toDateString(), 'hora' => '10:00'])->assertStatus(422);
        $this->actingAs($admin)->delete("/visitas/{$v->id}")->assertStatus(422);
        $this->assertSame(2, $v->evidencias()->where('tipo', 'ubicacion')->count());

        // Observaciones: el promotor solo ve las suyas; el supervisor ve todas
        $this->actingAs($carlos)->getJson("/visitas/{$v->id}/detalle")->assertOk()->assertJsonCount(2, 'observaciones')->assertJsonCount(3, 'adjuntos');
        $this->actingAs($this->u('supervisor@ventrack.test'))->getJson("/visitas/{$v->id}/detalle")->assertJsonCount(4, 'observaciones');

        // Trazabilidad con usuario, fecha y hora
        $log = SysLog::where('tabla_afectada', 'visitas')->where('registro_id', $v->id)->where('accion', 'UPDATE')->latest('id')->first();
        $this->assertNotNull($log->usuario_id);
        $this->assertNotNull($log->fecha);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $log->hora);
        $this->assertArrayHasKey('estatus', $log->cambios_json);
    }

    public function test_reprogramar_y_cancelar_solo_programadas(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $v = Visita::where('estatus', Visita::PROGRAMADA)->first();
        $nueva = today()->addDays(10)->toDateString();
        $this->actingAs($admin)->put("/visitas/{$v->id}", ['fecha' => $nueva])->assertSessionHas('success');
        $this->assertSame($nueva, $v->fresh()->fecha->toDateString());

        $hecha = Visita::where('estatus', Visita::REALIZADA)->first();
        $this->actingAs($admin)->delete("/visitas/{$hecha->id}")->assertStatus(422);
    }

    public function test_alta_de_usuario_con_sucursales_y_expediente(): void
    {
        Storage::fake('local');
        $admin = $this->u('admin@ventrack.test');
        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Pedro', 'apellido_paterno' => 'Luna', 'email' => 'pedro@ventrack.test', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'perfil' => 'Promotor', 'curp' => 'LUPP900101HMSNDR09', 'activo' => true, 'todas' => true,
        ])->assertSessionHas('success');
        $pedro = $this->u('pedro@ventrack.test');
        $this->assertCount(Sucursal::count(), $pedro->sucursales);

        $ine = Catalogo::where('nombre', 'INE')->value('id');
        $this->actingAs($admin)->post("/documentos/usuario/{$pedro->id}", [
            'archivo' => UploadedFile::fake()->create('ine.pdf', 120, 'application/pdf'), 'tipo_documento_id' => $ine, 'descripcion' => 'Vigente',
        ])->assertSessionHas('success');
        $doc = Documento::first();
        $this->actingAs($admin)->get("/documentos/{$doc->id}/archivo")->assertOk();
        $this->actingAs($this->u('carlos@ventrack.test'))->get("/documentos/{$doc->id}/archivo")->assertForbidden();
    }

    public function test_validaciones_de_usuario(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $base = ['name' => 'Menor', 'email' => 'menor@ventrack.test', 'perfil' => 'Promotor', 'todas' => true,
            'password' => 'secreto123', 'password_confirmation' => 'secreto123'];
        $this->actingAs($admin)->post('/usuarios', [...$base, 'fecha_nacimiento' => now()->subYears(17)->toDateString()])
            ->assertSessionHasErrors('fecha_nacimiento');
        $this->actingAs($admin)->post('/usuarios', [...$base, 'telefono' => '777-ABC-1234'])->assertSessionHasErrors('telefono');
        $this->actingAs($admin)->post('/usuarios', [...$base, 'password_confirmation' => 'otra'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/usuarios', [...$base, 'telefono' => '7771234567', 'fecha_nacimiento' => '1990-05-01'])
            ->assertSessionHas('success');
    }

    public function test_perfil_cambia_correo_y_contrasena(): void
    {
        $carlos = $this->u('carlos@ventrack.test');
        $this->actingAs($carlos)->put('/perfil/correo', ['email' => 'nuevo@ventrack.test', 'password_actual' => 'mala'])
            ->assertSessionHasErrors('password_actual');
        $this->actingAs($carlos)->put('/perfil/correo', ['email' => 'nuevo@ventrack.test', 'password_actual' => 'password'])
            ->assertSessionHas('success');
        $this->assertSame('nuevo@ventrack.test', $carlos->fresh()->email);

        $this->actingAs($carlos)->put('/perfil/contrasena', ['password_actual' => 'password', 'password' => 'Nueva2026x', 'password_confirmation' => 'otra'])
            ->assertSessionHasErrors('password');
        $this->actingAs($carlos)->put('/perfil/contrasena', ['password_actual' => 'password', 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'])
            ->assertSessionHas('success');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Nueva2026x', $carlos->fresh()->password));
        $this->assertDatabaseHas('sys_logs', ['tabla_afectada' => 'users', 'registro_id' => $carlos->id, 'accion' => 'UPDATE']);
    }

    public function test_panel_muestra_agenda_y_giros(): void
    {
        $this->actingAs($this->u('admin@ventrack.test'))->get('/')->assertInertia(fn (Assert $p) => $p
            ->component('Panel/Index')->has('agenda')->has('giros'));
    }

    public function test_formato_por_etapa_es_unico(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $formato = fn (string $n) => Catalogo::where('tipo', Catalogo::FORMATO)->where('nombre', $n)->first();
        $this->assertSame(Visita::PROGRAMADA, $formato('Primera visita')->etapa);

        $seg = $formato('Seguimiento');
        $this->actingAs($admin)->put("/catalogos/{$seg->id}", ['nombre' => 'Seguimiento', 'etapa' => Visita::PROGRAMADA])
            ->assertSessionHas('success');
        $this->assertSame(Visita::PROGRAMADA, $seg->fresh()->etapa);
        $this->assertNull($formato('Primera visita')->etapa);

        $this->actingAs($admin)->put("/catalogos/{$seg->id}", ['nombre' => 'Seguimiento', 'etapa' => 'otra'])->assertSessionHasErrors('etapa');
    }

    public function test_eliminar_activa_y_desactiva(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $giro = Catalogo::where('nombre', 'Comercio')->first();
        $this->actingAs($admin)->delete("/catalogos/{$giro->id}")->assertSessionHas('success');
        $this->assertFalse($giro->fresh()->activo);
        $this->actingAs($admin)->delete("/catalogos/{$giro->id}");
        $this->assertTrue($giro->fresh()->activo);

        $suc = Sucursal::first();
        $this->actingAs($admin)->delete("/sucursales/{$suc->id}");
        $this->assertFalse($suc->fresh()->activa);

        $pros = Prospecto::where('nombre', 'Abarrotes Doña Chela')->first();
        $this->actingAs($admin)->delete("/prospectos/{$pros->id}");
        $this->assertFalse($pros->fresh()->activo);
        $this->assertFalse($this->u('chela@ventrack.test')->activo);
        $this->assertModelExists($pros);
    }

    public function test_documentos_solo_pdf_office_e_imagenes(): void
    {
        Storage::fake('local');
        $admin = $this->u('admin@ventrack.test');
        $suc = Sucursal::first();
        $this->actingAs($admin)->post("/documentos/sucursal/{$suc->id}", ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('archivo');
        $this->actingAs($admin)->post("/documentos/sucursal/{$suc->id}", ['archivo' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('archivo');
        $this->actingAs($admin)->post("/documentos/sucursal/{$suc->id}", ['archivo' => UploadedFile::fake()->create('brochure.pdf', 50, 'application/pdf')])
            ->assertSessionHas('success');
    }

    public function test_codigo_postal_autocompleta_direccion(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.zippopotam.us/*' => \Illuminate\Support\Facades\Http::response(['places' => [
                ['place name' => 'Centro', 'state' => 'Morelos'], ['place name' => 'Amatitlán', 'state' => 'Morelos'],
            ]]),
            'nominatim.openstreetmap.org/*' => \Illuminate\Support\Facades\Http::response([['address' => ['county' => 'Cuernavaca', 'state' => 'Morelos']]]),
        ]);
        $this->actingAs($this->u('admin@ventrack.test'))->getJson('/codigo-postal/62000')
            ->assertOk()->assertJson(['estado' => 'Morelos', 'municipio' => 'Cuernavaca', 'colonias' => ['Amatitlán', 'Centro']]);
        $this->actingAs($this->u('admin@ventrack.test'))->getJson('/codigo-postal/abc')->assertStatus(422);
    }

    public function test_bitacora_por_modulo_muestra_quien_registro(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $this->actingAs($admin)->getJson('/bitacora/modulo?modulo=prospectos')
            ->assertOk()->assertJsonPath('data.0.tabla', 'prospectos');
        $this->actingAs($this->u('chela@ventrack.test'))->getJson('/bitacora/modulo?modulo=prospectos')->assertForbidden();

        $this->actingAs($admin)->get('/prospectos')->assertInertia(fn (Assert $p) => $p
            ->where('prospectos.data.0.traza.creado.usuario', 'Laura Méndez')
            ->has('prospectos.data.0.traza.creado.hora'));
    }

    public function test_prospecto_con_portal_y_mensajes(): void
    {
        $admin = $this->u('admin@ventrack.test');
        $this->actingAs($admin)->post('/prospectos', [
            'tipo_persona' => 'Moral', 'nombre' => 'Farmacia Sol', 'direccion' => 'Calle 1', 'sucursal_id' => Sucursal::first()->id,
            'portal' => true, 'portal_email' => 'sol@ventrack.test', 'portal_password' => 'secreto123',
        ])->assertSessionHas('success');
        $this->assertSame(User::PROSPECTO, $this->u('sol@ventrack.test')->perfil);

        // Doña Chela escribe a su promotor (Carlos tiene visitas con ella)
        $chela = $this->u('chela@ventrack.test');
        $carlos = $this->u('carlos@ventrack.test');
        $this->actingAs($chela)->post('/mensajes', ['con' => $carlos->id, 'texto' => 'Hola'])->assertRedirect();
        $this->actingAs($chela)->post('/mensajes', ['con' => $this->u('ana@ventrack.test')->id, 'texto' => 'Hola'])->assertForbidden();
        $this->actingAs($carlos)->get('/mensajes')->assertInertia(fn (Assert $p) => $p->where('chat', $chela->id)->has('mensajes', 2));
    }

    public function test_todas_las_paginas_cargan(): void
    {
        $admin = $this->u('admin@ventrack.test');
        foreach (['/', '/agenda', '/agenda?vista=semana', '/agenda?vista=lista', '/visitas', '/prospectos', '/sucursales', '/usuarios', '/productos', '/catalogos', '/bitacora', '/reportes', '/reportes?tipo=promotores', '/reportes?tipo=prospectos'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($this->u('chela@ventrack.test'))->get('/')->assertInertia(fn (Assert $p) => $p->component('Panel/Prospecto'));
        $this->actingAs($this->u('carlos@ventrack.test'))->get('/mensajes')->assertOk();
    }

    public function test_recuperar_contrasena_por_correo(): void
    {
        Notification::fake();
        $carlos = $this->u('carlos@ventrack.test');

        // Se verifica que el correo exista y que la cuenta esté activa
        $this->post('/olvide-contrasena', ['email' => 'nadie@ventrack.test'])->assertSessionHasErrors('email');
        $inactivo = User::factory()->create(['activo' => false]);
        $this->post('/olvide-contrasena', ['email' => $inactivo->email])->assertSessionHasErrors('email');
        Notification::assertNothingSent();

        $this->post('/olvide-contrasena', ['email' => $carlos->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($carlos, RestablecerContrasena::class, function ($n) use (&$token, $carlos) {
            $token = $n->token;
            $html = $n->toMail($carlos)->render();

            return str_contains($html, '/restablecer/'.$n->token);
        });

        $this->get("/restablecer/{$token}?email={$carlos->email}")->assertInertia(fn (Assert $p) => $p->component('Auth/Restablecer')->where('email', $carlos->email));
        $this->post('/restablecer', ['token' => 'malo', 'email' => $carlos->email, 'password' => 'Nueva1234', 'password_confirmation' => 'Nueva1234'])
            ->assertSessionHasErrors('email');
        $this->post('/restablecer', ['token' => $token, 'email' => $carlos->email, 'password' => 'Nueva1234', 'password_confirmation' => 'Nueva1234'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('Nueva1234', $carlos->fresh()->password));
        $this->assertTrue(SysLog::where('tabla_afectada', 'users')->where('registro_id', $carlos->id)->where('descripcion', 'like', 'Contraseña restablecida%')->exists());
    }

    public function test_reportes_en_pdf_y_excel(): void
    {
        $admin = $this->u('admin@ventrack.test');
        foreach (['visitas', 'promotores', 'prospectos'] as $tipo) {
            $this->actingAs($admin)->get("/reportes/{$tipo}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->actingAs($admin)->get("/reportes/{$tipo}/excel?desde=".today()->subMonth()->toDateString())->assertOk()->assertDownload();
        }
        // Respeta la sucursal y los filtros; el prospecto no tiene acceso
        $suc = Sucursal::orderBy('nombre')->first();
        $this->actingAs($admin)->get("/reportes?tipo=visitas&sucursal={$suc->id}&resultado=positiva")->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Index')->where('reporte.total', Visita::where('sucursal_id', $suc->id)->where('resultado', 'positiva')->count()));
        $this->actingAs($this->u('chela@ventrack.test'))->get('/reportes/visitas/pdf')->assertForbidden();
        $this->assertTrue(SysLog::where('tabla_afectada', 'reportes')->where('accion', 'EXPORT')->exists());
    }
}
