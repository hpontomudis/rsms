<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIK (Nomor Induk Kependudukan -- Indonesian national ID) for Staff and
 * Students (P2A). Nullable and unique-where-present: dev data has zero
 * existing values for this new column, so the plain nullable unique index
 * activates immediately with no conflict -- multiple NULLs never collide
 * under a standard unique index on either PostgreSQL or SQLite, so this
 * does not need the partial-index technique used elsewhere in this
 * codebase for "at most one OPEN row" invariants; that's a different
 * problem than "at most one of a given non-null value".
 *
 * VARCHAR, not an integer type: identity numbers are identifiers, not
 * arithmetic values, and a leading zero must never be silently dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('email');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('nik');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('nik');
        });
    }
};
