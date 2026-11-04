<?php

namespace App\Models\Concerns;

use App\Models\SysLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automáticamente en sys_logs cada alta, cambio o baja del modelo
 * con el usuario responsable, fecha, hora y los valores anteriores/nuevos.
 */
trait Auditable
{
    protected static array $auditIgnore = ['created_at', 'updated_at', 'password', 'remember_token'];

    /** Nota opcional que se agrega a la descripción del siguiente registro de bitácora. */
    public ?string $auditNota = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->writeAudit('INSERT', ['nuevo' => $model->auditPayload($model->getAttributes())]);
        });

        static::updated(function (Model $model) {
            $changes = [];
            foreach ($model->getChanges() as $key => $value) {
                if (in_array($key, static::$auditIgnore, true)) {
                    continue;
                }
                $changes[$key] = ['antes' => $model->getOriginal($key), 'despues' => $value];
            }
            if (array_key_exists('password', $model->getChanges())) {
                $changes['password'] = ['antes' => '••••', 'despues' => '•••• (actualizada)'];
            }
            if ($changes) {
                $model->writeAudit('UPDATE', $changes);
            }
        });

        static::deleted(function (Model $model) {
            $model->writeAudit('DELETE', ['anterior' => $model->auditPayload($model->getAttributes())]);
        });
    }

    protected function auditPayload(array $attributes): array
    {
        return collect($attributes)->except(static::$auditIgnore)->all();
    }

    public function auditLabel(): string
    {
        return (string) ($this->nombre ?? $this->name ?? '#'.$this->getKey());
    }

    public function writeAudit(string $accion, ?array $cambios = null, ?string $descripcion = null): SysLog
    {
        $descripcion ??= $this->auditLabel().($this->auditNota ? ' · '.$this->auditNota : '');
        $this->auditNota = null;

        return SysLog::registrar($this->getTable(), $this->getKey(), $accion, $descripcion, $cambios);
    }
}
