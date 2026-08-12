<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive unique indexes that exist only to be composite-foreign-key TARGETS.
 *
 * Same technique as the Phase 5E planning anchors: `id` is already unique, so
 * none of these constrains anything new. They simply let a child table say
 * "this parent, AND its roster, AND its subject" in one foreign key, so a
 * module cannot claim an assignment's id while carrying a different subject or
 * a different roster.
 *
 * The roster columns are split into two indexes because exactly one of them is
 * ever non-null (the class XOR teaching-group rule). A single three-column key
 * would be skipped entirely under MATCH SIMPLE, since one column is always
 * NULL; two keys are each enforced precisely when their column is present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            $table->unique(['id', 'class_id', 'subject_id'], 'class_subject_class_anchor_unique');
            $table->unique(['id', 'teaching_group_id', 'subject_id'], 'class_subject_group_anchor_unique');
        });

        // So a journal's assessment links can be proven to belong to the same
        // teaching assignment as the journal itself.
        Schema::table('assessments', function (Blueprint $table) {
            $table->unique(['id', 'class_subject_id'], 'assessments_assignment_anchor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            $table->dropUnique('class_subject_class_anchor_unique');
            $table->dropUnique('class_subject_group_anchor_unique');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique('assessments_assignment_anchor_unique');
        });
    }
};
