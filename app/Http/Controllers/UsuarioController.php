<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Models\User;
use App\Support\Presenta;
use App\Support\SucursalActual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->only(['q', 'perfil', 'estado', 'nivel']);
        $sucursal = SucursalActual::resolver($request);
        $deSucursal = fn ($q) => $q->when($sucursal, fn ($q, $v) => $q->where(fn ($w) => $w
            ->whereHas('sucursales', fn ($s) => $s->where('sucursales.id', $v))
            ->orWhereIn('perfil', [User::AUDITOR, User::SUPERVISOR])
            ->orWhereHas('prospecto', fn ($p) => $p->where('sucursal_id', $v))));

        $usuarios = User::with('sucursales:id,nombre')->withCount('documentos')->tap($deSucursal)
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")
                ->orWhere('apellido_paterno', 'like', "%$v%")->orWhere('apellido_materno', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($f['perfil'] ?? null, fn ($q, $v) => $q->where('perfil', $v))
            ->when($f['nivel'] ?? null, fn ($q, $v) => $q->where('nivel_estudios', $v))
            ->when(($f['estado'] ?? null) === 'inactivos', fn ($q) => $q->where('activo', false), fn ($q) => $q->where('activo', true))
            ->orderBy('name')->paginate(20)->withQueryString()
            ->through(fn (User $u) => [
                ...$u->only(['id', 'name', 'apellido_paterno', 'apellido_materno', 'nombre_completo', 'email', 'perfil', 'telefono',
                    'curp', 'rfc', 'direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal', 'nivel_estudios', 'estudios_detalle', 'activo']),
                'fecha_nacimiento' => $u->fecha_nacimiento?->toDateString(),
                'sucursales' => $u->sucursales->map->only(['id', 'nombre']),
                'sucursal_ids' => $u->sucursales->pluck('id'),
                'documentos_count' => $u->documentos_count,
                'traza' => Presenta::traza('users', $u->id),
            ]);

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'kpis' => [
                'total' => User::query()->tap($deSucursal)->count(),
                'activos' => User::query()->tap($deSucursal)->where('activo', true)->count(),
                'inactivos' => User::query()->tap($deSucursal)->where('activo', false)->count(),
                'perfiles' => User::query()->tap($deSucursal)->where('activo', true)->selectRaw('perfil, count(*) as total')->groupBy('perfil')->pluck('total', 'perfil'),
            ],
            'filtros' => $f,
            'perfiles' => User::PERFILES,
            'niveles' => User::NIVELES_ESTUDIO,
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data) {
            $u = User::create(collect($data)->except('sucursal_ids')->all());
            $this->asignar($u, $data['sucursal_ids']);
        });

        return back()->with('success', 'Usuario creado');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $this->validar($request, $usuario);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($usuario->id === $request->user()->id && $data['perfil'] !== User::ADMIN) {
            return back()->with('error', 'No puedes quitarte el perfil de administrador.');
        }
        DB::transaction(function () use ($data, $usuario) {
            $usuario->update(collect($data)->except('sucursal_ids')->all());
            $this->asignar($usuario, $data['sucursal_ids']);
        });

        return back()->with('success', 'Usuario actualizado');
    }

    /** Baja lógica: se conserva el expediente y la trazabilidad. */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propio usuario.');
        }
        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with('success', $usuario->activo ? 'Usuario reactivado' : 'Usuario desactivado');
    }

    private function asignar(User $u, array $ids): void
    {
        $cambios = $u->sucursales()->sync($ids);
        if (array_filter($cambios)) {
            $nombres = fn ($ids) => Sucursal::whereIn('id', $ids)->pluck('nombre')->all();
            $u->writeAudit('UPDATE', ['sucursales' => ['agregadas' => $nombres($cambios['attached']), 'quitadas' => $nombres($cambios['detached'])]],
                $u->nombre_completo.' · sucursales asignadas');
        }
    }

    private function validar(Request $request, ?User $u = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['nullable', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($u?->id)],
            'password' => [$u ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'confirmed'],
            'perfil' => ['required', Rule::in(User::PERFILES)],
            'telefono' => ['nullable', 'regex:/^\d{10,15}$/'],
            // Mayor de edad
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'curp' => ['nullable', 'string', 'size:18'],
            'rfc' => ['nullable', 'string', 'min:12', 'max:13'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'colonia' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'nivel_estudios' => ['nullable', Rule::in(User::NIVELES_ESTUDIO)],
            'estudios_detalle' => ['nullable', 'string', 'max:255'],
            'todas' => ['boolean'],
            'sucursal_ids' => ['array'],
            'sucursal_ids.*' => ['exists:sucursales,id'],
        ], [
            'fecha_nacimiento.before_or_equal' => 'El usuario debe ser mayor de 18 años.',
            'telefono.regex' => 'El teléfono solo debe contener números (10 a 15 dígitos).',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ], ['name' => 'nombre(s)', 'sucursal_ids' => 'sucursales']);

        // Asignación flexible: una, varias o todas las sucursales
        if ($data['todas'] ?? false) {
            $data['sucursal_ids'] = Sucursal::pluck('id')->all();
        }
        unset($data['todas']);
        $data['sucursal_ids'] ??= [];
        if (! in_array($data['perfil'], [User::AUDITOR, User::SUPERVISOR], true) && ! $data['sucursal_ids']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['sucursal_ids' => 'Asigna al menos una sucursal.']);
        }
        foreach (['curp', 'rfc'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = mb_strtoupper($data[$k]);
            }
        }

        return $data;
    }
}
