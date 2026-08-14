<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * School-authored content -- what was said, to be resolved into an audience
 * and materialized into recipients elsewhere. This row never carries a
 * channel, an email address, a phone number, or a delivery status; those are
 * a different concern by design (V8A architecture review).
 *
 * `created_by_user_id` is the real audit actor, never shown to recipients.
 * `display_sender` is the recipient-facing label, editable while draft and
 * frozen at publish -- see the model's `saving()` guard below.
 *
 * PUBLISHED IS IMMUTABLE, with no correction/replacement path in V8A, same
 * rule and reasoning as PerformanceEvaluation: a mistake in a published
 * Communication is fixed by publishing a new one, never by editing this row.
 * Archiving is the ONE transition a published row may still make -- it is a
 * presentation state (hide from "current"), not an unpublish, and never
 * touches content or recipient history.
 */
#[Fillable([
    'created_by_user_id', 'display_sender', 'title', 'body', 'priority',
    'status', 'published_at', 'expires_at', 'audience_summary_snapshot',
])]
class Communication extends Model
{
    use Auditable;

    public const PRIORITIES = ['normal', 'important', 'urgent'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $communication) {
            $original = $communication->getOriginal('status');

            if ($original === 'archived') {
                throw new LogicException('An archived communication is never changed.');
            }

            if ($original === 'published') {
                $dirty = array_keys($communication->getDirty());

                if ($dirty !== ['status'] || $communication->status !== 'archived') {
                    throw new LogicException(
                        'A published communication is immutable. The only transition it may still make is to archived.'
                    );
                }
            }
        });

        static::deleting(function (self $communication) {
            if ($communication->status !== 'draft') {
                throw new LogicException('Only a draft communication can be deleted. Archive a published one instead.');
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function audienceRules(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRule::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
