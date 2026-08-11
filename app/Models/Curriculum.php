<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

/**
 * One VERSION of a curriculum family.
 *
 * Identity is code + version. `name` is presentation and may be corrected;
 * identity may not, once the version has left draft.
 */
#[Fillable([
    'name', 'code', 'version', 'description', 'source_reference',
    'effective_from', 'effective_to', 'status', 'english_programme_id',
])]
class Curriculum extends Model
{
    use Auditable;

    protected $table = 'curricula';

    /** Columns that carry the version's identity. */
    private const IDENTITY = ['code', 'version', 'english_programme_id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $curriculum) {
            $from = $curriculum->effective_from;
            $to = $curriculum->effective_to;

            if ($from && $to && $to->lt($from)) {
                throw new InvalidArgumentException(
                    'A curriculum version cannot stop being effective before it starts.'
                );
            }
        });

        /**
         * Identity is immutable once a version has left draft.
         *
         * A draft has never been used, so an administrator may still correct a
         * typo in its code. The moment it is activated, records start pointing
         * at it, and rewriting `code`, `version` or the English programme
         * binding would retroactively change what those records were taught
         * against. Superseding is archive-and-create, never overwrite.
         */
        static::updating(function (self $curriculum) {
            if ($curriculum->getOriginal('status') === 'draft') {
                return;
            }

            foreach (self::IDENTITY as $column) {
                if ($curriculum->isDirty($column)) {
                    throw new LogicException(
                        "A curriculum version's identity cannot be changed once it has left draft ({$column}). ".
                        'Archive this version and create the new one instead.'
                    );
                }
            }
        });
    }

    /**
     * NULL for the national, phase-based curriculum. Set for a Rahai English
     * curriculum, which Curriculum Scope will later use to refuse a level from
     * the wrong programme.
     */
    public function englishProgramme(): BelongsTo
    {
        return $this->belongsTo(EnglishProgramme::class);
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(CurriculumScope::class);
    }

    /**
     * What this version's scopes and outcomes are CALLED. The national
     * framework's terms are Capaian Pembelajaran and Fase; a Rahai English
     * curriculum uses neutral wording rather than borrowing a government term
     * for a school's own framework. Same tables either way.
     *
     * @return array{outcome: string, outcomes: string, objective: string, objectives: string, basis: string, bases: string}
     */
    public function vocabulary(): array
    {
        return $this->isEnglishProgrammeBound()
            ? [
                'outcome' => 'Learning Outcome', 'outcomes' => 'Learning Outcomes',
                'objective' => 'Learning Objective', 'objectives' => 'Learning Objectives',
                'basis' => 'Level', 'bases' => 'Levels',
            ]
            : [
                'outcome' => 'Capaian Pembelajaran (CP)', 'outcomes' => 'Capaian Pembelajaran (CP)',
                'objective' => 'Tujuan Pembelajaran (TP)', 'objectives' => 'Tujuan Pembelajaran (TP)',
                'basis' => 'Fase', 'bases' => 'Learning Phases',
            ];
    }

    public function isEnglishProgrammeBound(): bool
    {
        return $this->english_programme_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Other versions of the same curriculum family, newest first.
     */
    public function siblingVersions(): Builder
    {
        return static::where('code', $this->code)->whereKeyNot($this->id)->orderByDesc('effective_from');
    }
}
