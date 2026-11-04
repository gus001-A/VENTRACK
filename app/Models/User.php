<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    public const ADMIN = 'Administrador';
    public const PROMOTOR = 'Promotor';
    public const AUDITOR = 'Auditor';
    public const SUPERVISOR = 'Supervisor';
    public const PROSPECTO = 'Prospecto';

    public const PERFILES = [self::ADMIN, self::PROMOTOR, self::AUDITOR, self::SUPERVISOR, self::PROSPECTO];

    public const NIVELES_ESTUDIO = ['Primaria', 'Secundaria', 'Bachillerato', 'Carrera técnica', 'Licenciatura', 'Maestría', 'Doctorado'];

    protected $fillable = [
        'name', 'apellido_paterno', 'apellido_materno', 'email', 'password', 'perfil', 'telefono',
        'fecha_nacimiento', 'curp', 'rfc', 'direccion', 'colonia', 'ciudad', 'estado', 'codigo_postal',
        'nivel_estudios', 'estudios_detalle', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['nombre_completo'];

    protected ?array $sucursalIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'fecha_nacimiento' => 'date:Y-m-d',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->apellido_paterno, $this->apellido_materno])));
    }

    public function getNombreCortoAttribute(): string
    {
        return trim($this->name.' '.$this->apellido_paterno);
    }

    public function auditLabel(): string
    {
        return $this->nombre_completo.' ('.$this->perfil.')';
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function prospecto(): HasOne
    {
        return $this->hasOne(Prospecto::class);
    }

    /* ---------- Control de acceso (RBAC) ---------- */

    public function tienePerfil(string ...$perfiles): bool
    {
        return in_array($this->perfil, $perfiles, true);
    }

    /** Auditores y supervisores ven la totalidad de las sucursales. */
    public function esGlobal(): bool
    {
        return $this->tienePerfil(self::AUDITOR, self::SUPERVISOR);
    }

    public function puedeProgramar(): bool
    {
        return $this->tienePerfil(self::ADMIN, self::SUPERVISOR);
    }

    public function esAdmin(): bool
    {
        return $this->perfil === self::ADMIN;
    }

    /** IDs de sucursales visibles para el usuario (filtro de seguridad). */
    /** Correo de recuperación de contraseña con el formato de VENTRACK. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\RestablecerContrasena($token));
    }

    public function sucursalIds(): array
    {
        return $this->sucursalIdsCache ??= $this->esGlobal()
            ? Sucursal::pluck('id')->all()
            : $this->sucursales()->pluck('sucursales.id')->all();
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopePerfil(Builder $q, string $perfil): Builder
    {
        return $q->where('perfil', $perfil);
    }
}
