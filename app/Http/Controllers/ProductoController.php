<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\SysLog;
use App\Models\User;
use App\Support\Presenta;
use App\Support\SucursalActual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $f = $request->only(['q', 'area', 'tipo']);
        $ids = $user->sucursalIds();
        $sucursal = SucursalActual::resolver($request);

        $productos = Producto::with(['sucursales:id,nombre', 'documentos.tipo:id,nombre'])
            ->when(! $user->esAdmin(), fn ($q) => $q->where('activo', true))
            // Cada quien ve los productos de sus sucursales (o los generales sin sucursal)
            ->where(fn ($q) => $q->whereDoesntHave('sucursales')->orWhereHas('sucursales', fn ($s) => $s->whereIn('sucursales.id', $ids)))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('nombre', 'like', "%$v%")->orWhere('descripcion', 'like', "%$v%")))
            ->when($f['area'] ?? null, fn ($q, $v) => $q->where('area', $v))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($sucursal, fn ($q, $v) => $q->where(fn ($w) => $w->whereDoesntHave('sucursales')->orWhereHas('sucursales', fn ($s) => $s->where('sucursales.id', $v))))
            ->orderBy('area')->orderBy('nombre')->get()
            ->map(fn (Producto $p) => [
                ...$p->only(['id', 'nombre', 'tipo', 'area', 'costo', 'unidad', 'descripcion', 'informacion', 'activo']),
                'links' => $p->links ?? [],
                'sucursales' => $p->sucursales->map->only(['id', 'nombre']),
                'sucursal_ids' => $p->sucursales->pluck('id'),
                'presentaciones' => $p->documentos->map(fn ($d) => [
                    'id' => $d->id, 'nombre' => $d->nombre_original, 'tipo' => $d->tipo?->nombre, 'descripcion' => $d->descripcion,
                    'mime' => $d->mime, 'publico' => route('documentos.publico', $d->uuid),
                ]),
                'traza' => Presenta::traza('productos', $p->id),
            ]);

        return Inertia::render('Productos/Index', [
            'productos' => $productos,
            'filtros' => $f,
            'areas' => Producto::distinct()->orderBy('area')->pluck('area'),
            'sucursales' => Sucursal::whereIn('id', $ids)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposDocumento' => Catalogo::opciones(Catalogo::TIPO_DOCUMENTO),
            'puedeEditar' => $user->esAdmin(),
            'puedeCompartir' => $user->perfil !== User::PROSPECTO,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data) {
            $p = Producto::create(collect($data)->except('sucursal_ids')->all());
            $p->sucursales()->sync($data['sucursal_ids'] ?? []);
        });

        return back()->with('success', 'Producto o servicio creado');
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $producto) {
            $producto->update(collect($data)->except('sucursal_ids')->all());
            $cambios = $producto->sucursales()->sync($data['sucursal_ids'] ?? []);
            if (array_filter($cambios)) {
                $producto->writeAudit('UPDATE', ['sucursales' => $cambios], $producto->nombre.' · sucursales actualizadas');
            }
        });

        return back()->with('success', 'Producto o servicio actualizado');
    }

    /** Activa o desactiva el producto o servicio (se conserva por trazabilidad). */
    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        return back()->with('success', $producto->activo ? 'Producto activado' : 'Producto desactivado');
    }

    /** Registra en bitácora el envío de la ficha (WhatsApp o correo). */
    public function compartir(Request $request, Producto $producto): RedirectResponse
    {
        $data = $request->validate([
            'canal' => ['required', Rule::in(['WhatsApp', 'Correo'])],
            'destino' => ['nullable', 'string', 'max:255'],
        ]);
        SysLog::registrar('productos', $producto->id, 'UPDATE',
            'Ficha “'.$producto->nombre.'” enviada por '.$data['canal'].(filled($data['destino'] ?? null) ? ' a '.$data['destino'] : ''),
            ['envio' => $data]);

        return back();
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['Producto', 'Servicio'])],
            'area' => ['required', 'string', 'max:255'],
            'costo' => ['required', 'numeric', 'min:0'],
            'unidad' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'informacion' => ['nullable', 'string', 'max:10000'],
            'links' => ['array'],
            'links.*.titulo' => ['nullable', 'string', 'max:255'],
            'links.*.url' => ['required', 'url', 'max:500'],
            'sucursal_ids' => ['array'],
            'sucursal_ids.*' => ['exists:sucursales,id'],
        ], [], ['links.*.url' => 'enlace', 'area' => 'área o departamento']);
        $data['links'] = array_values($data['links'] ?? []);

        return $data;
    }
}
