<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The canonical reporting period within an academic year.
 *
 * Data-driven on purpose: Rahai currently runs "Semester 1" and "Semester 2",
 * but neither the count nor the names are encoded anywhere in application
 * logic. A year that later needs three periods is a data change, not a code
 * change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
            $table->unique(['academic_year_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
