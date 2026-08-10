<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An English proficiency framework. Rahai runs more than one -- Primary and
 * Junior High use different level ladders -- and Senior High uses none at all,
 * teaching English as an ordinary class-based subject.
 *
 * Programmes are reference data: archived, never deleted, once referenced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_programmes');
    }
};
