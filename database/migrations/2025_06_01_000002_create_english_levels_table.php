<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A proficiency level WITHIN a programme (Primary: Purple..Red;
 * Junior High: Level A..C).
 *
 * Uniqueness is deliberately scoped to the programme, not global -- a future
 * programme may legitimately reuse a name like "Level A".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_programme_id')->constrained('english_programmes')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->unique(['english_programme_id', 'name']);
            $table->unique(['english_programme_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_levels');
    }
};
