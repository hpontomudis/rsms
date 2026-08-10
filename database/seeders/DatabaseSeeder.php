<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            GradeSeeder::class,
            PositionSeeder::class,
            AcademicYearSeeder::class,
            // Must follow AcademicYearSeeder -- periods hang off a year.
            AcademicPeriodSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@rahai.sch.id'],
            ['name' => 'RSMS Super Admin', 'password' => 'password']
        );
        $admin->assignRole('super_admin');
    }
}
