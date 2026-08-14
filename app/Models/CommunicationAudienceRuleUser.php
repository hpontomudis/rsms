<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hand-picked User on a `selected_users` audience rule. See CommunicationAudienceRuleStaff. */
#[Fillable(['communication_audience_rule_id', 'user_id'])]
class CommunicationAudienceRuleUser extends Model
{
    use Auditable;

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudienceRule::class, 'communication_audience_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
