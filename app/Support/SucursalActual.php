<?php

namespace App\Support;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sucursal de trabajo seleccionada (obligatoria). Se guarda en sesión para que
 * Visitas, Prospectos, Usuarios y Productos respeten la misma sucursal al navegar.
 */
class SucursalActual
{
    private const CLAVE = 'sucursal_actual';

    /** Sucursales que el usuario puede elegir. */
    public static function opciones(User $user): array
    {
        if ($user->perfil === User::PROSPECTO) {
            return [];
        }

        return Sucursal::whereIn('id', $user->sucursalIds())->orderBy('nombre')
            ->get(['id', 'nombre'])->toArray();
    }

    /** Resuelve la sucursal: la pedida en ?sucursal=, la guardada en sesión o la primera disponible. */
    public static function resolver(Request $request): ?int
    {
        $user = $request->user();
        if (! $user || $user->perfil === User::PROSPECTO) {
            return null;
        }
        $ids = array_map('intval', $user->sucursalIds());
        $pedida = $request->integer('sucursal');
        $guardada = (int) $request->session()->get(self::CLAVE);

        $id = match (true) {
            $pedida && in_array($pedida, $ids, true) => $pedida,
            $guardada && in_array($guardada, $ids, true) => $guardada,
            default => Sucursal::whereIn('id', $ids)->orderBy('nombre')->value('id'),
        };
        $request->session()->put(self::CLAVE, $id);

        return $id;
    }
}
