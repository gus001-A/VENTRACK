<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Presenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SucursalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $sucursales = Sucursal::whereIn('id', $user->sucursalIds())
            ->with(['responsable:id,name,apellido_paterno,apellido_materno,telefono,email', 'tipoNegocio:id,nombre', 'productos:id,nombre'])
            ->withCount(['usuarios', 'prospectos', 'documentos'])
            ->orderBy('nombre')->get()
            ->map(fn (Sucursal $s) => [
                ...$s->toArray(),
                'responsable' => $s->responsable ? ['id' => $s->responsable->id, 'nombre' => $s->responsable->nombre_completo, 'telefono' => $s->responsable->telefono, 'email' => $s->responsable->email] : null,
                'giro' => $s->tipoNegocio?->nombre,
                'productos' => $s->productos->map->only(['id', 'nombre']),
                'producto_ids' => $s->productos->pluck('id'),
                'traza' => Presenta::traza('sucursales', $s->id),
            ]);

        return Inertia::render('Sucursales/Index', [
            'sucursales' => $sucursales,
            'administradores' => User::activos()->perfil(User::ADMIN)->orderBy('name')->get()->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_completo]),
            'tiposNegocio' => Catalogo::opciones(Catalogo::TIPO_NEGOCIO),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'puedeEditar' => $user->esAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request) {
            $s = Sucursal::create(collect($data)->except('producto_ids')->all());
            $s->productos()->sync($data['producto_ids'] ?? []);
            // El responsable y quien la crea quedan asignados a la nueva sucursal
            $s->usuarios()->syncWithoutDetaching(array_unique([$data['responsable_id'], $request->user()->id]));
        });

        return back()->with('success', 'Sucursal creada');
    }

    public function update(Request $request, Sucursal $sucursal): RedirectResponse
    {
        abort_unless(in_array($sucursal->id, $request->user()->sucursalIds()), 403);
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $sucursal) {
            $sucursal->update(collect($data)->except('producto_ids')->all());
            $cambios = $sucursal->productos()->sync($data['producto_ids'] ?? []);
            if (array_filter($cambios)) {
                $sucursal->writeAudit('UPDATE', ['productos' => $cambios], $sucursal->nombre.' · productos/servicios actualizados');
            }
            $sucursal->usuarios()->syncWithoutDetaching([$data['responsable_id']]);
        });

        return back()->with('success', 'Sucursal actualizada');
    }

    /** Activa o desactiva la sucursal (se conserva por trazabilidad). */
    public function destroy(Request $request, Sucursal $sucursal): RedirectResponse
    {
        abort_unless(in_array($sucursal->id, $request->user()->sucursalIds()), 403);
        $sucursal->update(['activa' => ! $sucursal->activa]);

        return back()->with('success', $sucursal->activa ? 'Sucursal activada' : 'Sucursal desactivada');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_negocio_id' => ['nullable', Rule::exists('catalogos', 'id')->where('tipo', Catalogo::TIPO_NEGOCIO)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'direccion' => ['required', 'string', 'max:255'],
            'colonia' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'telefono' => ['nullable', 'regex:/^\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'regex:/^\d{10,15}$/'],
            'otras_redes' => ['nullable', 'string', 'max:255'],
            'responsable_id' => ['required', Rule::exists('users', 'id')->where('perfil', User::ADMIN)],
            'producto_ids' => ['array'],
            'producto_ids.*' => ['exists:productos,id'],
        ], ['telefono.regex' => 'El teléfono solo debe contener números (10 a 15 dígitos).', 'whatsapp.regex' => 'El WhatsApp solo debe contener números (10 a 15 dígitos).'], ['responsable_id' => 'responsable']);
    }
}
