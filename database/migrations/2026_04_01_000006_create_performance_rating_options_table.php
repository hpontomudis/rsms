<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One framework, one rating scale -- so the scale IS the framework's options,
 * with no separate `performance_rating_scales` table to link through.
 *
 * A rubric indicator's response, and an evaluation's overall rating, both
 * reference a row here via a COMPOSITE foreign key that includes
 * performance_framework_id -- so the database itself refuses an option
 * borrowed from a different framework, not just the service layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_rating_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_framework_id')->constrained()->restrictOnDelete();
            $table->smallInteger('value');
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['performance_framework_id', 'value']);
            $table->unique(['performance_framework_id', 'position']);
        });

        DB::statement('CREATE UNIQUE INDEX performance_rating_options_anchor_unique ON performance_rating_options (id, performance_framework_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_rating_options');
    }
};
