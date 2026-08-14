<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What KIND of staff member this is, for the one purpose that needs it:
 * assigning a Performance Framework.
 *
 * A real lookup table, not an enum, because `positions.title` already proved
 * free text cannot safely carry application logic -- the same mistake here
 * would just move one column over. `code` is the stable identity a framework
 * points at; `name` is presentation and may be corrected without disturbing
 * anything that references the row by id.
 *
 * Existing staff are NOT backfilled into a category. Nothing in `positions`
 * can be trusted to infer one, so staff.staff_category_id starts NULL and stays
 * NULL until a human assigns it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_categories');
    }
};
