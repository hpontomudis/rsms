<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\EnglishLevel;
use App\Models\LearningPhase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The rules for what a curriculum version may be scoped to.
 *
 * Every scope is created here, so the UI, the tests and any future import path
 * go through the same checks. The database enforces most of this too (see the
 * curriculum_scopes migration); this layer exists to give a usable error
 * instead of a constraint violation, and to close the one direction SQL
 * cannot see.
 */
class CurriculumScopeService
{
    /**
     * Scope a national, phase-based curriculum to a learning phase.
     */
    public function addPhase(Curriculum $curriculum, LearningPhase $phase): CurriculumScope
    {
        $this->assertDraft($curriculum);

        // THE APPLICATION-LEVEL RULE. A phase scope carries a NULL programme
        // discriminator, and SQL skips a composite foreign key whenever any of
        // its columns is NULL -- so no constraint can compare this row against
        // the curriculum's programme. It is checked here or nowhere.
        if ($curriculum->isEnglishProgrammeBound()) {
            $this->fail('learning_phase_id', "{$curriculum->name} is bound to the {$curriculum->englishProgramme->name} and is scoped by English level, not by learning phase.");
        }

        $this->assertPhaseNotAlreadyScoped($curriculum, $phase);

        return CurriculumScope::create([
            'curriculum_id' => $curriculum->id,
            'english_programme_id' => null,
            'learning_phase_id' => $phase->id,
            'english_level_id' => null,
        ]);
    }

    /**
     * Scope a Rahai English curriculum to one of ITS OWN programme's levels.
     */
    public function addEnglishLevel(Curriculum $curriculum, EnglishLevel $level): CurriculumScope
    {
        $this->assertDraft($curriculum);

        if (! $curriculum->isEnglishProgrammeBound()) {
            $this->fail('english_level_id', "{$curriculum->name} is a national curriculum and is scoped by learning phase, not by English level.");
        }

        // The commitment from the architecture work: a Primary English
        // curriculum must never scope to Junior High Level B. The composite
        // foreign keys refuse this at the database too; this produces a
        // sentence an administrator can act on.
        if ($level->english_programme_id !== $curriculum->english_programme_id) {
            $this->fail(
                'english_level_id',
                "{$level->name} belongs to the {$level->programme->name}, but {$curriculum->name} covers the {$curriculum->englishProgramme->name}."
            );
        }

        $this->assertLevelNotAlreadyScoped($curriculum, $level);

        return CurriculumScope::create([
            'curriculum_id' => $curriculum->id,
            // Mirrors the curriculum's programme so the composite foreign keys
            // can compare it against the level's. Never set by hand.
            'english_programme_id' => $curriculum->english_programme_id,
            'learning_phase_id' => null,
            'english_level_id' => $level->id,
        ]);
    }

    /**
     * Remove a scope that nothing depends on. Only while the curriculum is a
     * draft -- the model guard enforces that independently.
     */
    public function remove(CurriculumScope $scope): void
    {
        if ($scope->learningOutcomes()->exists()) {
            $this->fail('scope', 'Remove this scope\'s learning outcomes first.');
        }

        $scope->delete();
    }

    /**
     * What this curriculum may still be scoped to -- phases for a national
     * curriculum, its own programme's levels for an English one. Never the
     * other programme's levels, and never a mixture.
     */
    public function availableBases(Curriculum $curriculum): Collection
    {
        if ($curriculum->isEnglishProgrammeBound()) {
            $taken = $curriculum->scopes()->pluck('english_level_id');

            return EnglishLevel::where('english_programme_id', $curriculum->english_programme_id)
                ->whereNotIn('id', $taken)
                ->orderBy('sequence')->get();
        }

        $taken = $curriculum->scopes()->pluck('learning_phase_id');

        return LearningPhase::active()->whereNotIn('id', $taken)->orderBy('sequence')->get();
    }

    private function assertDraft(Curriculum $curriculum): void
    {
        if (! $curriculum->isDraft()) {
            $this->fail('curriculum', "{$curriculum->name} is {$curriculum->status}. Its standards are history now -- create a new version instead.");
        }
    }

    private function assertPhaseNotAlreadyScoped(Curriculum $curriculum, LearningPhase $phase): void
    {
        $exists = CurriculumScope::where('curriculum_id', $curriculum->id)
            ->where('learning_phase_id', $phase->id)->exists();

        if ($exists) {
            $this->fail('learning_phase_id', "{$phase->name} is already part of this curriculum version.");
        }
    }

    private function assertLevelNotAlreadyScoped(Curriculum $curriculum, EnglishLevel $level): void
    {
        $exists = CurriculumScope::where('curriculum_id', $curriculum->id)
            ->where('english_level_id', $level->id)->exists();

        if ($exists) {
            $this->fail('english_level_id', "{$level->name} is already part of this curriculum version.");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
