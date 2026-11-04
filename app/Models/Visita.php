<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visita extends Model
{
    use Auditable;

    public const PROGRAMADA = 'programada';
    public const PROCESO = 'proceso';
    public const REALIZADA = 'realizada';
    public const REVISADA = 'revisada';
    public const SUPERVISADA = 'supervisada';

    public const ESTATUS = [self::PROGRAMADA, self::PROCESO, self::REALIZADA, self::REVISADA, self::SUPERVISADA];

    public const ETIQUETAS = [
        self::PROGRAMADA => 'Programada',
        self::PROCESO => 'En proceso',
        self::REALIZADA => 'Realizada',
        self::REVISADA => 'Revisada',
        self::SUPERVISADA => 'Supervisada',
    ];

    protected $fillable = [
        'prospecto_id', 'sucursal_id', 'promotor_id', 'programada_por', 'fecha', 'hora', 'objetivo', 'estatus',
        'resultado', 'latitud', 'longitud', 'ubicacion_at', 'iniciada_at', 'realizada_at', 'revisada_at', 'supervisada_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'latitud' => 'float',
            'longitud' => 'float',
            'ubicacion_at' => 'datetime',
            'iniciada_at' => 'datetime',
            'realizada_at' => 'datetime',
            'revisada_at' => 'datetime',
            'supervisada_at' => 'datetime',
        ];
    }

    public function auditLabel(): string
    {
        return 'Visita #'.$this->id.' · '.($this->prospecto?->nombre ?? '');
    }

    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function promotor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promotor_id');
    }

    public function programador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'programada_por');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(VisitaObservacion::class)->oldest();
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(VisitaEvidencia::class);
    }

    public function getVencidaAttribute(): bool
    {
        return $this->estatus === self::PROGRAMADA && $this->fecha->lt(today());
    }

    /**
     * Lista de trabajo: promotores ven las suyas, prospectos las de su establecimiento,
     * el resto las de sus sucursales (auditores y supervisores: todas).
     */
    public function scopeVisiblesPara(Builder $q, User $user): Builder
    {
        return match ($user->perfil) {
            User::PROMOTOR => $q->where('promotor_id', $user->id),
            User::PROSPECTO => $q->whereHas('prospecto', fn ($p) => $p->where('user_id', $user->id)),
            default => $q->whereIn('sucursal_id', $user->sucursalIds()),
        };
    }

    /** Siguiente estatus al que puede avanzar el usuario (el paso a "realizada" es el cierre positiva/negativa). */
    public function siguientePara(User $user): ?string
    {
        $next = self::ESTATUS[array_search($this->estatus, self::ESTATUS, true) + 1] ?? null;

        return match ($next) {
            self::PROCESO => ($user->esAdmin() || $user->id === $this->promotor_id) ? $next : null,
            self::REVISADA => $user->tienePerfil(User::ADMIN, User::AUDITOR, User::SUPERVISOR) ? $next : null,
            self::SUPERVISADA => $user->tienePerfil(User::ADMIN, User::SUPERVISOR) ? $next : null,
            default => null,
        };
    }

    public function puedeCerrar(User $user): bool
    {
        return $this->estatus === self::PROCESO && ($user->esAdmin() || $user->id === $this->promotor_id);
    }

    /** En cada estatus se pueden subir fotos y ubicaciones (trazabilidad); el prospecto solo consulta. */
    public function puedeRegistrarEvidencia(User $user): bool
    {
        return $this->estatus !== self::SUPERVISADA
            && $user->perfil !== User::PROSPECTO
            && ($user->perfil !== User::PROMOTOR || $user->id === $this->promotor_id);
    }

    /** Observaciones visibles: las propias, y todas para administradores, auditores y supervisores. */
    public function observacionesVisiblesPara(User $user)
    {
        if ($user->perfil === User::PROSPECTO) {
            return collect();
        }
        $q = $this->observaciones()->with(['usuario:id,name,apellido_paterno,perfil', 'formato:id,nombre', 'adjuntos']);
        if (! $user->tienePerfil(User::ADMIN, User::AUDITOR, User::SUPERVISOR)) {
            $q->where('user_id', $user->id);
        }

        return $q->get();
    }
}
