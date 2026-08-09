<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
