<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Programme-to-grade applicability.
 *
 * Deliberately a full Eloquent Model rather than a Pivot, and written to with
 * create()/delete() rather than attach()/detach().
 *
 * WHY: BelongsToMany::attach(), detach() and sync() operate through the query
 * builder and do NOT fire Eloquent model events. Applying the Auditable trait
 * to a pivot used that way would silently record nothing. Because linking a
 * grade to a programme changes which students a proficiency framework governs,
 * it must be audited -- so every write goes through this model.
 */
#[Fillable(['english_programme_id', 'grade_id'])]
class EnglishProgrammeGrade extends Model
{
    use Auditable;

    protected $table = 'english_programme_grade';

    public function programme(): BelongsTo
    {
        return $this->belongsTo(EnglishProgramme::class, 'english_programme_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
