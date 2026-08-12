<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which scheduled slot a module is planned to serve.
 *
 * The one fact neither Prosem nor the module can state alone: a module may
 * cover weeks 3 and 4 but not week 6, even where all three teach the same
 * objective. Auditable and explicit for the same reason as every other link
 * model here.
 */
#[Fillable(['teaching_module_id', 'semester_programme_item_id'])]
class TeachingModuleSemesterProgrammeItem extends Model
{
    use Auditable;

    protected $table = 'teaching_module_semester_programme_item';

    public function teachingModule(): BelongsTo
    {
        return $this->belongsTo(TeachingModule::class);
    }

    public function semesterProgrammeItem(): BelongsTo
    {
        return $this->belongsTo(SemesterProgrammeItem::class);
    }
}
