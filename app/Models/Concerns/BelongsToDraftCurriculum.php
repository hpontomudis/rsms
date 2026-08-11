<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Curriculum content is versioned history: once its curriculum leaves draft,
 * the standards it states have been published and work starts pointing at
 * them. Editing them afterwards would silently rewrite what a class was
 * taught against, so the supported answer is a NEW curriculum version.
 *
 * Applied to curriculum scopes and learning outcomes. A draft is fully
 * editable, because nothing has ever relied on it.
 */
trait BelongsToDraftCurriculum
{
    public static function bootBelongsToDraftCurriculum(): void
    {
        static::saving(function ($model) {
            $model->assertParentCurriculumIsDraft($model->exists ? 'changed' : 'added');
        });

        static::deleting(function ($model) {
            $model->assertParentCurriculumIsDraft('removed');
        });
    }

    private function assertParentCurriculumIsDraft(string $verb): void
    {
        $curriculum = $this->resolveCurriculum();

        if (! $curriculum || $curriculum->isDraft()) {
            return;
        }

        throw new LogicException(
            "Curriculum content cannot be {$verb} once its curriculum version has left draft ".
            "({$curriculum->code} v{$curriculum->version} is {$curriculum->status}). ".
            'Create a new curriculum version instead.'
        );
    }
}
