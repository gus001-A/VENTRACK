<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaEvidencia extends Model
{
    use Auditable;

    protected $table = 'visita_evidencias';

    protected $fillable = ['visita_id', 'user_id', 'visita_observacion_id', 'estatus', 'tipo', 'archivo', 'nombre', 'latitud', 'longitud', 'precision'];

    protected function casts(): array
    {
        return ['latitud' => 'float', 'longitud' => 'float', 'precision' => 'float'];
    }

    public function auditLabel(): string
    {
        return match ($this->tipo) {
            'foto' => 'Foto',
            'documento' => 'Documento '.$this->nombre,
            default => 'Ubicación',
        }.' en visita #'.$this->visita_id;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
