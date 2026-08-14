<?php

namespace Database\Seeders;

use App\Models\StaffCategory;
use Illuminate\Database\Seeder;

/**
 * The initial set a Performance Framework can be assigned to.
 *
 * Deliberately does NOT touch existing `staff` rows -- nothing here infers a
 * category from `positions.title`. Categorizing staff is a separate, explicit,
 * human act.
 */
class StaffCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'teacher', 'name' => 'Teacher'],
            ['code' => 'academic_leadership', 'name' => 'Academic Leadership'],
            ['code' => 'administration', 'name' => 'Administration'],
            ['code' => 'driver', 'name' => 'Driver'],
            ['code' => 'security', 'name' => 'Security'],
            ['code' => 'support', 'name' => 'Support Staff'],
        ] as $category) {
            StaffCategory::firstOrCreate(['code' => $category['code']], $category);
        }
    }
}
