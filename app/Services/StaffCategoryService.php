<?php

namespace App\Services;

use App\Models\StaffCategory;
use Illuminate\Validation\ValidationException;

/**
 * Categories are ordinary business data -- editable, and deletable while
 * genuinely unused. RESTRICT at the database level is the real backstop
 * (`staff.staff_category_id`, `performance_frameworks.staff_category_id`);
 * this only turns that constraint violation into a readable sentence before
 * the database is even asked.
 */
class StaffCategoryService
{
    public function create(array $attributes): StaffCategory
    {
        return StaffCategory::create($attributes);
    }

    public function update(StaffCategory $category, array $attributes): StaffCategory
    {
        $category->update($attributes);

        return $category->refresh();
    }

    public function delete(StaffCategory $category): void
    {
        if ($category->isReferenced()) {
            throw ValidationException::withMessages([
                'code' => "{$category->name} is assigned to staff or a performance framework and cannot be deleted.",
            ]);
        }

        $category->delete();
    }
}
