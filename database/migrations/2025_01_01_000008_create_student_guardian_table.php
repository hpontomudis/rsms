<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->enum('relationship_type', ['father', 'mother', 'grandparent', 'sibling', 'legal_guardian', 'other']);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('can_pickup')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardian');
    }
};
