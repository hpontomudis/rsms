<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive and nullable. No backfill: existing staff remain uncategorized
 * until explicitly assigned, and a Performance Evaluation refuses to start
 * for an uncategorized staff member rather than guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('staff_category_id')->nullable()
                ->after('position_id')
                ->constrained('staff_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_category_id');
        });
    }
};
