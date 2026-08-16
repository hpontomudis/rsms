<?php

namespace App\Services;

use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one write path for `class_teacher` (Foundation F2). Every write goes
 * through a direct model call (`ClassTeacher::create()`/`update()`), never
 * the BelongsToMany `attach()`/`detach()`/`sync()` helpers -- see
 * `ClassTeacher`'s docblock for why that distinction is load-bearing for
 * the Auditable trail.
 *
 * Deliberately three narrow operations, not a generic "assign(role, ...)"
 * entry point: `role = 'subject_teacher'` is refused STRUCTURALLY, by the
 * simple fact that no method here accepts it, rather than by a runtime
 * guard on a generic write path. `ClassSubject` remains the canonical
 * effective-dated subject-teaching authority; existing `subject_teacher`
 * rows are historical legacy data this service never writes and never
 * reads as authoritative.
 */
class ClassTeacherService
{
    /**
     * Set the class's homeroom teacher. Transactional close-and-create:
     * whatever already points at the outgoing assignment (Communication
     * authority, Attendance access) must stop resolving to the outgoing
     * teacher the moment this commits, and the outgoing row is closed, never
     * deleted. Idempotent -- setting the already-current teacher again is a
     * no-op, not a fresh handover.
     */
    public function setHomeroom(SchoolClass $class, Staff $staff, ?Carbon $on = null): ClassTeacher
    {
        $on ??= Carbon::today();

        return DB::transaction(function () use ($class, $staff, $on) {
            // Locks the class's current homeroom row (if any) so two
            // concurrent handovers serialize instead of racing. No-op on
            // SQLite, which serializes writers anyway.
            $current = ClassTeacher::where('class_id', $class->id)
                ->where('role', 'homeroom')
                ->open()
                ->lockForUpdate()
                ->first();

            if ($current && $current->staff_id === $staff->id) {
                return $current;
            }

            $current?->update(['ended_on' => $on]);

            return ClassTeacher::create([
                'class_id' => $class->id,
                'staff_id' => $staff->id,
                'role' => 'homeroom',
                'started_on' => $on,
            ]);
        });
    }

    /**
     * Remove the class's homeroom teacher without naming a successor -- e.g.
     * a teacher resigns and a replacement isn't assigned yet. The DB
     * invariant is at-most-one, not exactly-one, so this is a legitimate,
     * separate operation rather than a handover to nobody.
     */
    public function endHomeroom(SchoolClass $class, ?Carbon $on = null): void
    {
        $current = ClassTeacher::where('class_id', $class->id)
            ->where('role', 'homeroom')
            ->open()
            ->first();

        if (! $current) {
            $this->fail('This class has no current homeroom teacher to remove.');
        }

        $this->endAssignment($current, $on);
    }

    /**
     * Assign an assistant. Plural by design -- unlike homeroom, multiple
     * Staff may hold an open 'assistant' row on the same class at once.
     * Re-assigning a Staff member already open on this class is a no-op.
     */
    public function assignAssistant(SchoolClass $class, Staff $staff, ?Carbon $on = null): ClassTeacher
    {
        $on ??= Carbon::today();

        $existing = ClassTeacher::where('class_id', $class->id)
            ->where('staff_id', $staff->id)
            ->where('role', 'assistant')
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        return ClassTeacher::create([
            'class_id' => $class->id,
            'staff_id' => $staff->id,
            'role' => 'assistant',
            'started_on' => $on,
        ]);
    }

    /**
     * Close any open assignment (homeroom or assistant) -- history is
     * closed, never hard-deleted. Used directly for assistant removal, and
     * internally by `endHomeroom()`.
     */
    public function endAssignment(ClassTeacher $assignment, ?Carbon $on = null): ClassTeacher
    {
        if (! $assignment->isOpen()) {
            $this->fail('That assignment has already ended.');
        }

        $on ??= Carbon::today();

        if ($on->lt($assignment->started_on)) {
            $this->fail('The end date cannot be before the start date.');
        }

        $assignment->update(['ended_on' => $on]);

        return $assignment;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['class_teacher' => $message]);
    }
}
