<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SysLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cambios_json' => 'array',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function registrar(string $tabla, $registroId, string $accion, ?string $descripcion = null, ?array $cambios = null): self
    {
        $now = now();

        return static::create([
            'tabla_afectada' => $tabla,
            'registro_id' => $registroId,
            'accion' => $accion,
            'usuario_id' => auth()->id(),
            'fecha' => $now->toDateString(),
            'hora' => $now->format('H:i:s'),
            'descripcion' => $descripcion ? mb_substr($descripcion, 0, 250) : null,
            'cambios_json' => $cambios,
            'ip' => request()?->ip(),
            'created_at' => $now,
        ]);
    }

    /** Quién registró el registro y quién lo modificó por última vez (usuario, fecha y hora). */
    public static function trazaDe(string $tabla, $registroId): array
    {
        $q = fn () => static::with('usuario:id,name,apellido_paterno,perfil')
            ->where('tabla_afectada', $tabla)->where('registro_id', $registroId);
        $fila = fn (?self $l) => $l ? [
            'usuario' => $l->usuario?->nombre_corto ?? 'Sistema',
            'perfil' => $l->usuario?->perfil,
            'fecha' => $l->fecha->format('Y-m-d'),
            'hora' => substr($l->hora, 0, 5),
            'accion' => $l->accion,
        ] : null;

        $creado = $q()->where('accion', 'INSERT')->oldest('id')->first();
        $ultima = $q()->where('accion', '!=', 'INSERT')->latest('id')->first();

        return [
            'creado' => $fila($creado),
            'ultima' => $fila($ultima),
            'total' => $q()->count(),
        ];
    }

    /** Igual que trazaDe() pero para varios registros a la vez: [id => traza]. */
    public static function trazasDe(string $tabla, iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $logs = static::with('usuario:id,name,apellido_paterno,perfil')
            ->where('tabla_afectada', $tabla)->whereIn('registro_id', $ids)
            ->orderBy('id')->get(['id', 'registro_id', 'accion', 'usuario_id', 'fecha', 'hora'])
            ->groupBy('registro_id');
        $fila = fn (?self $l) => $l ? [
            'usuario' => $l->usuario?->nombre_corto ?? 'Sistema',
            'perfil' => $l->usuario?->perfil,
            'fecha' => $l->fecha->format('Y-m-d'),
            'hora' => substr($l->hora, 0, 5),
            'accion' => $l->accion,
        ] : null;

        return $ids->mapWithKeys(function ($id) use ($logs, $fila) {
            $l = $logs->get($id, collect());

            return [$id => [
                'creado' => $fila($l->firstWhere('accion', 'INSERT')),
                'ultima' => $fila($l->where('accion', '!=', 'INSERT')->last()),
                'total' => $l->count(),
            ]];
        })->all();
    }

    /** Resumen compacto de la última modificación de un registro: "Usuario · fecha hora". */
    public static function ultimaDe(string $tabla, $registroId): ?array
    {
        $log = static::with('usuario:id,name,apellido_paterno')
            ->where('tabla_afectada', $tabla)->where('registro_id', $registroId)
            ->latest('id')->first();

        return $log ? [
            'usuario' => $log->usuario?->nombre_corto ?? 'Sistema',
            'fecha' => $log->fecha->format('Y-m-d'),
            'hora' => substr($log->hora, 0, 5),
            'accion' => $log->accion,
            'total' => static::where('tabla_afectada', $tabla)->where('registro_id', $registroId)->count(),
        ] : null;
    }
}
