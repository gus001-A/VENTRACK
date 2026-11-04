<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Visita;
use App\Support\Presenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(Request $request): Response
    {
        $tipo = array_key_exists($request->input('tipo'), Catalogo::TIPOS) ? $request->input('tipo') : Catalogo::TIPO_NEGOCIO;

        return Inertia::render('Catalogos/Index', [
            'tipo' => $tipo,
            'tipos' => Catalogo::TIPOS,
            'conteos' => Catalogo::selectRaw('tipo, count(*) as total')->groupBy('tipo')->pluck('total', 'tipo'),
            'registros' => Catalogo::where('tipo', $tipo)->orderBy('nombre')->get()
                ->map(fn (Catalogo $c) => [...$c->toArray(), 'usos' => $c->usos(), 'traza' => Presenta::traza('catalogos', $c->id)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->liberarEtapa(Catalogo::create($this->validar($request)));

        return back()->with('success', 'Registro agregado');
    }

    public function update(Request $request, Catalogo $catalogo): RedirectResponse
    {
        $catalogo->update($this->validar($request, $catalogo));
        $this->liberarEtapa($catalogo);

        return back()->with('success', 'Registro actualizado');
    }

    /** Activa o desactiva el registro (se conserva por trazabilidad). */
    public function destroy(Catalogo $catalogo): RedirectResponse
    {
        $catalogo->update(['activo' => ! $catalogo->activo]);

        return back()->with('success', $catalogo->activo ? 'Registro activado' : 'Registro desactivado');
    }

    /** Solo un formato se precarga por etapa: el recién guardado reemplaza al anterior. */
    private function liberarEtapa(Catalogo $c): void
    {
        if ($c->tipo !== Catalogo::FORMATO || ! $c->etapa) {
            return;
        }
        Catalogo::where('tipo', Catalogo::FORMATO)->where('etapa', $c->etapa)->whereKeyNot($c->id)
            ->get()->each->update(['etapa' => null]);
    }

    private function validar(Request $request, ?Catalogo $c = null): array
    {
        $tipo = $c?->tipo ?? $request->input('tipo');

        return $request->validate([
            'tipo' => [$c ? 'sometimes' : 'required', Rule::in(array_keys(Catalogo::TIPOS))],
            'nombre' => ['required', 'string', 'max:255', Rule::unique('catalogos')->where('tipo', $tipo)->ignore($c?->id)],
            'nota' => ['nullable', 'string', 'max:2000'],
            'plantilla' => ['nullable', 'string', 'max:5000'],
            'etapa' => ['nullable', Rule::in(Visita::ESTATUS)],
        ]);
    }
}
