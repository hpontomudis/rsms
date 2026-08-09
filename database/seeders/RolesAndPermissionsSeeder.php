<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles per PRD §7. Permissions cover the Foundation (Phase 1), Attendance
 * (Phase 2), Finance (Phase 3), and Academics (Phase 4) modules — later
 * phases add their own permissions without touching this seeder's shape.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'students.view', 'students.create', 'students.update', 'students.delete',
            'guardians.view', 'guardians.create', 'guardians.update', 'guardians.delete',
            'staff.view', 'staff.create', 'staff.update', 'staff.delete',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete',
            'academic-years.manage',
            'grades.manage',
            'roles.manage',
            'audit-logs.view',
            'attendance.record',
            'attendance.view',
            'finance.view',
            'finance.manage',
            'finance.discounts.approve',
            'academics.manage',
            'academics.record',
            'academics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // super_admin gets every permission explicitly (in addition to the
        // Gate::before bypass in AppServiceProvider) so the role stays
        // correct even if that bypass is ever removed.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'guardians.view', 'staff.view', 'classes.view', 'audit-logs.view',
                'attendance.view',
                'finance.view', 'finance.discounts.approve',
                'academics.view',
            ]);

        Role::firstOrCreate(['name' => 'admin_staff', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'students.create', 'students.update',
                'guardians.view', 'guardians.create', 'guardians.update',
                'staff.view', 'staff.create', 'staff.update',
                'classes.view', 'classes.create', 'classes.update',
                'academic-years.manage', 'grades.manage',
                'attendance.record', 'attendance.view',
                'academics.manage', 'academics.record', 'academics.view',
            ]);

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'classes.view',
                'attendance.record', 'attendance.view',
                'academics.record', 'academics.view',
            ]);

        Role::firstOrCreate(['name' => 'finance_staff', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view',
                'finance.view', 'finance.manage', 'finance.discounts.approve',
            ]);

        Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'staff.view', 'classes.view', 'audit-logs.view',
                'attendance.view', 'finance.view', 'academics.view',
            ]);

        // Parent-portal access is scoped via the student_guardian relation,
        // not RBAC permissions — the role exists now so it's ready to assign.
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
    }
}
