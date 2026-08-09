<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            'Kindergarten 1', 'Kindergarten 2',
            'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6',
            'Year 7', 'Year 8', 'Year 9', 'Year 10', 'Year 11', 'Year 12',
        ];

        foreach ($grades as $order => $name) {
            Grade::firstOrCreate(['name' => $name], ['level_order' => $order + 1]);
        }
    }
}
