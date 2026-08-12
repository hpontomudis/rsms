<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program Semester: the within-period elaboration of one annual programme.
 *
 * Belongs to the annual programme rather than standing alone, so there is no
 * second planning universe that could disagree about which pathway is being
 * followed. Because annual items already carry their period, a semester
 * programme has an unambiguous set of items to schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_programmes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annual_programme_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();

            $table->unique(['annual_programme_id', 'academic_period_id'], 'semester_programme_period_unique');

            $table->foreign(['annual_programme_id', 'academic_year_id'], 'semester_programme_year_foreign')
                ->references(['id', 'academic_year_id'])->on('annual_programmes')->restrictOnDelete();

            $table->foreign(['academic_period_id', 'academic_year_id'], 'semester_programme_period_year_foreign')
                ->references(['id', 'academic_year_id'])->on('academic_periods')->restrictOnDelete();
        });

        // Anchor for the slot table.
        DB::statement(
            'CREATE UNIQUE INDEX semester_programmes_context_anchor_unique
             ON semester_programmes (id, annual_programme_id, academic_period_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_programmes');
    }
};
