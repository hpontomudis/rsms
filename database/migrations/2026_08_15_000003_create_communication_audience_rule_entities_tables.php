<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small, explicit join tables for the four "hand-picked" rule types
 * (selected_staff / selected_guardians / selected_students / selected_users).
 * Four narrow tables rather than one polymorphic join, matching the same
 * "explicit FKs over generic polymorphism" choice made for
 * communication_recipients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_audience_rule_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_audience_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['communication_audience_rule_id', 'staff_id'], 'car_staff_unique');
        });

        Schema::create('communication_audience_rule_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_audience_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['communication_audience_rule_id', 'guardian_id'], 'car_guardian_unique');
        });

        Schema::create('communication_audience_rule_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_audience_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['communication_audience_rule_id', 'student_id'], 'car_student_unique');
        });

        Schema::create('communication_audience_rule_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_audience_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['communication_audience_rule_id', 'user_id'], 'car_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_audience_rule_users');
        Schema::dropIfExists('communication_audience_rule_students');
        Schema::dropIfExists('communication_audience_rule_guardians');
        Schema::dropIfExists('communication_audience_rule_staff');
    }
};
