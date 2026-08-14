<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hand-picked Guardian on a `selected_guardians` audience rule. See CommunicationAudienceRuleStaff. */
#[Fillable(['communication_audience_rule_id', 'guardian_id'])]
class CommunicationAudienceRuleGuardian extends Model
{
    use Auditable;

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudienceRule::class, 'communication_audience_rule_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
