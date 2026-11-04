<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Documento extends Model
{
    use Auditable;

    protected $fillable = [
        'uuid', 'documentable_type', 'documentable_id', 'tipo_documento_id', 'descripcion', 'archivo',
        'nombre_original', 'mime', 'tamano', 'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Documento $d) => $d->uuid ??= (string) Str::uuid());
    }

    public function auditLabel(): string
    {
        return ($this->tipo?->nombre ?? 'Documento').': '.$this->nombre_original;
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'tipo_documento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
