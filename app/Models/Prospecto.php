<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Prospecto extends Model
{
    use Auditable;

    protected $fillable = [
        'tipo_persona', 'nombre', 'nombre_comercial', 'rfc', 'contacto', 'telefono', 'email', 'direccion', 'colonia',
        'ciudad', 'estado', 'codigo_postal', 'latitud', 'longitud', 'tipo_negocio_id', 'sucursal_id', 'user_id', 'notas', 'activo',
    ];

    protected function casts(): array
    {
        return ['latitud' => 'float', 'longitud' => 'float', 'activo' => 'boolean'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function tipoNegocio(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'tipo_negocio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function scopeVisiblesPara(Builder $q, User $user): Builder
    {
        if ($user->perfil === User::PROSPECTO) {
            return $q->where('user_id', $user->id);
        }

        return $q->whereIn('sucursal_id', $user->sucursalIds());
    }

    public function getMapsUrlAttribute(): string
    {
        $q = $this->latitud && $this->longitud
            ? $this->latitud.','.$this->longitud
            : trim($this->direccion.', '.$this->colonia.', '.$this->ciudad, ', ');

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($q);
    }
}
