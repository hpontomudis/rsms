<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes an append-only AuditLog row on create/update/delete.
 *
 * Applied to models holding sensitive student/guardian/staff/access-control
 * data per PRD §18 — never applied to AuditLog itself.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAuditLog('updated', $original, $changes);
        });

        static::deleted(function ($model) {
            $action = method_exists($model, 'trashed') && $model->trashed() ? 'soft_deleted' : 'deleted';
            $model->writeAuditLog($action, $model->getOriginal(), null);
        });
    }

    protected function writeAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }
}
