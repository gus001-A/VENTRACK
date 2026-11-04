<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitaObservacion extends Model
{
    use Auditable;

    protected $table = 'visita_observaciones';

    protected $fillable = ['visita_id', 'user_id', 'estatus', 'formato_id', 'texto'];

    public function auditLabel(): string
    {
        return 'Observación en visita #'.$this->visita_id.' ('.(Visita::ETIQUETAS[$this->estatus] ?? $this->estatus).')';
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(VisitaEvidencia::class)->whereIn('tipo', ['foto', 'documento']);
    }

    public function formato(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'formato_id');
    }
}
