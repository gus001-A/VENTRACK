<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sucursal extends Model
{
    use Auditable;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'tipo_negocio_id', 'descripcion', 'direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal',
        'telefono', 'email', 'sitio_web', 'facebook', 'instagram', 'whatsapp', 'otras_redes', 'responsable_id', 'activa',
    ];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function tipoNegocio(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'tipo_negocio_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class);
    }

    public function prospectos(): HasMany
    {
        return $this->hasMany(Prospecto::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
