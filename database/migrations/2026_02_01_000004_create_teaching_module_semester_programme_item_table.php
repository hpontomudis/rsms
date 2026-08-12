<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which scheduled slots a module is planned to serve.
 *
 * Prosem answers WHEN; the module answers HOW. This answers "which design is
 * planned for which meeting" -- a fact neither table can state alone, and the
 * only thing here that is not derivable from the objective in common.
 *
 * Optional and many-to-many in both directions on purpose:
 *
 *  - one module may cover weeks 3 and 4 but NOT week 6, even though all three
 *    slots teach the same objective, so intersecting on TP would over-claim;
 *  - one slot may end up served by different teacher-specific modules across a
 *    handover, since Prosem is shared but modules are not.
 *
 * Compatibility (same roster, subject, year and scope) is service-validated:
 * the chain from a slot back to its roster runs through the semester programme
 * and the annual programme, and mirroring four columns here to police it with
 * keys would cost more than the rule is worth on an optional link.
 *
 * Nothing about the slot is copied. No week_label, no dates, no JP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_module_semester_programme_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teaching_module_id');
            $table->unsignedBigInteger('semester_programme_item_id');
            $table->timestamps();

            $table->unique(['teaching_module_id', 'semester_programme_item_id'], 'module_slot_unique');

            $table->foreign('teaching_module_id')->references('id')->on('teaching_modules')->restrictOnDelete();
            $table->foreign('semester_programme_item_id')->references('id')->on('semester_programme_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_module_semester_programme_item');
    }
};
