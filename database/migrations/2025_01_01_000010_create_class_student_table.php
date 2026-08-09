<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('enrolled_at');
            $table->enum('status', ['active', 'transferred_out', 'withdrawn'])->default('active');
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_student');
    }
};
