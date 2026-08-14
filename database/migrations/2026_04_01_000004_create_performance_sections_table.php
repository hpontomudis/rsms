<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One grouping within a framework -- "Planning & Preparation", "Professionalism"
 * -- editable only while the parent framework is draft. Once the framework is
 * active its sections are frozen along with everything beneath them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_framework_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['performance_framework_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_sections');
    }
};
