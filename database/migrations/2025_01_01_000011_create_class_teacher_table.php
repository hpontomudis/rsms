<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->enum('role', ['homeroom', 'assistant', 'subject_teacher']);
            $table->timestamps();

            $table->unique(['class_id', 'staff_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teacher');
    }
};
