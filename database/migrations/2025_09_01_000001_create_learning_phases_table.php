<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national Learning Phase reference (Fase) -- Foundation, A-F.
 *
 * Reference DATA, not application constants. A phase list scattered through
 * the codebase as an enum would have to be edited and redeployed the next time
 * the ministry adjusts it; a table can be corrected by an administrator.
 *
 * Capaian Pembelajaran will later belong to a phase, never to a grade: Phase C
 * spans Year 5 and Year 6 and has ONE set of outcomes across both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_phases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // FOUNDATION, A, B, C, D, E, F
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_phases');
    }
};
