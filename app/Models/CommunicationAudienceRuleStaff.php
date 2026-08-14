<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hand-picked Staff member on a `selected_staff` audience rule.
 *
 * A real model, not accessed via attach()/detach()/sync() -- those bypass
 * Eloquent events (a standing gotcha in this codebase, see PerformanceFramework's
 * cleanup notes), which would silently defeat this being Auditable. Rows are
 * created and removed through plain create()/delete() in CommunicationService.
 */
#[Fillable(['communication_audience_rule_id', 'staff_id'])]
class CommunicationAudienceRuleStaff extends Model
{
    use Auditable;

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudienceRule::class, 'communication_audience_rule_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
