<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The historical truth of who a published Communication actually reached --
 * written once, atomically, inside CommunicationService::publish(), and
 * never rewritten by a later membership, relationship, category or role
 * change (V8A architecture review item 19).
 *
 * Deliberately NOT Auditable: materialization can create many rows in one
 * Publish action, and every later `read_at` update would otherwise flood
 * audit_logs with pure noise. The Communication's own "published" audit
 * entry is the one meaningful record of the materialization event; per-row
 * detail lives here as data, not as audit history.
 *
 * CANONICAL RECIPIENT IDENTITY (staff_id / guardian_id / student_id /
 * direct_user_id -- exactly one, enforced by a DB CHECK) is a different
 * question from RESOLVED LOGIN USER (resolved_user_id). An entity with no
 * unambiguous login still gets a recipient row; resolved_user_id is simply
 * null, meaning "historically addressed, not reachable in-app." V8A has no
 * external delivery, so that is never represented as a delivery failure --
 * there is no delivery to fail.
 */
#[Fillable([
    'communication_id', 'staff_id', 'guardian_id', 'student_id', 'direct_user_id',
    'resolved_user_id', 'recipient_name_snapshot', 'recipient_context_snapshot', 'read_at',
])]
class CommunicationRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $recipient) {
            throw new LogicException('A communication recipient is historical record and is never deleted.');
        });
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function directUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_user_id');
    }

    public function resolvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }

    public function isReachable(): bool
    {
        return $this->resolved_user_id !== null;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
