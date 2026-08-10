<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes teaching assignments effective-dated.
 *
 * Before this migration, reassigning a subject to a new teacher mutated
 * `class_subject.staff_id` in place. Because `assessments.class_subject_id`
 * points at that row, every historical assessment was retroactively
 * re-attributed to the new teacher -- and, via AssessmentPolicy, edit rights
 * transferred with it.
 *
 * After this migration a reassignment closes the current row (`ended_on`) and
 * opens a new one, so historical records keep pointing at the assignment that
 * was actually in force when they were created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            // CURRENT_DATE default (portable across pgsql/sqlite) lets the
            // column be NOT NULL immediately without a ->change() call.
            $table->date('started_on')->default(DB::raw('CURRENT_DATE'))->after('staff_id');
            $table->date('ended_on')->nullable()->after('started_on');
        });

        // Existing assignments began when they were created, not today.
        foreach (DB::table('class_subject')->select('id', 'created_at')->get() as $row) {
            DB::table('class_subject')
                ->where('id', $row->id)
                ->update(['started_on' => Carbon::parse($row->created_at)->toDateString()]);
        }

        Schema::table('class_subject', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'subject_id']);
        });

        // One ACTIVE assignment per class+subject, but unlimited closed
        // historical rows. Partial unique indexes are supported by both
        // PostgreSQL and SQLite with identical syntax.
        DB::statement(
            'CREATE UNIQUE INDEX class_subject_active_unique ON class_subject (class_id, subject_id) WHERE ended_on IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX class_subject_active_unique');

        // NOTE: this will fail if any class+subject has more than one row
        // (i.e. a reassignment has happened). That is intentional -- silently
        // discarding assignment history to satisfy a rollback would be worse.
        Schema::table('class_subject', function (Blueprint $table) {
            $table->unique(['class_id', 'subject_id']);
            $table->dropColumn(['started_on', 'ended_on']);
        });
    }
};
