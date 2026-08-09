<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Principal',
            'Vice Principal',
            'Homeroom Teacher',
            'Subject Teacher',
            'Finance Officer',
            'Administration Staff',
            'Librarian',
            'Support Staff',
            'Building Staff',
        ];

        foreach ($positions as $title) {
            Position::firstOrCreate(['title' => $title]);
        }
    }
}
