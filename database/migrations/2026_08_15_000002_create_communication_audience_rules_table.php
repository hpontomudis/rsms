<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRAFT-time targeting intent, nothing more. One row per rule, typed and
 * explicit -- never a JSON filter blob, and never one generic target_id whose
 * meaning shifts with rule_type. Each rule_type that has a genuine single
 * target table gets its own nullable FK column; which column is populated for
 * a given rule_type is validated by CommunicationService, the same way
 * PerformanceEvaluationItemService enforces "exactly one field per indicator
 * type" at the service layer rather than a sprawling per-type DB CHECK.
 *
 * Once the parent Communication is published, these rows stop mattering for
 * history -- communication_recipients (materialized at publish) is the
 * historical truth from then on. Audience rules are only ever re-resolved
 * while the parent is still draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_audience_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_id')->constrained()->restrictOnDelete();

            $table->enum('rule_type', [
                'everyone',
                'all_staff',
                'staff_category',
                'role',
                'school_class_students',
                'school_class_guardians',
                'teaching_group_students',
                'teaching_group_guardians',
                'selected_staff',
                'selected_guardians',
                'selected_students',
                'selected_users',
            ]);

            // Populated only for the matching rule_type; validated in service.
            $table->foreignId('staff_category_id')->nullable()->constrained('staff_categories')->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('teaching_group_id')->nullable()->constrained('teaching_groups')->restrictOnDelete();
            // Spatie role names are stable strings, not FK'd rows -- the one
            // legitimate plain-string column here, same reasoning as V7A's
            // review of role-based audience targeting.
            $table->string('role_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_audience_rules');
    }
};
