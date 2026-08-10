<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An operational group of students taught together for a specific academic
 * year -- Rahai's English proficiency groups being the case that prompted it.
 *
 * A group carrying an english_level_id is an English proficiency group; one
 * without is a generic group. There is deliberately no `kind` enum: the only
 * two behaviours the school actually has today are "is/isn't tied to an
 * English level", and inventing Remedial/Elective/OSN categories ahead of a
 * real requirement would mean guessing at rules nobody has stated.
 *
 * Groups are never auto-created from English levels. A level is a standard;
 * a group is a room of students in one year. "Green" may run as one group,
 * as "Green A"/"Green B", or not at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('english_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            // Unique per year, not globally: "Green A" recurs every year.
            $table->unique(['academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_groups');
    }
};
