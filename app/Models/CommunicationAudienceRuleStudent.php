<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hand-picked Student on a `selected_students` audience rule. See CommunicationAudienceRuleStaff. */
#[Fillable(['communication_audience_rule_id', 'student_id'])]
class CommunicationAudienceRuleStudent extends Model
{
    use Auditable;

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudienceRule::class, 'communication_audience_rule_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
