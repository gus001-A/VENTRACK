<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Catalogo extends Model
{
    use Auditable;

    public const TIPO_NEGOCIO = 'tipo_negocio';
    public const TIPO_DOCUMENTO = 'tipo_documento';
    public const FORMATO = 'formato_observacion';

    public const TIPOS = [
        self::TIPO_NEGOCIO => 'Tipos de negocio',
        self::TIPO_DOCUMENTO => 'Tipos de documento',
        self::FORMATO => 'Formatos de observación',
    ];

    protected $fillable = ['tipo', 'nombre', 'nota', 'plantilla', 'etapa', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeDeTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo)->where('activo', true)->orderBy('nombre');
    }

    public static function opciones(string $tipo): array
    {
        return static::deTipo($tipo)->get(['id', 'nombre', 'nota', 'plantilla', 'etapa'])->toArray();
    }

    /** Cuántos registros usan este elemento (evita borrar elementos en uso). */
    public function usos(): int
    {
        return match ($this->tipo) {
            self::TIPO_NEGOCIO => Prospecto::where('tipo_negocio_id', $this->id)->count() + Sucursal::where('tipo_negocio_id', $this->id)->count(),
            self::TIPO_DOCUMENTO => Documento::where('tipo_documento_id', $this->id)->count(),
            self::FORMATO => VisitaObservacion::where('formato_id', $this->id)->count(),
            default => 0,
        };
    }
}
