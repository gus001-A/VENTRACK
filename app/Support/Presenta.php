<?php

namespace App\Support;

use App\Models\Catalogo;
use App\Models\Prospecto;
use App\Models\SysLog;
use App\Models\User;
use App\Models\Visita;

/** Serializadores compartidos entre controladores para las vistas Vue. */
class Presenta
{
    public static function visita(Visita $v): array
    {
        return [
            'id' => $v->id,
            'fecha' => $v->fecha->toDateString(),
            'hora' => $v->hora ? substr($v->hora, 0, 5) : null,
            'estatus' => $v->estatus,
            'resultado' => $v->resultado,
            'vencida' => $v->vencida,
            'objetivo' => $v->objetivo,
            'geo' => $v->latitud !== null,
            'prospecto' => ['id' => $v->prospecto_id, 'nombre' => $v->prospecto?->nombre],
            'sucursal' => ['id' => $v->sucursal_id, 'nombre' => $v->sucursal?->nombre],
            'promotor' => ['id' => $v->promotor_id, 'nombre' => $v->promotor?->nombre_corto],
        ];
    }

    public static function conVisita(): array
    {
        return ['prospecto:id,nombre', 'sucursal:id,nombre', 'promotor:id,name,apellido_paterno'];
    }

    /** Opciones para el formulario "Programar visita". */
    public static function opcionesProgramar(User $user): array
    {
        if (! $user->puedeProgramar()) {
            return ['prospectos' => [], 'promotores' => []];
        }
        $ids = $user->sucursalIds();

        return [
            'prospectos' => Prospecto::whereIn('sucursal_id', $ids)->where('activo', true)->orderBy('nombre')
                ->with('sucursal:id,nombre')->get(['id', 'nombre', 'sucursal_id'])
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'sucursal' => $p->sucursal?->nombre, 'sucursal_id' => $p->sucursal_id]),
            'promotores' => User::activos()->perfil(User::PROMOTOR)->with('sucursales:id')
                ->when(! $user->esGlobal(), fn ($q) => $q->whereHas('sucursales', fn ($s) => $s->whereIn('sucursales.id', $ids)))
                ->orderBy('name')->get()
                ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_completo, 'sucursales' => $u->sucursales->pluck('id')]),
        ];
    }

    public static function formatos(): array
    {
        return Catalogo::opciones(Catalogo::FORMATO);
    }

    public static function traza(string $tabla, $id): array
    {
        return SysLog::trazaDe($tabla, $id);
    }
}
