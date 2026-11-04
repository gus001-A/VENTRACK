<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Producto extends Model
{
    use Auditable;

    protected $fillable = ['nombre', 'tipo', 'area', 'costo', 'unidad', 'descripcion', 'informacion', 'links', 'activo'];

    protected function casts(): array
    {
        return ['links' => 'array', 'costo' => 'float', 'activo' => 'boolean'];
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class);
    }

    /** Presentaciones comerciales: volantes, fichas técnicas, videos, brochures… */
    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
