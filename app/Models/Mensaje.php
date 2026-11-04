<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensaje extends Model
{
    use Auditable;

    protected $fillable = ['prospecto_user_id', 'promotor_id', 'from_id', 'texto', 'leido_at'];

    protected function casts(): array
    {
        return ['leido_at' => 'datetime'];
    }

    public function auditLabel(): string
    {
        return 'Mensaje de '.($this->autor?->nombre_corto ?? '#'.$this->from_id);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_id');
    }
}
