<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait para registrar em audit_logs quem criou e quem editou.
 * Preenche created_by e updated_by quando o model tem essas colunas.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('created_by', $model->getFillable(), true) && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (Auth::check() && in_array('updated_by', $model->getFillable(), true) && empty($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function (Model $model): void {
            if (Auth::check() && in_array('updated_by', $model->getFillable(), true)) {
                $model->updated_by = Auth::id();
            }
        });

        static::created(function (Model $model): void {
            self::writeAudit($model, AuditLog::ACTION_CREATED, null, $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            self::writeAudit($model, AuditLog::ACTION_UPDATED, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model): void {
            self::writeAudit($model, AuditLog::ACTION_DELETED, $model->getAttributes(), null);
        });
    }

    protected static function writeAudit(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $userId = Auth::id();
        $key = $model->getKey();
        $auditableId = $key !== null ? (string) $key : '';

        $payload = [
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => AuditLog::auditableTypeFor($model),
            'auditable_id' => $auditableId,
            'old_values' => $oldValues ? json_encode(self::sanitizeForAudit($oldValues)) : null,
            'new_values' => $newValues ? json_encode(self::sanitizeForAudit($newValues)) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent() ? substr(Request::userAgent(), 0, 500) : null,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        AuditLog::query()->insert($payload);
    }

    /**
     * Remove campos sensíveis e limita tamanho para não estourar JSON.
     */
    protected static function sanitizeForAudit(array $data): array
    {
        $skip = ['password', 'password_confirmation', 'remember_token', 'token'];
        $out = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            if (is_string($value) && strlen($value) > 5000) {
                $value = substr($value, 0, 5000) . '...';
            }
            $out[$key] = $value;
        }
        return $out;
    }
}
