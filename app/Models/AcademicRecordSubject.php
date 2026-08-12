<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One subject line as issued.
 *
 * NOT Auditable, deliberately and unlike every Phase 5 link model. Those could
 * be edited after creation, so their changes needed a trail. These cannot: they
 * are written only inside the publication transaction, they carry no update
 * path, and the guard below refuses any change once the parent is issued. The
 * parent's audit row plus the frozen snapshot is the entire history.
 */
#[Fillable(['academic_record_id', 'subject_id', 'subject_name_snapshot', 'score', 'position'])]
class AcademicRecordSubject extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if ($row->academicRecord?->isIssued()) {
                throw new LogicException(
                    'A subject line on an issued academic record is immutable. '.
                    'Publish a correction instead; it will supersede the whole record.'
                );
            }
        });

        static::deleting(function (self $row) {
            if ($row->academicRecord?->isIssued()) {
                throw new LogicException('A subject line on an issued academic record is never deleted.');
            }
        });
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }

    /** Traceability only. The document prints subject_name_snapshot. */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
