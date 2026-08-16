<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generation attempt's metadata -- never its prompt or response. This
 * row IS the audit trail for AI usage; it is deliberately not itself
 * Auditable (that would be auditing the audit log).
 */
#[Fillable([
    'user_id', 'use_case', 'provider', 'model', 'prompt_version', 'status',
    'input_tokens', 'output_tokens', 'total_tokens', 'duration_ms', 'error_code', 'accepted_at',
])]
class AiGeneration extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
