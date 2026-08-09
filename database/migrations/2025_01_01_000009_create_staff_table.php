<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->date('hire_date');
            $table->enum('status', ['active', 'on_leave', 'terminated'])->default('active');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
