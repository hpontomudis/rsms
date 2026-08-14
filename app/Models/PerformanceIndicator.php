<?php

namespace App\Models;

use App\Evidence\EvidenceRegistry;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use LogicException;
use Illuminate\Validation\ValidationException;

/**
 * One thing being evaluated. `system_evidence_key`, if set, must name a
 * provider the application actually ships -- see EvidenceRegistry.
 *
 * `target_value`/`unit_label` are informational display context only. Nothing
 * reads them to compute a rating; see EvidenceService and the firewall tests.
 */
#[Fillable([
    'performance_section_id', 'name', 'description', 'indicator_type',
    'position', 'system_evidence_key', 'target_value', 'unit_label',
])]
class PerformanceIndicator extends Model
{
    use Auditable;

    public const TYPES = ['rubric', 'numeric', 'boolean', 'narrative'];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $indicator) {
            if ($indicator->section && ! $indicator->section->framework?->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its indicators can no longer be edited."
                );
            }

            if ($indicator->system_evidence_key !== null && ! EvidenceRegistry::has($indicator->system_evidence_key)) {
                throw ValidationException::withMessages([
                    'system_evidence_key' => "\"{$indicator->system_evidence_key}\" is not a registered evidence source.",
                ]);
            }
        });

        static::deleting(function (self $indicator) {
            if ($indicator->section && ! $indicator->section->framework?->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its indicators can no longer be removed."
                );
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PerformanceSection::class, 'performance_section_id');
    }

    public function framework(): HasOneThrough
    {
        return $this->hasOneThrough(
            PerformanceFramework::class,
            PerformanceSection::class,
            'id', 'id', 'performance_section_id', 'performance_framework_id',
        );
    }

    public function isRubric(): bool
    {
        return $this->indicator_type === 'rubric';
    }

    public function isNumeric(): bool
    {
        return $this->indicator_type === 'numeric';
    }

    public function isBoolean(): bool
    {
        return $this->indicator_type === 'boolean';
    }

    public function isNarrative(): bool
    {
        return $this->indicator_type === 'narrative';
    }

    public function hasSystemEvidence(): bool
    {
        return $this->system_evidence_key !== null;
    }
}
