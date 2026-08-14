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
            // Authoring DRAFT learning pathways. Deliberately separate from
            // academics.manage: teachers plan, management puts into force.
            'academics.plan',
            'academics.record',
            'academics.view',
            // Staff performance evaluation is HR-sensitive and deliberately
            // does NOT ride on staff.view or academics.view -- a role that can
            // see the staff directory or teaching data should not thereby see
            // appraisal ratings. Self-view of one's own finalized evaluation is
            // a policy carve-out, not a permission.
            'performance.view',
            'performance.manage',
            // Broad at the permission level; teacher's actual reach is scoped
            // by CommunicationPolicy to their own current assignments -- the
            // permission alone never authorises a school-wide audience.
            'communications.view',
            'communications.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // super_admin gets every permission explicitly (in addition to the
        // Gate::before bypass in AppServiceProvider) so the role stays
        // correct even if that bypass is ever removed.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        // The principal holds school-wide academic oversight, which includes
        // managing academic standards (English programmes/levels, and later
        // curriculum) -- hence academics.manage, not just academics.view.
        Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'guardians.view', 'staff.view', 'classes.view', 'audit-logs.view',
                'attendance.view',
                'finance.view', 'finance.discounts.approve',
                'academics.view', 'academics.manage', 'academics.plan',
                // The principal is the evaluator: creates, edits, finalizes.
                'performance.manage',
                // School-wide publishing authority -- any audience.
                'communications.manage',
            ]);

        Role::firstOrCreate(['name' => 'admin_staff', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'students.create', 'students.update',
                'guardians.view', 'guardians.create', 'guardians.update',
                'staff.view', 'staff.create', 'staff.update',
                'classes.view', 'classes.create', 'classes.update',
                'academic-years.manage', 'grades.manage',
                'attendance.record', 'attendance.view',
                'academics.manage', 'academics.plan', 'academics.record', 'academics.view',
            ]);

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'])
            ->syncPermissions([
                'students.view', 'classes.view',
                'attendance.record', 'attendance.view',
                'academics.plan', 'academics.record', 'academics.view',
                // Scoped to their own current Classes/Teaching Groups by
                // CommunicationPolicy -- see its docblock.
                'communications.manage',
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
                // Read-only cross-module oversight, which is this role's whole
                // purpose -- extends naturally to reading (not judging) ratings.
                'performance.view',
                // Read-only here too -- management does not publish.
                'communications.view',
            ]);

        // Parent-portal access is scoped via the student_guardian relation,
        // not RBAC permissions — the role exists now so it's ready to assign.
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
    }
}
