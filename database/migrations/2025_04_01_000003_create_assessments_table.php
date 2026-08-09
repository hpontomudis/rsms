<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete (not cascade): removing a class-subject
            // assignment must never silently wipe out its assessments.
            $table->foreignId('class_subject_id')->constrained('class_subject')->restrictOnDelete();
            $table->string('name');
            $table->string('term');
            $table->decimal('max_score', 6, 2);
            $table->date('assessment_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
